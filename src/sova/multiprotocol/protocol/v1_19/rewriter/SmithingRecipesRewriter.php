<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;

/**
 * @extends TypedPacketRewriter<CraftingDataPacket>
 */
final class SmithingRecipesRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly bool $keepTransformRecipes,
		int $codecProtocolId
	) {
		parent::__construct(CraftingDataPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$data = $this->decode($packet);
		$data->smithingTrimRecipes = [];
		if (!$this->keepTransformRecipes) {
			$data->smithingTransformRecipes = [];
		}
	}
}
