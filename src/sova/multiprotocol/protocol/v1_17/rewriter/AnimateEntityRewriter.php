<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_17\rewriter;

use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class AnimateEntityRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::ANIMATE_ENTITY_PACKET, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(CommonTypes::getString(...));
		$packet->passthrough(CommonTypes::getString(...));
		$packet->passthrough(CommonTypes::getString(...));
		LE::readSignedInt($packet->reader());
	}
}
