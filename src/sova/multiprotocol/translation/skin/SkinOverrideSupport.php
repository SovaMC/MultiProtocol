<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\skin;

final class SkinOverrideSupport
{
	private bool $supported = false;

	public function isSupported(): bool
	{
		return $this->supported;
	}

	public function setSupported(bool $supported): void
	{
		$this->supported = $supported;
	}
}
