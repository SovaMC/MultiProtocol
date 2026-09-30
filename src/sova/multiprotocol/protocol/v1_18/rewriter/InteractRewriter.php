<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_18\rewriter;

use pmmp\encoding\Byte;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class InteractRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::INTERACT_PACKET, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$action = $packet->passthrough(Byte::readUnsigned(...));
		$packet->passthrough(CommonTypes::getActorRuntimeId(...));

		if ($action === InteractPacket::ACTION_LEAVE_VEHICLE) {
			CommonTypes::putVector3($packet->writer(), Vector3::zero());
		}
	}
}
