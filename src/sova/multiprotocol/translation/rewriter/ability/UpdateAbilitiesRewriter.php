<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\ability;

use pocketmine\network\mcpe\protocol\UpdateAbilitiesPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\ability\AbilitiesFilter;

/**
 * @extends TypedPacketRewriter<UpdateAbilitiesPacket>
 */
final class UpdateAbilitiesRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly AbilitiesFilter $filter,
		int $codecProtocolId
	) {
		parent::__construct(UpdateAbilitiesPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$this->replace($packet, UpdateAbilitiesPacket::create($this->filter->filter($this->peek($packet)->getData())));
	}
}
