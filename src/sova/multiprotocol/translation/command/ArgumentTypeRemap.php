<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\command;

final readonly class ArgumentTypeRemap
{
	public function __construct(
		private int $insertedFrom,
		private int $insertedCount,
		private int $replacement
	) {
	}

	public function translate(int $type): int
	{
		return match (true) {
			$type >= $this->insertedFrom + $this->insertedCount => $type - $this->insertedCount,
			$type >= $this->insertedFrom => $this->replacement,
			default => $type,
		};
	}
}
