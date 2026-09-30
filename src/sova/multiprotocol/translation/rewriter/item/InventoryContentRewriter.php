<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;
use function array_map;
use function count;

/**
 * @extends TypedPacketRewriter<InventoryContentPacket>
 */
final class InventoryContentRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(InventoryContentPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$content = $this->decode($packet);
		$content->items = array_map(
			fn(ItemStackWrapper $item) => $this->context->items->wrapper($packet->direction, $item),
			$content->items
		);
		if ($this->context->legacyItems !== null) {
			$out = new ByteBufferWriter();
			VarInt::writeUnsignedInt($out, $content->windowId);
			VarInt::writeUnsignedInt($out, count($content->items));
			foreach ($content->items as $item) {
				$this->context->legacyItems->writeWrapper($out, $item);
			}
			$packet->replacePayload($out->getData());
		}
	}
}
