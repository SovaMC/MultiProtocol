<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<ActorEventPacket>
 */
final class ActorEventRewriter extends TypedPacketRewriter
{
	private const int ITEM_ID_SHIFT = 16;
	private const int ITEM_META_MASK = 0xffff;

	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(ActorEventPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		if ($this->peek($packet)->eventId !== ActorEvent::EATING_ITEM) {
			return;
		}

		$event = $this->decode($packet);
		$items = $this->context->items;
		$direction = $packet->direction;

		[$id, $meta] = $items->idMeta($direction, $event->eventData >> self::ITEM_ID_SHIFT, $event->eventData & self::ITEM_META_MASK)
			?? [$items->fallback($direction), 0];

		$event->eventData = ($id << self::ITEM_ID_SHIFT) | ($meta & self::ITEM_META_MASK);
	}
}
