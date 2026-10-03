<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\item\LegacyItemCodec;

/**
 * @extends TypedPacketRewriter<AddItemActorPacket>
 */
final class LegacyAddItemActorRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly LegacyItemCodec $items,
		int $codecProtocolId
	) {
		parent::__construct(AddItemActorPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$actor = $this->peek($packet);

		$out = new ByteBufferWriter();
		CommonTypes::putActorUniqueId($out, $actor->actorUniqueId);
		CommonTypes::putActorRuntimeId($out, $actor->actorRuntimeId);
		$this->items->write($out, $actor->item->getItemStack());
		CommonTypes::putVector3($out, $actor->position);
		CommonTypes::putVector3Nullable($out, $actor->motion);
		CommonTypes::putEntityMetadata($out, $this->codecProtocolId, $actor->metadata);
		CommonTypes::putBool($out, $actor->isFromFishing);
		$packet->replacePayload($out->getData());
	}
}
