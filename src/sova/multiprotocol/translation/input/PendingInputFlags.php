<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\input;

use pocketmine\network\mcpe\protocol\serializer\BitSet;

final class PendingInputFlags
{
	/** @var array<int, true> */
	private array $flags = [];

	public function add(int $flag): void
	{
		$this->flags[$flag] = true;
	}

	public function apply(BitSet $flags): void
	{
		foreach ($this->flags as $flag => $_) {
			$flags->set($flag, true);
		}
		$this->flags = [];
	}
}
