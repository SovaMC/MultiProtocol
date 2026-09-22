<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
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
		$equipment = $this->decode($packet);
		$equipment->item = $this->context->items->wrapper($packet->direction, $equipment->item);
	}
}
