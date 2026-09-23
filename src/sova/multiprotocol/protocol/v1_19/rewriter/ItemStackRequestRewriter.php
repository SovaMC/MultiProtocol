<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\translation\inventory\LegacyItemStackRequestReader;

final class ItemStackRequestRewriter extends AbstractPacketRewriter
{
	public function __construct(
		private readonly LegacyItemStackRequestReader $requests,
		private readonly int $codecProtocolId
	) {
		parent::__construct(ProtocolInfo::ITEM_STACK_REQUEST_PACKET, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$count = $packet->passthrough(VarInt::readUnsignedInt(...));
		for ($i = 0; $i < $count; ++$i) {
			$this->requests->read($packet->reader())->write($packet->writer(), $this->codecProtocolId);
		}
	}
}
