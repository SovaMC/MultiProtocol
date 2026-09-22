<?php

declare(strict_types=1);

namespace sova\multiprotocol\network\raklib;

use Exception;
use pmmp\thread\ThreadSafeArray;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\network\AdvancedNetworkInterface;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\raklib\PthreadsChannelReader;
use pocketmine\network\mcpe\raklib\PthreadsChannelWriter;
use pocketmine\network\Network;
use pocketmine\network\NetworkInterfaceStartException;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\GameMode;
use pocketmine\Server;
use pocketmine\thread\ThreadCrashException;
use pocketmine\timings\Timings;
use pocketmine\utils\Utils;
use pocketmine\YmlServerProperties;
use raklib\generic\DisconnectReason;
use raklib\generic\SocketException;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\PacketReliability;
use raklib\server\ipc\RakLibToUserThreadMessageReceiver;
use raklib\server\ipc\UserToRakLibThreadMessageSender;
use raklib\server\ServerEventListener;
use raklib\utils\InternetAddress;
use sova\multiprotocol\config\MultiProtocolConfig;
use sova\multiprotocol\network\ProtocolPacketPool;
use sova\multiprotocol\protocol\ProtocolRegistry;
use sova\multiprotocol\protocol\transport\Transport;
use sova\multiprotocol\session\MultiProtocolNetworkSession;
use Throwable;
use function addcslashes;
use function base64_encode;
use function implode;
use function mt_rand;
use function rtrim;
use function substr;
use const PHP_INT_MAX;

final class MultiProtocolRakLibInterface implements ServerEventListener, AdvancedNetworkInterface
{
	private const string MCPE_RAKNET_PACKET_ID = "\xfe";

	private const string SERVER_NAME_FLAG_TRUE = "1";
	private const string SERVER_NAME_FLAG_FALSE = "0";

	private Network $network;

	private readonly int $rakServerId;
	private readonly MultiProtocolRakLibServer $rakLib;
	private RakLibToUserThreadMessageReceiver $eventReceiver;
	private readonly UserToRakLibThreadMessageSender $interface;
	private readonly int $sleeperNotifierId;

	/** @var array<int, MultiProtocolNetworkSession> */
	private array $sessions = [];

	public function __construct(
		private readonly Server $server,
		InternetAddress $address,
		private readonly PacketBroadcaster $packetBroadcaster,
		private readonly EntityEventBroadcaster $entityEventBroadcaster,
		private readonly TypeConverter $typeConverter,
		private readonly ProtocolRegistry $registry,
		private readonly ProtocolPacketPool $packetPool,
		private readonly MultiProtocolConfig $config
	) {
		$this->rakServerId = mt_rand(0, PHP_INT_MAX);

		$sleeperEntry = $this->server->getTickSleeper()->addNotifier(function (): void {
			Timings::$connection->startTiming();
			try {
				do {
					$handled = $this->eventReceiver->handle($this);
				} while ($handled);
			} finally {
				Timings::$connection->stopTiming();
			}
		});
		$this->sleeperNotifierId = $sleeperEntry->getNotifierId();

		/** @phpstan-var ThreadSafeArray<int, string> $mainToThreadBuffer */
		$mainToThreadBuffer = new ThreadSafeArray();
		/** @phpstan-var ThreadSafeArray<int, string> $threadToMainBuffer */
		$threadToMainBuffer = new ThreadSafeArray();

		$this->rakLib = new MultiProtocolRakLibServer(
			$this->server->getLogger(),
			$mainToThreadBuffer,
			$threadToMainBuffer,
			$address,
			$this->rakServerId,
			$this->server->getConfigGroup()->getPropertyInt(YmlServerProperties::NETWORK_MAX_MTU_SIZE, 1492),
			Transport::RAKNET_VERSION,
			$this->registry->getRaknetVersions(),
			$sleeperEntry
		);
		$this->eventReceiver = new RakLibToUserThreadMessageReceiver(new PthreadsChannelReader($threadToMainBuffer));
		$this->interface = new UserToRakLibThreadMessageSender(new PthreadsChannelWriter($mainToThreadBuffer));
	}

	public function start(): void
	{
		$this->server->getLogger()->debug("Waiting for RakLib to start...");
		try {
			$this->rakLib->startAndWait();
		} catch (SocketException $e) {
			throw new NetworkInterfaceStartException($e->getMessage(), 0, $e);
		}
		$this->server->getLogger()->debug("RakLib booted successfully");
	}

	public function setNetwork(Network $network): void
	{
		$this->network = $network;
	}

	public function tick(): void
	{
		if (!$this->rakLib->isRunning()) {
			$crashInfo = $this->rakLib->getCrashInfo();
			if ($crashInfo !== null) {
				throw new ThreadCrashException("RakLib crashed", $crashInfo);
			}
			throw new Exception("RakLib Thread crashed without crash information");
		}
	}

	public function onClientConnect(int $sessionId, string $address, int $port, int $clientID): void
	{
		$this->sessions[$sessionId] = new MultiProtocolNetworkSession(
			$this->server,
			$this->network->getSessionManager(),
			$this->packetPool,
			new MultiProtocolPacketSender($sessionId, $this),
			$this->packetBroadcaster,
			$this->entityEventBroadcaster,
			ZlibCompressor::getInstance(),
			$this->typeConverter,
			$address,
			$port,
			$this->registry,
			$this->config
		);
	}

	public function onClientDisconnect(int $sessionId, int $reason): void
	{
		if (!isset($this->sessions[$sessionId])) {
			return;
		}

		$session = $this->sessions[$sessionId];
		unset($this->sessions[$sessionId]);
		$session->onClientDisconnect(match ($reason) {
			DisconnectReason::CLIENT_DISCONNECT => KnownTranslationFactory::pocketmine_disconnect_clientDisconnect(),
			DisconnectReason::PEER_TIMEOUT => KnownTranslationFactory::pocketmine_disconnect_error_timeout(),
			DisconnectReason::CLIENT_RECONNECT => KnownTranslationFactory::pocketmine_disconnect_clientReconnect(),
			default => "Unknown RakLib disconnect reason (ID $reason)"
		});
	}

	public function close(int $sessionId): void
	{
		if (isset($this->sessions[$sessionId])) {
			unset($this->sessions[$sessionId]);
			$this->interface->closeSession($sessionId);
		}
	}

	public function shutdown(): void
	{
		$this->server->getTickSleeper()->removeNotifier($this->sleeperNotifierId);
		$this->rakLib->quit();
	}

	public function onPacketReceive(int $sessionId, string $packet): void
	{
		$session = $this->sessions[$sessionId] ?? null;
		if ($session === null) {
			return;
		}

		if ($packet === "" || $packet[0] !== self::MCPE_RAKNET_PACKET_ID) {
			$session->getLogger()->debug("Non-FE packet received: " . base64_encode($packet));
			return;
		}

		$address = $session->getIp();
		$name = $session->getDisplayName();
		try {
			$session->handleEncoded(substr($packet, 1));
		} catch (PacketHandlingException $e) {
			$session->disconnectWithError(
				reason: "Bad packet: " . $e->getMessage(),
				disconnectScreenMessage: KnownTranslationFactory::pocketmine_disconnect_error_badPacket()
			);
			$session->getLogger()->debug(implode("\n", Utils::printableExceptionInfo($e)));

			$this->interface->blockAddress($address, 5);
		} catch (Throwable $e) {
			$this->server->getLogger()->emergency("Crash occurred while handling a packet from session: $name");
			throw $e;
		}
	}

	public function blockAddress(string $address, int $timeout = 300): void
	{
		$this->interface->blockAddress($address, $timeout);
	}

	public function unblockAddress(string $address): void
	{
		$this->interface->unblockAddress($address);
	}

	public function onRawPacketReceive(string $address, int $port, string $payload): void
	{
		$this->network->processRawPacket($this, $address, $port, $payload);
	}

	public function sendRawPacket(string $address, int $port, string $payload): void
	{
		$this->interface->sendRaw($address, $port, $payload);
	}

	public function addRawPacketFilter(string $regex): void
	{
		$this->interface->addRawPacketFilter($regex);
	}

	public function onPacketAck(int $sessionId, int $identifierACK): void
	{
		($this->sessions[$sessionId] ?? null)?->handleAckReceipt($identifierACK);
	}

	public function setName(string $name): void
	{
		$info = $this->server->getQueryInformation();
		$isOnline = $this->server->getOnlineMode();

		$this->interface->setName(implode(";", [
			"MCPE",
			rtrim(addcslashes($name, ";"), '\\'),
			ProtocolInfo::CURRENT_PROTOCOL,
			ProtocolInfo::MINECRAFT_VERSION_NETWORK,
			$info->getPlayerCount(),
			$info->getMaxPlayerCount(),
			$this->rakServerId,
			$this->server->getName(),
			match ($this->server->getGamemode()) {
				GameMode::SURVIVAL => "Survival",
				GameMode::ADVENTURE => "Adventure",
				default => "Creative"
			},
			self::SERVER_NAME_FLAG_TRUE,
			(string) $this->server->getPort(),
			(string) $this->server->getPortV6(),
			self::SERVER_NAME_FLAG_FALSE,
			$isOnline ? self::SERVER_NAME_FLAG_TRUE : self::SERVER_NAME_FLAG_FALSE,
			!$isOnline ? self::SERVER_NAME_FLAG_TRUE : self::SERVER_NAME_FLAG_FALSE,
		]) . ";");
	}

	public function setPortCheck(bool $name): void
	{
		$this->interface->setPortCheck($name);
	}

	public function setPacketLimit(int $limit): void
	{
		$this->interface->setPacketsPerTickLimit($limit);
	}

	public function onBandwidthStatsUpdate(int $bytesSentDiff, int $bytesReceivedDiff): void
	{
		$this->network->getBandwidthTracker()->add($bytesSentDiff, $bytesReceivedDiff);
	}

	public function putPacket(int $sessionId, string $payload, bool $immediate = true, ?int $receiptId = null): void
	{
		if (!isset($this->sessions[$sessionId])) {
			return;
		}

		$packet = new EncapsulatedPacket();
		$packet->buffer = self::MCPE_RAKNET_PACKET_ID . $payload;
		$packet->reliability = PacketReliability::RELIABLE_ORDERED;
		$packet->orderChannel = 0;
		$packet->identifierACK = $receiptId;

		$this->interface->sendEncapsulated($sessionId, $packet, $immediate);
	}

	public function onPingMeasure(int $sessionId, int $pingMS): void
	{
		($this->sessions[$sessionId] ?? null)?->updatePing($pingMS);
	}
}
