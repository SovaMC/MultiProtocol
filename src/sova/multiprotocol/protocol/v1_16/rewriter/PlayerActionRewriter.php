<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16\rewriter;

use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\input\PendingInputFlags;

/**
 * @extends TypedPacketRewriter<PlayerActionPacket>
 */
final class PlayerActionRewriter extends TypedPacketRewriter
{
	private const array INPUT_FLAGS = [
		PlayerAction::JUMP => PlayerAuthInputFlags::START_JUMPING,
		PlayerAction::START_SPRINT => PlayerAuthInputFlags::START_SPRINTING,
		PlayerAction::STOP_SPRINT => PlayerAuthInputFlags::STOP_SPRINTING,
		PlayerAction::START_SNEAK => PlayerAuthInputFlags::START_SNEAKING,
		PlayerAction::STOP_SNEAK => PlayerAuthInputFlags::STOP_SNEAKING,
		PlayerAction::START_GLIDE => PlayerAuthInputFlags::START_GLIDING,
		PlayerAction::STOP_GLIDE => PlayerAuthInputFlags::STOP_GLIDING,
		PlayerAction::START_SWIMMING => PlayerAuthInputFlags::START_SWIMMING,
		PlayerAction::STOP_SWIMMING => PlayerAuthInputFlags::STOP_SWIMMING,
	];

	public function __construct(int $codecProtocolId)
	{
		parent::__construct(PlayerActionPacket::class, $codecProtocolId, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$flag = self::INPUT_FLAGS[$this->peek($packet)->action] ?? null;
		if ($flag !== null) {
			$packet->session->get(PendingInputFlags::class)->add($flag);
			$packet->cancel();
		}
	}
}
