<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
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
		$equipment = $this->decode($packet);
		$items = $this->context->items;
		$direction = $packet->direction;

		$equipment->head = $items->wrapper($direction, $equipment->head);
		$equipment->chest = $items->wrapper($direction, $equipment->chest);
		$equipment->legs = $items->wrapper($direction, $equipment->legs);
		$equipment->feet = $items->wrapper($direction, $equipment->feet);
	}
}
