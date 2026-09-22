<?php

declare(strict_types=1);

namespace sova\multiprotocol\network\raklib;

use pocketmine\network\mcpe\PacketSender;

final class MultiProtocolPacketSender implements PacketSender
{
	private bool $closed = false;

	public function __construct(
		private readonly int $sessionId,
		private readonly MultiProtocolRakLibInterface $handler
	) {
	}

	public function send(string $payload, bool $immediate, ?int $receiptId): void
	{
		if (!$this->closed) {
			$this->handler->putPacket($this->sessionId, $payload, $immediate, $receiptId);
		}
	}

	public function close(string $reason = "unknown reason"): void
	{
		if (!$this->closed) {
			$this->closed = true;
			$this->handler->close($this->sessionId);
		}
	}
}
