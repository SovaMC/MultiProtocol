<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\session\DeferredPackets;
use function in_array;

/**
 * @extends TypedPacketRewriter<BlockActorDataPacket>
 */
final class SignEditRewriter extends TypedPacketRewriter
{
	private const string ID_TAG = 'id';
	private const array SIGN_IDS = ['Sign', 'HangingSign'];

	public function __construct(int $codecProtocolId)
	{
		parent::__construct(BlockActorDataPacket::class, $codecProtocolId, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$data = $this->peek($packet);
		$nbt = $data->nbt->getRoot();
		if (!$nbt instanceof CompoundTag || !in_array($nbt->getString(self::ID_TAG, ''), self::SIGN_IDS, true)) {
			return;
		}

		$position = $data->blockPosition;
		$payload = $packet->getPayload();
		$packet->session->get(DeferredPackets::class)->defer(
			self::class . ':' . $position->getX() . ':' . $position->getY() . ':' . $position->getZ(),
			$packet->getHeader()->encode() . $payload
		);
		$packet->cancel();
	}
}
