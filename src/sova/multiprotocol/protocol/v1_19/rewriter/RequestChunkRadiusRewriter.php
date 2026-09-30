<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use function max;
use function min;

final class RequestChunkRadiusRewriter extends AbstractPacketRewriter
{
	private const int MAX_RADIUS = 255;

	public function __construct()
	{
		parent::__construct(ProtocolInfo::REQUEST_CHUNK_RADIUS_PACKET, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$radius = $packet->passthrough(VarInt::readSignedInt(...));
		Byte::writeUnsigned($packet->writer(), max(0, min(self::MAX_RADIUS, $radius)));
	}
}
