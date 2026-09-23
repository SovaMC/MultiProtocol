<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\ability;

use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\ability\AbilitiesFilter;

/**
 * @extends TypedPacketRewriter<AddPlayerPacket>
 */
final class AddPlayerAbilitiesRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly AbilitiesFilter $filter,
		int $codecProtocolId
	) {
		parent::__construct(AddPlayerPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$player = $this->decode($packet);
		$player->abilities = $this->filter->filter($player->abilities);
	}
}
