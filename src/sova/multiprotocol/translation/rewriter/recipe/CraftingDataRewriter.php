<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\recipe;

use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<CraftingDataPacket>
 */
final class CraftingDataRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(CraftingDataPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$this->context->recipes->translate($packet->direction, $this->decode($packet));
	}
}
