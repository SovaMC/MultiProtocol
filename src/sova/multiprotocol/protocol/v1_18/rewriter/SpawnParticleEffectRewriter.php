<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_18\rewriter;

use pmmp\encoding\Byte;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class SpawnParticleEffectRewriter extends AbstractPacketRewriter
{
	public function __construct()
	{
		parent::__construct(ProtocolInfo::SPAWN_PARTICLE_EFFECT_PACKET, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(Byte::readUnsigned(...));
		$packet->passthrough(CommonTypes::getActorUniqueId(...));
		$packet->passthrough(CommonTypes::getVector3(...));
		$packet->passthrough(CommonTypes::getString(...));
		$packet->discardRemaining();
	}
}
