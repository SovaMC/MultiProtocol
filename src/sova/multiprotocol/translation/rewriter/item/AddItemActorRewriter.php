<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\item;

use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<AddItemActorPacket>
 */
final class AddItemActorRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(AddItemActorPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$actor = $this->decode($packet);
		$actor->item = $this->context->items->wrapper($packet->direction, $actor->item);
		if ($this->context->legacyItems !== null) {
			$out = new ByteBufferWriter();
			CommonTypes::putActorUniqueId($out, $actor->actorUniqueId);
			CommonTypes::putActorRuntimeId($out, $actor->actorRuntimeId);
			$this->context->legacyItems->write($out, $actor->item->getItemStack());
			CommonTypes::putVector3($out, $actor->position);
			CommonTypes::putVector3Nullable($out, $actor->motion);
			CommonTypes::putEntityMetadata($out, $this->context->codecProtocolId, $actor->metadata);
			CommonTypes::putBool($out, $actor->isFromFishing);
			$packet->replacePayload($out->getData());
		}
	}
}
