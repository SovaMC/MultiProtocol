<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;

final class CommandRequestRewriter extends AbstractPacketRewriter
{
	private const int COMMAND_VERSION = 0;

	public function __construct()
	{
		parent::__construct(ProtocolInfo::COMMAND_REQUEST_PACKET, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthroughAll();
		VarInt::writeSignedInt($packet->writer(), self::COMMAND_VERSION);
	}
}
