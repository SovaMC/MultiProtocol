<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class StructureBlockUpdateRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::STRUCTURE_BLOCK_UPDATE_PACKET, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthroughAll();
		CommonTypes::putBool($packet->writer(), false);
	}
}
