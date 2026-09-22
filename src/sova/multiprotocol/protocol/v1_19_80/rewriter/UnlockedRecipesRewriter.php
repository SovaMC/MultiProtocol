<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_80\rewriter;

use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\UnlockedRecipesPacket;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class UnlockedRecipesRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::UNLOCKED_RECIPES_PACKET, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$type = LE::readUnsignedInt($packet->reader());

		if ($type === UnlockedRecipesPacket::TYPE_REMOVE || $type === UnlockedRecipesPacket::TYPE_REMOVE_ALL) {
			$packet->cancel();
			return;
		}

		CommonTypes::putBool($packet->writer(), $type === UnlockedRecipesPacket::TYPE_NEWLY_UNLOCKED);
	}
}
