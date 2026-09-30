<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;

/**
 * @extends TypedPacketRewriter<SetActorDataPacket>
 */
final class SetActorDataRewriter extends TypedPacketRewriter
{
	public function __construct(int $codecProtocolId, private readonly bool $hasTick = true)
	{
		parent::__construct(SetActorDataPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$data = $this->peek($packet);

		$out = new ByteBufferWriter();
		CommonTypes::putActorRuntimeId($out, $data->actorRuntimeId);
		CommonTypes::putEntityMetadata($out, $this->codecProtocolId, $data->metadata);
		if ($this->hasTick) {
			VarInt::writeUnsignedLong($out, $data->tick);
		}

		$packet->replacePayload($out->getData());
	}
}
