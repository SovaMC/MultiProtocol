<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<PlayerAuthInputPacket>
 */
final class PlayerAuthInputRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(PlayerAuthInputPacket::class, $context->codecProtocolId, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		if ($this->peek($packet)->getItemInteractionData() === null) {
			return;
		}

		$interaction = $this->decode($packet)->getItemInteractionData();
		if ($interaction !== null) {
			$this->context->transactions->translate($packet->direction, $interaction->getTransactionData());
		}
	}
}
