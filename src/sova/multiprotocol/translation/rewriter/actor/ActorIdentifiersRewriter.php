<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\actor;

use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\actor\ActorIdentifiers;

/**
 * @extends TypedPacketRewriter<AvailableActorIdentifiersPacket>
 */
final class ActorIdentifiersRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly ActorIdentifiers $identifiers,
		int $codecProtocolId
	) {
		parent::__construct(AvailableActorIdentifiersPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$this->replace($packet, AvailableActorIdentifiersPacket::create(new CacheableNbt(clone $this->identifiers->nbt)));
	}
}
