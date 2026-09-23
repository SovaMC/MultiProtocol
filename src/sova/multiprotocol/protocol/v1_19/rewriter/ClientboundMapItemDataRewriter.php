<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class ClientboundMapItemDataRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::CLIENTBOUND_MAP_ITEM_DATA_PACKET, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(CommonTypes::getActorUniqueId(...));
		$packet->passthrough(VarInt::readUnsignedInt(...));
		$packet->passthrough(Byte::readUnsigned(...));
		$packet->passthrough(CommonTypes::getBool(...));
		CommonTypes::getBlockPosition($packet->reader(), false);
	}
}
