<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<AddItemActorPacket>
 */
final class AddItemActorRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(AddItemActorPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$actor = $this->decode($packet);
		$actor->item = $this->context->items->wrapper($packet->direction, $actor->item);
	}
}
