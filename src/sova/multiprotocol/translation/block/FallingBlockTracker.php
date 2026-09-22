<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

final class FallingBlockTracker
{
	/** @var array<int, true> */
	private array $actors = [];

	public function track(int $actorRuntimeId): void
	{
		$this->actors[$actorRuntimeId] = true;
	}

	public function untrack(int $actorUniqueId): void
	{
		unset($this->actors[$actorUniqueId]);
	}

	public function isTracked(int $actorRuntimeId): bool
	{
		return isset($this->actors[$actorRuntimeId]);
	}
}
