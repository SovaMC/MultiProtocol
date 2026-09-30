<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\world\LegacyGameRules;

/**
 * @extends TypedPacketRewriter<GameRulesChangedPacket>
 */
final class GameRulesChangedRewriter extends TypedPacketRewriter
{
	public function __construct(int $codecProtocolId)
	{
		parent::__construct(GameRulesChangedPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$out = new ByteBufferWriter();
		LegacyGameRules::write($out, $this->peek($packet)->gameRules, $this->codecProtocolId, false);
		$packet->replacePayload($out->getData());
	}
}
