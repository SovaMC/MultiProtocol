<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pocketmine\network\mcpe\protocol\CraftingEventPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;
use function array_map;

/**
 * @extends TypedPacketRewriter<CraftingEventPacket>
 */
final class CraftingEventRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(CraftingEventPacket::class, $context->codecProtocolId, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$event = $this->decode($packet);
		$translate = fn(ItemStackWrapper $item) => $this->context->items->wrapper($packet->direction, $item);

		$event->input = array_map($translate, $event->input);
		$event->output = array_map($translate, $event->output);
	}
}
