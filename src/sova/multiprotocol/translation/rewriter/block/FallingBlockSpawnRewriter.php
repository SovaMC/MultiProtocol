<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\block\FallingBlockTracker;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<AddActorPacket>
 */
final class FallingBlockSpawnRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(AddActorPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$actor = $this->peek($packet);
		if ($actor->type !== EntityIds::FALLING_BLOCK) {
			return;
		}

		$packet->session->get(FallingBlockTracker::class)->track($actor->actorRuntimeId);

		$actor = $this->decode($packet);
		$actor->metadata = $this->context->variants->translate($packet->direction, $actor->metadata);
	}
}
