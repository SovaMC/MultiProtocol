<?php

declare(strict_types=1);

namespace sova\multiprotocol\session;

use Closure;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\compression\CompressBatchPromise;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\compression\DecompressionException;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\encryption\EncryptionContext;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\handler\HandshakePacketHandler;
use pocketmine\network\mcpe\handler\LoginPacketHandler;
use pocketmine\network\mcpe\handler\PacketHandler;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use pocketmine\network\NetworkSessionManager;
use pocketmine\network\PacketHandlingException;
use pocketmine\Server;
use sova\multiprotocol\config\MultiProtocolConfig;
use sova\multiprotocol\event\ProtocolSessionOpenEvent;
use sova\multiprotocol\network\BatchCodec;
use sova\multiprotocol\network\ProtocolPacketBroadcaster;
use sova\multiprotocol\network\UnknownPacket;
use sova\multiprotocol\packet\PacketHeader;
use sova\multiprotocol\protocol\ProtocolConstants;
use sova\multiprotocol\protocol\ProtocolException;
use sova\multiprotocol\protocol\ProtocolPipeline;
use sova\multiprotocol\protocol\ProtocolRegistry;
use sova\multiprotocol\protocol\transport\CompressionType;
use sova\multiprotocol\protocol\transport\EncryptionType;
use sova\multiprotocol\session\handshake\LegacyHandshake;
use sova\multiprotocol\session\handshake\LegacyLogin;
use sova\multiprotocol\session\login\LenientLoginPacketHandler;
use sova\multiprotocol\utils\Reflection;
use Throwable;
use function base64_encode;
use function is_string;
use function substr;

class MultiProtocolNetworkSession extends NetworkSession
{
	private const int DEBUG_DUMP_LENGTH = 256;

	private ?ProtocolSession $protocolSession = null;

	private bool $handshakeInspected = false;
	private bool $translationBypassed = false;
	private bool $outboundDiscarded = false;
	private bool $translationFailed = false;

	public function __construct(
		Server $server,
		NetworkSessionManager $manager,
		PacketPool $packetPool,
		PacketSender $sender,
		PacketBroadcaster $broadcaster,
		EntityEventBroadcaster $entityEventBroadcaster,
		Compressor $compressor,
		TypeConverter $typeConverter,
		string $ip,
		int $port,
		private readonly ProtocolRegistry $registry,
		private readonly MultiProtocolConfig $config
	) {
		parent::__construct($server, $manager, $packetPool, $sender, $broadcaster, $entityEventBroadcaster, $compressor, $typeConverter, $ip, $port);
	}

	public function getProtocolSession(): ?ProtocolSession
	{
		return $this->protocolSession;
	}

	public function isTranslated(): bool
	{
		return $this->protocolSession !== null;
	}

	public function tick(): void
	{
		if ($this->translationFailed && $this->isConnected()) {
			$this->translationFailed = false;
			$this->disconnectWithError('Protocol translation error');
			return;
		}

		parent::tick();
	}

	public function handleEncoded(string $payload): void
	{
		if (!$this->handshakeInspected) {
			$this->handshakeInspected = true;

			$login = LegacyHandshake::detect($payload);
			if ($login !== null && !$this->beginLegacySession($login)) {
				return;
			}
		}

		parent::handleEncoded($payload);
	}

	public function handleDataPacket(Packet $packet, string $buffer): void
	{
		if ($this->translationBypassed) {
			parent::handleDataPacket($packet, $buffer);
			return;
		}

		$session = $this->protocolSession;
		if ($session !== null) {
			$this->handleTranslated($this->translateServerbound($session, $buffer));
			return;
		}

		if ($packet instanceof RequestNetworkSettingsPacket && $this->beginNetworkSettingsSession($buffer)) {
			return;
		}

		if ($packet instanceof UnknownPacket) {
			throw new PacketHandlingException('Unknown packet ' . $packet->pid() . ' received');
		}

		parent::handleDataPacket($packet, $buffer);
	}

	/**
	 * @internal
	 * @param list<string> $buffers
	 * @throws PacketHandlingException
	 */
	public function handleTranslated(array $buffers): void
	{
		$pool = PacketPool::getInstance();

		foreach ($buffers as $buffer) {
			try {
				$id = PacketHeader::peekId($buffer);
			} catch (DataDecodeException $e) {
				throw PacketHandlingException::wrap($e, 'Malformed translated packet header');
			}

			$packet = $pool->getPacketById($id) ?? throw new PacketHandlingException('Unknown packet ' . $id . ' after translation from ' . $this->describeClient());
			parent::handleDataPacket($packet, $buffer);

			if (!$this->isConnected()) {
				return;
			}
		}
	}

	public function addToSendBuffer(string $buffer): void
	{
		if ($this->outboundDiscarded) {
			return;
		}

		$session = $this->protocolSession;
		if ($session === null) {
			parent::addToSendBuffer($buffer);
			return;
		}

		$this->sendTranslated($this->translateClientbound($session, $buffer));
	}

	/**
	 * @internal
	 * @param list<string> $buffers
	 */
	public function sendTranslated(array $buffers): void
	{
		foreach ($buffers as $buffer) {
			parent::addToSendBuffer($buffer);
		}
	}

	public function queueCompressed(CompressBatchPromise|string $payload, bool $immediate = false): void
	{
		$session = $this->protocolSession;
		if ($session === null) {
			parent::queueCompressed($payload, $immediate);
			return;
		}

		if (is_string($payload)) {
			$batch = $this->translateBatch($session, $payload);
			if ($batch !== null) {
				parent::queueCompressed($batch, $immediate);
			}
			return;
		}

		if ($payload->isCancelled()) {
			return;
		}

		$translated = new CompressBatchPromise();
		parent::queueCompressed($translated, $immediate);

		$payload->onResolve(function (CompressBatchPromise $source) use ($session, $translated): void {
			if (!$translated->isCancelled()) {
				$translated->resolve($this->translateBatch($session, $source->getResult()) ?? $this->compress(''));
			}
		});
	}

	public function setHandler(?PacketHandler $handler): void
	{
		if ($handler instanceof LoginPacketHandler && !$handler instanceof LenientLoginPacketHandler && $this->protocolSession !== null) {
			$handler = self::lenientLoginHandler($handler);
		}

		parent::setHandler($handler);

		$transport = $this->protocolSession?->client->transport;
		if ($handler instanceof HandshakePacketHandler && $transport !== null && $transport->hasCustomEncryption()) {
			$this->replaceCipher($transport->encryption);
		}
	}

	public function getBroadcaster(): PacketBroadcaster
	{
		$session = $this->protocolSession;

		return $session !== null ? ProtocolPacketBroadcaster::get($session->serverProtocolId) : parent::getBroadcaster();
	}

	public function getEntityEventBroadcaster(): EntityEventBroadcaster
	{
		if ($this->protocolSession === null) {
			return parent::getEntityEventBroadcaster();
		}

		return Server::getInstance()->getEntityEventBroadcaster($this->getBroadcaster(), $this->getTypeConverter());
	}

	private function beginLegacySession(LegacyLogin $login): bool
	{
		$pipeline = $this->resolvePipeline($login->protocolId);

		if ($pipeline === null || !$pipeline->client->transport->isLegacyHandshake() || !$this->openProtocolSession($pipeline, $login->compression)) {
			$this->rejectLegacySession($login);
			return false;
		}

		$this->outboundDiscarded = true;
		$this->translationBypassed = true;
		try {
			parent::handleEncoded(LegacyHandshake::createNetworkSettingsRequest($pipeline->serverProtocolId));
		} finally {
			$this->outboundDiscarded = false;
			$this->translationBypassed = false;
		}

		$this->applyClientProtocol($pipeline);

		return $this->isConnected();
	}

	private function rejectLegacySession(LegacyLogin $login): void
	{
		$this->replaceCompressor($login->compression);
		parent::setProtocolId(ProtocolConstants::LEGACY_BASE_PROTOCOL);
		$this->enableCompression = true;
		$this->disconnectIncompatibleProtocol($login->protocolId);
	}

	/**
	 * @throws PacketHandlingException
	 */
	private function beginNetworkSettingsSession(string $buffer): bool
	{
		try {
			$request = new RequestNetworkSettingsPacket();
			$request->decode(new ByteBufferReader($buffer), ProtocolInfo::CURRENT_PROTOCOL);
		} catch (PacketDecodeException $e) {
			throw PacketHandlingException::wrap($e, 'Malformed network settings request');
		}

		$protocolId = $request->getProtocolVersion();
		if (ProtocolConstants::isNative($protocolId)) {
			return false;
		}

		$pipeline = $this->resolvePipeline($protocolId);
		if ($pipeline === null || $pipeline->client->transport->isLegacyHandshake()) {
			return false;
		}

		if (!$this->openProtocolSession($pipeline)) {
			$this->disconnectIncompatibleProtocol($protocolId);
			return true;
		}

		$translated = RequestNetworkSettingsPacket::create($pipeline->serverProtocolId);
		$writer = new ByteBufferWriter();
		$translated->encode($writer, $pipeline->serverProtocolId);

		$this->translationBypassed = true;
		try {
			parent::handleDataPacket($translated, $writer->getData());
		} finally {
			$this->translationBypassed = false;
		}

		$this->applyClientProtocol($pipeline);

		return true;
	}

	private static function lenientLoginHandler(LoginPacketHandler $handler): LenientLoginPacketHandler
	{
		$argument = static fn(string $property): mixed => Reflection::get(LoginPacketHandler::class, $handler, $property);

		$server = $argument('server');
		$session = $argument('session');
		$playerInfoConsumer = $argument('playerInfoConsumer');
		$authCallback = $argument('authCallback');

		if (!$server instanceof Server || !$session instanceof NetworkSession || !$playerInfoConsumer instanceof Closure || !$authCallback instanceof Closure) {
			throw new ProtocolException('Unexpected LoginPacketHandler state');
		}

		return new LenientLoginPacketHandler($server, $session, $playerInfoConsumer, $authCallback);
	}

	private function applyClientProtocol(ProtocolPipeline $pipeline): void
	{
		if ($this->isConnected()) {
			$this->setProtocolId($pipeline->client->id);
		}
	}

	private function resolvePipeline(int $protocolId): ?ProtocolPipeline
	{
		if (!$this->registry->isVirtual($protocolId)) {
			return null;
		}

		try {
			return $this->registry->getPipeline($protocolId);
		} catch (ProtocolException $e) {
			$this->getLogger()->warning('Unable to translate protocol ' . $protocolId . ': ' . $e->getMessage());
			return null;
		}
	}

	private function openProtocolSession(ProtocolPipeline $pipeline, ?CompressionType $compression = null): bool
	{
		$event = new ProtocolSessionOpenEvent($this, $pipeline);
		$event->call();
		if ($event->isCancelled()) {
			return false;
		}

		$this->replaceCompressor($compression ?? $pipeline->client->transport->compression);

		$session = new ProtocolSession($this, $pipeline);
		$this->protocolSession = $session;

		foreach ($pipeline->protocols as $protocol) {
			$protocol->onSessionOpen($session);
		}

		$this->getLogger()->debug('Translating ' . $pipeline->client . ' to native protocol ' . $pipeline->serverProtocolId);

		return true;
	}

	/**
	 * @return list<string>
	 * @throws PacketHandlingException
	 */
	private function translateServerbound(ProtocolSession $session, string $buffer): array
	{
		try {
			return $session->serverbound($buffer);
		} catch (DataDecodeException|PacketDecodeException|ProtocolException $e) {
			$this->dumpPacket('Serverbound', $buffer);
			throw PacketHandlingException::wrap($e, 'Failed to translate packet ' . self::describePacket($buffer) . ' from ' . $this->describeClient());
		} catch (Throwable $e) {
			$this->getLogger()->logException($e);
			$this->dumpPacket('Serverbound', $buffer);
			throw PacketHandlingException::wrap($e, 'Internal error while translating packet ' . self::describePacket($buffer) . ' from ' . $this->describeClient());
		}
	}

	/**
	 * @return list<string>
	 */
	private function translateClientbound(ProtocolSession $session, string $buffer): array
	{
		try {
			return $session->clientbound($buffer);
		} catch (DataDecodeException|PacketDecodeException|ProtocolException $e) {
			$this->getLogger()->error('Failed to translate packet ' . self::describePacket($buffer) . ' to ' . $this->describeClient() . ': ' . $e->getMessage());
			$this->dumpPacket('Clientbound', $buffer);
			return [];
		} catch (Throwable $e) {
			$this->getLogger()->error('Internal error while translating packet ' . self::describePacket($buffer) . ' to ' . $this->describeClient());
			$this->getLogger()->logException($e);
			$this->dumpPacket('Clientbound', $buffer);
			$this->translationFailed = true;
			return [];
		}
	}

	private function translateBatch(ProtocolSession $session, string $payload): ?string
	{
		$buffers = [];

		try {
			$batch = BatchCodec::decompress($payload, $session->serverProtocolId, $this->getCompressor());
			foreach (BatchCodec::split($batch) as $buffer) {
				foreach ($this->translateClientbound($session, $buffer) as $translated) {
					$buffers[] = $translated;
				}
			}
		} catch (DecompressionException|PacketDecodeException $e) {
			$this->getLogger()->error('Failed to translate compressed batch to ' . $this->describeClient() . ': ' . $e->getMessage());
			return null;
		}

		return $buffers === [] ? null : $this->compress(BatchCodec::join($buffers));
	}

	private function compress(string $batch): string
	{
		$result = Server::getInstance()->prepareBatch($batch, $this->getProtocolId(), $this->getCompressor(), true);

		return is_string($result) ? $result : $result->getResult();
	}

	private function replaceCompressor(CompressionType $compression): void
	{
		if ($compression !== CompressionType::DEFLATE) {
			Reflection::set(NetworkSession::class, $this, 'compressor', $compression->createCompressor());
		}
	}

	private function replaceCipher(EncryptionType $encryption): void
	{
		$cipher = Reflection::get(NetworkSession::class, $this, 'cipher');
		if (!$cipher instanceof EncryptionContext) {
			return;
		}

		$key = Reflection::get(EncryptionContext::class, $cipher, 'key');
		if (is_string($key)) {
			Reflection::set(NetworkSession::class, $this, 'cipher', $encryption->createContext($key));
		}
	}

	private function describeClient(): string
	{
		return $this->protocolSession !== null ? (string) $this->protocolSession->client : (string) $this->getProtocolId();
	}

	private static function describePacket(string $buffer): string
	{
		try {
			return (string) PacketHeader::peekId($buffer);
		} catch (DataDecodeException) {
			return '?';
		}
	}

	private function dumpPacket(string $direction, string $buffer): void
	{
		if ($this->config->debug) {
			$this->getLogger()->info($direction . ' packet dump: ' . base64_encode(substr($buffer, 0, self::DEBUG_DUMP_LENGTH)));
		}
	}
}
