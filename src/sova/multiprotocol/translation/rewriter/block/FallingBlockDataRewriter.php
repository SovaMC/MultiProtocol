<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\block\FallingBlockTracker;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<SetActorDataPacket>
 */
final class FallingBlockDataRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(SetActorDataPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		if (!$packet->session->get(FallingBlockTracker::class)->isTracked($this->peek($packet)->actorRuntimeId)) {
			return;
		}

		$data = $this->decode($packet);
		$data->metadata = $this->context->variants->translate($packet->direction, $data->metadata);
	}
}
