<?php

declare(strict_types=1);

namespace sova\multiprotocol\packet;

use Closure;

final class ClosurePacketRewriter extends AbstractPacketRewriter
{
	/**
	 * @param Closure(PacketWrapper): void $rewriter
	 */
	public function __construct(
		int $packetId,
		Direction $direction,
		private readonly Closure $rewriter
	) {
		parent::__construct($packetId, $direction);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		($this->rewriter)($packet);
	}
}
