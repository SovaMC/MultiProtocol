<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\block\FallingBlockTracker;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<RemoveActorPacket>
 */
final class FallingBlockRemoveRewriter extends TypedPacketRewriter
{
	public function __construct(TranslationContext $context)
	{
		parent::__construct(RemoveActorPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->session->get(FallingBlockTracker::class)->untrack($this->peek($packet)->actorUniqueId);
	}
}
