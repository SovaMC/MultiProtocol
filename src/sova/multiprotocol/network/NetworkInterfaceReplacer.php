<?php

declare(strict_types=1);

namespace sova\multiprotocol\network;

use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\mcpe\raklib\RakLibServer;
use pocketmine\Server;
use pocketmine\thread\NonThreadSafeValue;
use raklib\utils\InternetAddress;
use sova\multiprotocol\config\MultiProtocolConfig;
use sova\multiprotocol\network\raklib\MultiProtocolRakLibInterface;
use sova\multiprotocol\protocol\ProtocolException;
use sova\multiprotocol\protocol\ProtocolRegistry;
use sova\multiprotocol\utils\Reflection;
use function is_int;

final class NetworkInterfaceReplacer
{
	private ?ProtocolPacketPool $packetPool = null;

	/** @var array<string, true> */
	private array $replaced = [];

	public function __construct(
		private readonly Server $server,
		private readonly ProtocolRegistry $registry,
		private readonly MultiProtocolConfig $config
	) {
	}

	public function hasReplaced(): bool
	{
		return $this->replaced !== [];
	}

	public function replace(RakLibInterface $original): void
	{
		$address = $this->readAddress($original);

		$sleeperNotifierId = Reflection::get(RakLibInterface::class, $original, 'sleeperNotifierId');
		if (is_int($sleeperNotifierId)) {
			$this->server->getTickSleeper()->removeNotifier($sleeperNotifierId);
		}

		$this->replaced[$address->toString()] = true;

		$this->server->getNetwork()->registerInterface(new MultiProtocolRakLibInterface(
			$this->server,
			$address,
			self::read($original, 'packetBroadcaster', PacketBroadcaster::class),
			self::read($original, 'entityEventBroadcaster', EntityEventBroadcaster::class),
			self::read($original, 'typeConverter', TypeConverter::class),
			$this->registry,
			$this->packetPool ??= new ProtocolPacketPool(),
			$this->config
		));
	}

	private function readAddress(RakLibInterface $original): InternetAddress
	{
		$rakLib = self::read($original, 'rakLib', RakLibServer::class);
		$address = Reflection::get(RakLibServer::class, $rakLib, 'address');

		if (!$address instanceof NonThreadSafeValue) {
			throw new ProtocolException('Unable to resolve RakLib bind address');
		}

		$value = $address->deserialize();
		if (!$value instanceof InternetAddress) {
			throw new ProtocolException('Unable to resolve RakLib bind address');
		}

		return $value;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T
	 */
	private static function read(RakLibInterface $original, string $property, string $class): object
	{
		$value = Reflection::get(RakLibInterface::class, $original, $property);

		if (!$value instanceof $class) {
			throw new ProtocolException('Unexpected value of RakLibInterface::$' . $property);
		}

		return $value;
	}
}
