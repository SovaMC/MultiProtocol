<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class EmoteRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::EMOTE_PACKET, Direction::CLIENTBOUND, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(CommonTypes::getActorRuntimeId(...));
		$packet->passthrough(CommonTypes::getString(...));

		if ($packet->direction === Direction::CLIENTBOUND) {
			CommonTypes::getString($packet->reader());
			CommonTypes::getString($packet->reader());
		} else {
			CommonTypes::putString($packet->writer(), '');
			CommonTypes::putString($packet->writer(), '');
		}
	}
}
