<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<MobArmorEquipmentPacket>
 */
final class MobArmorEquipmentRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(MobArmorEquipmentPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		if ($packet->direction === Direction::SERVERBOUND && $this->context->legacyItems !== null) {
			$in = $packet->reader();
			$out = new ByteBufferWriter();
			CommonTypes::putActorRuntimeId($out, CommonTypes::getActorRuntimeId($in));
			for ($i = 0; $i < 4; ++$i) {
				CommonTypes::putItemStackWrapper($out, $this->context->codecProtocolId, new ItemStackWrapper(0, $this->context->legacyItems->read($in)), false);
			}
			$packet->replacePayload($out->getData());
		}
		$equipment = $this->decode($packet);
		$items = $this->context->items;
		$direction = $packet->direction;

		$equipment->head = $items->wrapper($direction, $equipment->head);
		$equipment->chest = $items->wrapper($direction, $equipment->chest);
		$equipment->legs = $items->wrapper($direction, $equipment->legs);
		$equipment->feet = $items->wrapper($direction, $equipment->feet);
		if ($direction === Direction::CLIENTBOUND && $this->context->legacyItems !== null) {
			$out = new ByteBufferWriter();
			CommonTypes::putActorRuntimeId($out, $equipment->actorRuntimeId);
			foreach ([$equipment->head, $equipment->chest, $equipment->legs, $equipment->feet] as $item) {
				$this->context->legacyItems->write($out, $item->getItemStack());
			}
			$packet->replacePayload($out->getData());
		}
	}
}
