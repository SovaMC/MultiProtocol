<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pocketmine\network\mcpe\protocol\InventorySlotPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<InventorySlotPacket>
 */
final class InventorySlotRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(InventorySlotPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$slot = $this->decode($packet);
		$slot->item = $this->context->items->wrapper($packet->direction, $slot->item);
	}
}
