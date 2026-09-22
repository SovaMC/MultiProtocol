<?php

declare(strict_types=1);

namespace sova\multiprotocol\network;

use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\PacketPool;
use sova\multiprotocol\packet\PacketHeader;

final class ProtocolPacketPool extends PacketPool
{
	public function registerPacket(Packet $packet): void
	{
		PacketPool::getInstance()->registerPacket($packet);
	}

	public function getPacketById(int $pid): Packet
	{
		return PacketPool::getInstance()->getPacketById($pid) ?? UnknownPacket::create($pid);
	}

	public function getPacket(string $buffer): Packet
	{
		return $this->getPacketById(PacketHeader::peekId($buffer));
	}

	public function getAll(): array
	{
		return PacketPool::getInstance()->getAll();
	}
}
