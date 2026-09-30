<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class MoveActorDeltaRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::MOVE_ACTOR_DELTA_PACKET, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(CommonTypes::getActorRuntimeId(...));
		$flags = $packet->passthrough(LE::readUnsignedShort(...));
		foreach ([MoveActorDeltaPacket::FLAG_HAS_X, MoveActorDeltaPacket::FLAG_HAS_Y, MoveActorDeltaPacket::FLAG_HAS_Z] as $flag) {
			if (($flags & $flag) !== 0) {
				VarInt::writeSignedInt($packet->writer(), (int) LE::readFloat($packet->reader()));
			}
		}
		foreach ([MoveActorDeltaPacket::FLAG_HAS_PITCH, MoveActorDeltaPacket::FLAG_HAS_YAW, MoveActorDeltaPacket::FLAG_HAS_HEAD_YAW] as $flag) {
			if (($flags & $flag) !== 0) {
				$packet->passthrough(Byte::readUnsigned(...));
			}
		}
	}
}
