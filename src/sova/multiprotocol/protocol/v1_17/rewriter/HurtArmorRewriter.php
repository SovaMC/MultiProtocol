<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_17\rewriter;

use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class HurtArmorRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::HURT_ARMOR_PACKET, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(VarInt::readSignedInt(...));
		$packet->passthrough(VarInt::readSignedInt(...));
		$packet->discardRemaining();
	}
}
