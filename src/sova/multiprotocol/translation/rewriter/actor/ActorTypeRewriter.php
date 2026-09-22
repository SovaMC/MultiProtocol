<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\actor;

use pocketmine\network\mcpe\protocol\AddActorPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\actor\ActorIdentifiers;

/**
 * @extends TypedPacketRewriter<AddActorPacket>
 */
final class ActorTypeRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly ActorIdentifiers $identifiers,
		int $codecProtocolId
	) {
		parent::__construct(AddActorPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$type = $this->peek($packet)->type;
		$translated = $this->identifiers->translate($type);

		if ($translated !== $type) {
			$this->decode($packet)->type = $translated;
		}
	}
}
