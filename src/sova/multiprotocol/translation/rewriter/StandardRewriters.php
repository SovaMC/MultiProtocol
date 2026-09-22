<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter;

use sova\multiprotocol\packet\PacketRewriter;
use sova\multiprotocol\translation\actor\ActorIdentifiers;
use sova\multiprotocol\translation\rewriter\actor\ActorIdentifiersRewriter;
use sova\multiprotocol\translation\rewriter\actor\ActorTypeRewriter;
use sova\multiprotocol\translation\rewriter\block\FallingBlockDataRewriter;
use sova\multiprotocol\translation\rewriter\block\FallingBlockRemoveRewriter;
use sova\multiprotocol\translation\rewriter\block\FallingBlockSpawnRewriter;
use sova\multiprotocol\translation\rewriter\block\LevelChunkRewriter;
use sova\multiprotocol\translation\rewriter\block\LevelEventRewriter;
use sova\multiprotocol\translation\rewriter\block\LevelSoundEventRewriter;
use sova\multiprotocol\translation\rewriter\block\UpdateBlockRewriter;
use sova\multiprotocol\translation\rewriter\block\UpdateBlockSyncedRewriter;
use sova\multiprotocol\translation\rewriter\block\UpdateSubChunkBlocksRewriter;
use sova\multiprotocol\translation\rewriter\item\ActorEventRewriter;
use sova\multiprotocol\translation\rewriter\item\AddItemActorRewriter;
use sova\multiprotocol\translation\rewriter\item\AddPlayerRewriter;
use sova\multiprotocol\translation\rewriter\item\CraftingEventRewriter;
use sova\multiprotocol\translation\rewriter\item\InventoryContentRewriter;
use sova\multiprotocol\translation\rewriter\item\InventorySlotRewriter;
use sova\multiprotocol\translation\rewriter\item\InventoryTransactionRewriter;
use sova\multiprotocol\translation\rewriter\item\MobArmorEquipmentRewriter;
use sova\multiprotocol\translation\rewriter\item\MobEquipmentRewriter;
use sova\multiprotocol\translation\rewriter\item\PlayerAuthInputRewriter;
use sova\multiprotocol\translation\rewriter\recipe\CraftingDataRewriter;
use sova\multiprotocol\translation\rewriter\recipe\CreativeContentRewriter;
use sova\multiprotocol\translation\TranslationContext;

final class StandardRewriters
{
	private function __construct()
	{
	}

	/**
	 * @return list<PacketRewriter>
	 */
	public static function blocks(TranslationContext $context): array
	{
		return [
			new LevelChunkRewriter($context),
			new UpdateBlockRewriter($context),
			new UpdateBlockSyncedRewriter($context),
			new UpdateSubChunkBlocksRewriter($context),
			new LevelEventRewriter($context),
			new LevelSoundEventRewriter($context),
			new FallingBlockSpawnRewriter($context),
			new FallingBlockDataRewriter($context),
			new FallingBlockRemoveRewriter($context),
		];
	}

	/**
	 * @return list<PacketRewriter>
	 */
	public static function items(TranslationContext $context): array
	{
		return [
			new InventoryContentRewriter($context),
			new InventorySlotRewriter($context),
			new MobEquipmentRewriter($context),
			new MobArmorEquipmentRewriter($context),
			new AddItemActorRewriter($context),
			new AddPlayerRewriter($context),
			new CraftingEventRewriter($context),
			new InventoryTransactionRewriter($context),
			new PlayerAuthInputRewriter($context),
			new ActorEventRewriter($context),
			new CraftingDataRewriter($context),
			new CreativeContentRewriter($context),
		];
	}

	/**
	 * @return list<PacketRewriter>
	 */
	public static function actors(ActorIdentifiers $identifiers, int $codecProtocolId): array
	{
		return [
			new ActorIdentifiersRewriter($identifiers, $codecProtocolId),
			new ActorTypeRewriter($identifiers, $codecProtocolId),
		];
	}
}
