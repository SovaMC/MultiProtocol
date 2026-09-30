<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_17\rewriter;

use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;

/**
 * @extends TypedPacketRewriter<LevelEventPacket>
 */
final class ParticleRewriter extends TypedPacketRewriter
{
	private const int PARTICLE_ID_MASK = 0x3fff;
	private const int CANDLE_FLAME = 9;
	private const int LEGACY_MOB_FLAME = 17;

	public function __construct(int $codecProtocolId)
	{
		parent::__construct(LevelEventPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$eventId = $this->peek($packet)->eventId;
		if (($eventId & LevelEvent::ADD_PARTICLE_MASK) === 0) {
			return;
		}

		$particle = $eventId & self::PARTICLE_ID_MASK;
		if ($particle < self::CANDLE_FLAME) {
			return;
		}

		$translated = $particle === self::CANDLE_FLAME ? self::LEGACY_MOB_FLAME : $particle - 1;
		$this->decode($packet)->eventId = ($eventId & ~self::PARTICLE_ID_MASK) | $translated;
	}
}
