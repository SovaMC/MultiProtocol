<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

use pocketmine\network\mcpe\protocol\types\DimensionIds;

final class DimensionTracker
{
	private int $dimension = DimensionIds::OVERWORLD;

	public function getDimension(): int
	{
		return $this->dimension;
	}

	public function setDimension(int $dimension): void
	{
		$this->dimension = $dimension;
	}
}
