<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\network\mcpe\protocol\types\UpdateSubChunkBlocksPacketEntry;
use pocketmine\network\mcpe\protocol\UpdateSubChunkBlocksPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;
use function array_map;

/**
 * @extends TypedPacketRewriter<UpdateSubChunkBlocksPacket>
 */
final class UpdateSubChunkBlocksRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(UpdateSubChunkBlocksPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$update = $this->peek($packet);
		$translate = fn(UpdateSubChunkBlocksPacketEntry $entry) => $this->translateEntry($packet->direction, $entry);

		$this->replace($packet, UpdateSubChunkBlocksPacket::create(
			$update->getBaseBlockPosition(),
			array_map($translate, $update->getLayer0Updates()),
			array_map($translate, $update->getLayer1Updates())
		));
	}

	private function translateEntry(Direction $direction, UpdateSubChunkBlocksPacketEntry $entry): UpdateSubChunkBlocksPacketEntry
	{
		return new UpdateSubChunkBlocksPacketEntry(
			$entry->getBlockPosition(),
			$this->context->blocks->map($direction, $entry->getBlockRuntimeId()),
			$entry->getFlags(),
			$entry->getSyncedUpdateActorUniqueId(),
			$entry->getSyncedUpdateType()
		);
	}
}
