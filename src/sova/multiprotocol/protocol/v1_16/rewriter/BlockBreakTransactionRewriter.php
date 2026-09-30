<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16\rewriter;

use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;

/**
 * @extends TypedPacketRewriter<InventoryTransactionPacket>
 */
final class BlockBreakTransactionRewriter extends TypedPacketRewriter
{
	public function __construct(int $codecProtocolId)
	{
		parent::__construct(InventoryTransactionPacket::class, $codecProtocolId, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$data = $this->peek($packet)->trData;
		if (!$data instanceof UseItemTransactionData || $data->getActionType() !== UseItemTransactionData::ACTION_BREAK_BLOCK) {
			return;
		}

		$packet->replace(PlayerActionPacket::create(
			0,
			PlayerAction::PREDICT_DESTROY_BLOCK,
			$data->getBlockPosition(),
			$data->getBlockPosition(),
			$data->getFace()
		), $this->codecProtocolId);
	}
}
