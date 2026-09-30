<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\CraftingEventPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
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
		if ($this->context->legacyItems !== null) {
			$in = $packet->reader();
			$out = new ByteBufferWriter();
			Byte::writeUnsigned($out, Byte::readUnsigned($in));
			VarInt::writeSignedInt($out, VarInt::readSignedInt($in));
			CommonTypes::putUUID($out, CommonTypes::getUUID($in));
			for ($list = 0; $list < 2; ++$list) {
				$count = VarInt::readUnsignedInt($in);
				VarInt::writeUnsignedInt($out, $count);
				for ($i = 0; $i < $count; ++$i) {
					CommonTypes::putItemStackWrapper($out, $this->context->codecProtocolId, new ItemStackWrapper(0, $this->context->legacyItems->read($in)), false);
				}
			}
			$packet->replacePayload($out->getData());
		}
		$event = $this->decode($packet);
		$translate = fn(ItemStackWrapper $item) => $this->context->items->wrapper($packet->direction, $item);

		$event->input = array_map($translate, $event->input);
		$event->output = array_map($translate, $event->output);
	}
}
