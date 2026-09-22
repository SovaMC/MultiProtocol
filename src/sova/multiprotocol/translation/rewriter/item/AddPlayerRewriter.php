<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<AddPlayerPacket>
 */
final class AddPlayerRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(AddPlayerPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$player = $this->decode($packet);
		$player->item = $this->context->items->wrapper($packet->direction, $player->item);
	}
}
