<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16\rewriter;

use pmmp\encoding\Byte;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class ContainerCloseRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::CONTAINER_CLOSE_PACKET, Direction::CLIENTBOUND, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(Byte::readUnsigned(...));
		if ($packet->direction === Direction::CLIENTBOUND) {
			CommonTypes::getBool($packet->reader());
		} else {
			CommonTypes::putBool($packet->writer(), false);
		}
	}
}
