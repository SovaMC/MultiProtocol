<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_18\rewriter;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class PlayerActionRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::PLAYER_ACTION_PACKET, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(CommonTypes::getActorRuntimeId(...));
		$packet->passthrough(VarInt::readSignedInt(...));
		$position = $packet->passthrough(static fn(ByteBufferReader $in) => CommonTypes::getBlockPosition($in, false));
		CommonTypes::putBlockPosition($packet->writer(), $position, false);
	}
}
