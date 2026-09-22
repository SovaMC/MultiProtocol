<?php

declare(strict_types=1);

namespace sova\multiprotocol\network;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PacketHandlerInterface;
use pocketmine\network\mcpe\protocol\ServerboundPacket;

final class UnknownPacket extends DataPacket implements ServerboundPacket
{
	private int $packetId = 0;

	public static function create(int $packetId): self
	{
		$packet = new self();
		$packet->packetId = $packetId;

		return $packet;
	}

	public function pid(): int
	{
		return $this->packetId;
	}

	public function canBeSentBeforeLogin(): bool
	{
		return true;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId): void
	{
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId): void
	{
	}

	public function handle(PacketHandlerInterface $handler): bool
	{
		return false;
	}
}
