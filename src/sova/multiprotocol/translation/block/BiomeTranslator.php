<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

final readonly class BiomeTranslator
{
	private const int MAX_CHAIN = 8;

	/**
	 * @param array<int, int> $replacements
	 */
	public function __construct(
		private array $replacements
	) {
	}

	public function isEmpty(): bool
	{
		return $this->replacements === [];
	}

	public function translate(int $biome): int
	{
		for ($i = 0; $i < self::MAX_CHAIN && isset($this->replacements[$biome]); ++$i) {
			$biome = $this->replacements[$biome];
		}

		return $biome;
	}
}
