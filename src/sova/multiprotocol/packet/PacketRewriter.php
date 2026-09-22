<?php

declare(strict_types=1);

namespace sova\multiprotocol\packet;

use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use sova\multiprotocol\protocol\ProtocolException;

interface PacketRewriter
{
	public function getPacketId(): int;

	/**
	 * @return non-empty-list<Direction>
	 */
	public function getDirections(): array;

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	public function rewrite(PacketWrapper $packet): void;
}
