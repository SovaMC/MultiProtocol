<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\block;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\block\BlockActorTranslator;

/**
 * @extends TypedPacketRewriter<BlockActorDataPacket>
 */
final class BlockActorDataRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly BlockActorTranslator $translator,
		int $codecProtocolId
	) {
		parent::__construct(BlockActorDataPacket::class, $codecProtocolId, Direction::CLIENTBOUND, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$data = $this->decode($packet);
		$nbt = $data->nbt->getRoot();

		if ($nbt instanceof CompoundTag) {
			$data->nbt = new CacheableNbt($this->translator->translate($packet->direction, $nbt));
		}
	}
}
