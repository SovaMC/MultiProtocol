<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\block\DimensionTracker;

/**
 * @extends TypedPacketRewriter<ChangeDimensionPacket>
 */
final class ChangeDimensionRewriter extends TypedPacketRewriter
{
	public function __construct(int $codecProtocolId)
	{
		parent::__construct(ChangeDimensionPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->session->get(DimensionTracker::class)->setDimension($this->peek($packet)->dimension);
	}
}
