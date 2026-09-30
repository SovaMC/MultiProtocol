<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use function count;

/**
 * @extends TypedPacketRewriter<UpdateAttributesPacket>
 */
final class UpdateAttributesRewriter extends TypedPacketRewriter
{
	public function __construct(int $codecProtocolId, private readonly bool $hasTick = true)
	{
		parent::__construct(UpdateAttributesPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$attributes = $this->peek($packet);

		$out = new ByteBufferWriter();
		CommonTypes::putActorRuntimeId($out, $attributes->actorRuntimeId);
		VarInt::writeUnsignedInt($out, count($attributes->entries));
		foreach ($attributes->entries as $entry) {
			LE::writeFloat($out, $entry->getMin());
			LE::writeFloat($out, $entry->getMax());
			LE::writeFloat($out, $entry->getCurrent());
			LE::writeFloat($out, $entry->getDefault());
			CommonTypes::putString($out, $entry->getId());
		}
		if ($this->hasTick) {
			VarInt::writeUnsignedLong($out, $attributes->tick);
		}

		$packet->replacePayload($out->getData());
	}
}
