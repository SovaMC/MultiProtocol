<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\ParticleIds;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;
use function in_array;

/**
 * @extends TypedPacketRewriter<LevelEventPacket>
 */
final class LevelEventRewriter extends TypedPacketRewriter
{
	private const int PUNCH_FACE_SHIFT = 24;
	private const int PUNCH_RUNTIME_ID_MASK = 0xffffff;

	private const array BLOCK_EVENTS = [
		LevelEvent::PARTICLE_DESTROY,
		LevelEvent::PARTICLE_DESTROY_NO_SOUND,
		LevelEvent::PARTICLE_PUNCH_BLOCK_DOWN,
		LevelEvent::PARTICLE_PUNCH_BLOCK_UP,
		LevelEvent::PARTICLE_PUNCH_BLOCK_NORTH,
		LevelEvent::PARTICLE_PUNCH_BLOCK_SOUTH,
		LevelEvent::PARTICLE_PUNCH_BLOCK_WEST,
		LevelEvent::PARTICLE_PUNCH_BLOCK_EAST,
		LevelEvent::ADD_PARTICLE_MASK | ParticleIds::TERRAIN,
	];

	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(LevelEventPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$eventId = $this->peek($packet)->eventId;
		$blocks = $this->context->blocks;

		if ($eventId === LevelEvent::PARTICLE_PUNCH_BLOCK) {
			$event = $this->decode($packet);
			$face = $event->eventData >> self::PUNCH_FACE_SHIFT;
			$runtimeId = $event->eventData & self::PUNCH_RUNTIME_ID_MASK;
			$event->eventData = $blocks->map($packet->direction, $runtimeId) | ($face << self::PUNCH_FACE_SHIFT);
		} elseif (in_array($eventId, self::BLOCK_EVENTS, true)) {
			$event = $this->decode($packet);
			$event->eventData = $blocks->map($packet->direction, $event->eventData);
		}
	}
}
