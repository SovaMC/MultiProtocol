<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class MovePlayerRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::MOVE_PLAYER_PACKET, Direction::CLIENTBOUND, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(CommonTypes::getActorRuntimeId(...));
		$packet->passthrough(CommonTypes::getVector3(...));
		for ($i = 0; $i < 3; ++$i) {
			$packet->passthrough(LE::readFloat(...));
		}
		$mode = $packet->passthrough(Byte::readUnsigned(...));
		$packet->passthrough(CommonTypes::getBool(...));
		$packet->passthrough(CommonTypes::getActorRuntimeId(...));
		if ($mode === MovePlayerPacket::MODE_TELEPORT) {
			$packet->passthroughBytes(8);
		}
		if ($packet->direction === Direction::CLIENTBOUND) {
			VarInt::readUnsignedLong($packet->reader());
		} else {
			VarInt::writeUnsignedLong($packet->writer(), 0);
		}
	}
}
