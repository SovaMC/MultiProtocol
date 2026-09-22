<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;
use function in_array;

/**
 * @extends TypedPacketRewriter<LevelSoundEventPacket>
 */
final class LevelSoundEventRewriter extends TypedPacketRewriter
{
	private const array BLOCK_SOUNDS = [
		LevelSoundEvent::ITEM_USE_ON,
		LevelSoundEvent::HIT,
		LevelSoundEvent::STEP,
		LevelSoundEvent::HEAVY_STEP,
		LevelSoundEvent::BREAK,
		LevelSoundEvent::BREAK_BLOCK,
		LevelSoundEvent::PLACE,
		LevelSoundEvent::LAND,
		LevelSoundEvent::POWER_ON,
		LevelSoundEvent::POWER_OFF,
		LevelSoundEvent::INSERT,
		LevelSoundEvent::INSERT_ENCHANTED,
		LevelSoundEvent::PICKUP,
		LevelSoundEvent::PICKUP_ENCHANTED,
	];

	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(LevelSoundEventPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$sound = $this->peek($packet);
		if ($sound->extraData < 0 || !in_array($sound->sound, self::BLOCK_SOUNDS, true)) {
			return;
		}

		$sound = $this->decode($packet);
		$sound->extraData = $this->context->blocks->map($packet->direction, $sound->extraData);
	}
}
