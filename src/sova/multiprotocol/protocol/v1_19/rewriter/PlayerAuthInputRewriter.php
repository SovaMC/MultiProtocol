<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\BitSet;
use pocketmine\network\mcpe\protocol\types\InteractionMode;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\ItemInteractionData;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerBlockAction;
use pocketmine\network\mcpe\protocol\types\PlayMode;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\translation\input\PendingInputFlags;
use sova\multiprotocol\translation\inventory\LegacyItemStackRequestReader;
use sova\multiprotocol\translation\inventory\LegacyTransactionReader;
use function str_repeat;

final class PlayerAuthInputRewriter extends AbstractPacketRewriter
{
	private const int MOVEMENT_LENGTH = 32;
	private const int VECTOR3_LENGTH = 12;
	private const int INPUT_FLAGS_LENGTH = 64;

	public function __construct(
		private readonly int $codecProtocolId,
		private readonly ?LegacyItemStackRequestReader $requests,
		private readonly bool $hasInteractionMode,
		private readonly bool $hasTick = true,
		private readonly bool $hasActions = true,
		private readonly ?LegacyTransactionReader $transactions = null
	) {
		parent::__construct(ProtocolInfo::PLAYER_AUTH_INPUT_PACKET, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthroughBytes(self::MOVEMENT_LENGTH);
		$flags = BitSet::read($packet->reader(), self::INPUT_FLAGS_LENGTH);
		if (!$this->hasActions) {
			$packet->session->get(PendingInputFlags::class)->apply($flags);
		}
		$flags->write($packet->writer());
		$packet->passthrough(VarInt::readUnsignedInt(...));
		$playMode = $packet->passthrough(VarInt::readUnsignedInt(...));
		if ($this->hasInteractionMode) {
			$packet->passthrough(VarInt::readUnsignedInt(...));
		} else {
			VarInt::writeUnsignedInt($packet->writer(), InteractionMode::TOUCH);
		}
		if ($playMode === PlayMode::VR) {
			$packet->passthroughBytes(self::VECTOR3_LENGTH);
		}
		if ($this->hasTick) {
			$packet->passthrough(VarInt::readUnsignedLong(...));
			$packet->passthroughBytes(self::VECTOR3_LENGTH);
		} else {
			VarInt::writeUnsignedLong($packet->writer(), 0);
			$packet->writer()->writeByteArray(str_repeat("\x00", self::VECTOR3_LENGTH));
		}

		if ($this->hasActions && $flags->get(PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION)) {
			if ($this->transactions !== null) {
				$this->transactions->convertItemInteraction($packet->reader(), $packet->writer());
			} else {
				$packet->passthrough(fn(ByteBufferReader $in) => ItemInteractionData::read($in, $this->codecProtocolId));
			}
		}

		if ($this->hasActions && $flags->get(PlayerAuthInputFlags::PERFORM_ITEM_STACK_REQUEST)) {
			if ($this->requests !== null) {
				$this->requests->read($packet->reader())->write($packet->writer(), $this->codecProtocolId);
			} else {
				$packet->passthrough(fn(ByteBufferReader $in) => ItemStackRequest::read($in, $this->codecProtocolId));
			}
		}

		if ($this->hasActions && $flags->get(PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS)) {
			$count = $packet->passthrough(VarInt::readSignedInt(...));
			for ($i = 0; $i < $count; ++$i) {
				$packet->passthrough(fn(ByteBufferReader $in) => PlayerBlockAction::read($in, $this->codecProtocolId));
			}
		}

		$packet->passthroughAll();
		LE::writeFloat($packet->writer(), 0.0);
		LE::writeFloat($packet->writer(), 0.0);
	}
}
