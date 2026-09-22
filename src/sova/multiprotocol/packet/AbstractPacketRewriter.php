<?php

declare(strict_types=1);

namespace sova\multiprotocol\packet;

use function array_values;

abstract class AbstractPacketRewriter implements PacketRewriter
{
	/** @var non-empty-list<Direction> */
	private readonly array $directions;

	public function __construct(
		private readonly int $packetId,
		Direction $direction,
		Direction ...$directions
	) {
		$this->directions = [$direction, ...array_values($directions)];
	}

	public function getPacketId(): int
	{
		return $this->packetId;
	}

	public function getDirections(): array
	{
		return $this->directions;
	}
}
