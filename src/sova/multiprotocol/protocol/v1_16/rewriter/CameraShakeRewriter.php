<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class CameraShakeRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::CAMERA_SHAKE_PACKET, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(LE::readFloat(...));
		$packet->passthrough(LE::readFloat(...));
		$packet->passthrough(Byte::readUnsigned(...));
		Byte::readUnsigned($packet->reader());
	}
}
