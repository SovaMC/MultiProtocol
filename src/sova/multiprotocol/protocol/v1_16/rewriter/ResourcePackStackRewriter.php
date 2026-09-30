<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use function count;

/**
 * @extends TypedPacketRewriter<ResourcePackStackPacket>
 */
final class ResourcePackStackRewriter extends TypedPacketRewriter
{
	public function __construct(int $codecProtocolId)
	{
		parent::__construct(ResourcePackStackPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$stack = $this->peek($packet);
		$out = new ByteBufferWriter();
		CommonTypes::putBool($out, $stack->mustAccept);
		VarInt::writeUnsignedInt($out, count($stack->behaviorPackStack));
		foreach ($stack->behaviorPackStack as $entry) {
			$entry->write($out);
		}
		VarInt::writeUnsignedInt($out, count($stack->resourcePackStack));
		foreach ($stack->resourcePackStack as $entry) {
			$entry->write($out);
		}
		CommonTypes::putBool($out, false);
		CommonTypes::putString($out, $stack->baseGameVersion);
		$packet->replacePayload($out->getData());
	}
}
