<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<MobEquipmentPacket>
 */
final class MobEquipmentRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(MobEquipmentPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		if ($packet->direction === Direction::SERVERBOUND && $this->context->legacyItems !== null) {
			$in = $packet->reader();
			$out = new ByteBufferWriter();
			CommonTypes::putActorRuntimeId($out, CommonTypes::getActorRuntimeId($in));
			CommonTypes::putItemStackWrapper($out, $this->context->codecProtocolId, new ItemStackWrapper(0, $this->context->legacyItems->read($in)), false);
			$out->writeByteArray($in->readByteArray(3));
			$packet->replacePayload($out->getData());
		}
		$equipment = $this->decode($packet);
		$equipment->item = $this->context->items->wrapper($packet->direction, $equipment->item);
		if ($packet->direction === Direction::CLIENTBOUND && $this->context->legacyItems !== null) {
			$out = new ByteBufferWriter();
			CommonTypes::putActorRuntimeId($out, $equipment->actorRuntimeId);
			$this->context->legacyItems->write($out, $equipment->item->getItemStack());
			Byte::writeUnsigned($out, $equipment->inventorySlot);
			Byte::writeUnsigned($out, $equipment->hotbarSlot);
			Byte::writeUnsigned($out, $equipment->windowId);
			$packet->replacePayload($out->getData());
		}
	}
}
