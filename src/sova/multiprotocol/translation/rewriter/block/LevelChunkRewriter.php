<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\block\DimensionTracker;
use sova\multiprotocol\translation\TranslationContext;
use sova\multiprotocol\utils\Reflection;

/**
 * @extends TypedPacketRewriter<LevelChunkPacket>
 */
final class LevelChunkRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(LevelChunkPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		if ($this->peek($packet)->getSubChunkRequestLimit() !== null) {
			return;
		}

		$chunk = $this->decode($packet);
		$translated = $this->context->chunks->translate(
			$packet->direction,
			$chunk->getExtraPayload(),
			$chunk->getSubChunkCount(),
			$this->context->blockActors,
			$packet->session->get(DimensionTracker::class)->getDimension()
		);

		Reflection::set(LevelChunkPacket::class, $chunk, 'extraPayload', $translated->payload);
		Reflection::set(LevelChunkPacket::class, $chunk, 'subChunkCount', $translated->subChunkCount);
	}
}
