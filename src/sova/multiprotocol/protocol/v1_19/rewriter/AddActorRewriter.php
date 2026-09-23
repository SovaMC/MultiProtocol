<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\protocol\v1_19_10\Protocol1_19_10;
use function count;

/**
 * @extends TypedPacketRewriter<AddActorPacket>
 */
final class AddActorRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly int $clientProtocolId,
		int $codecProtocolId
	) {
		parent::__construct(AddActorPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$actor = $this->peek($packet);

		$out = new ByteBufferWriter();
		CommonTypes::putActorUniqueId($out, $actor->actorUniqueId);
		CommonTypes::putActorRuntimeId($out, $actor->actorRuntimeId);
		CommonTypes::putString($out, $actor->type);
		CommonTypes::putVector3($out, $actor->position);
		CommonTypes::putVector3Nullable($out, $actor->motion);
		LE::writeFloat($out, $actor->pitch);
		LE::writeFloat($out, $actor->yaw);
		LE::writeFloat($out, $actor->headYaw);
		if ($this->clientProtocolId >= Protocol1_19_10::PROTOCOL) {
			LE::writeFloat($out, $actor->bodyYaw);
		}

		VarInt::writeUnsignedInt($out, count($actor->attributes));
		foreach ($actor->attributes as $attribute) {
			CommonTypes::putString($out, $attribute->getId());
			LE::writeFloat($out, $attribute->getMin());
			LE::writeFloat($out, $attribute->getCurrent());
			LE::writeFloat($out, $attribute->getMax());
		}

		CommonTypes::putEntityMetadata($out, $this->codecProtocolId, $actor->metadata);

		VarInt::writeUnsignedInt($out, count($actor->links));
		foreach ($actor->links as $link) {
			CommonTypes::putEntityLink($out, $this->codecProtocolId, $link);
		}

		$packet->replacePayload($out->getData());
	}
}
