<?php

declare(strict_types=1);

namespace sova\multiprotocol\network;

use pmmp\encoding\ByteBufferWriter;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;

final class ProtocolPacketBroadcaster implements PacketBroadcaster
{
	/** @var array<int, self> */
	private static array $instances = [];

	private function __construct(
		private readonly int $protocolId
	) {
	}

	public static function get(int $protocolId): self
	{
		return self::$instances[$protocolId] ??= new self($protocolId);
	}

	public function broadcastPackets(array $recipients, array $packets): void
	{
		if (DataPacketSendEvent::hasHandlers()) {
			$event = new DataPacketSendEvent($recipients, $packets);
			$event->call();
			if ($event->isCancelled()) {
				return;
			}
			$packets = $event->getPackets();
		}

		$buffers = [];
		$writer = new ByteBufferWriter();
		foreach ($packets as $packet) {
			$writer->clear();
			$buffers[] = NetworkSession::encodePacketTimed($writer, $this->protocolId, $packet);
		}

		foreach ($recipients as $recipient) {
			foreach ($buffers as $buffer) {
				$recipient->addToSendBuffer($buffer);
			}
		}
	}
}
