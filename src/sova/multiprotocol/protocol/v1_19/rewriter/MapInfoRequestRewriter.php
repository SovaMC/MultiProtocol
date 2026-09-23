<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class MapInfoRequestRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::MAP_INFO_REQUEST_PACKET, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthroughAll();
		LE::writeUnsignedInt($packet->writer(), 0);
	}
}
