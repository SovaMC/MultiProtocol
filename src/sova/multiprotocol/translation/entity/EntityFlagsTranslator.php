<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\entity;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use function array_filter;
use function count;
use function in_array;

final readonly class EntityFlagsTranslator
{
	private const int BITS_PER_FIELD = 64;

	/**
	 * @param list<int> $missingFlags
	 */
	public function __construct(
		private array $missingFlags
	) {
	}

	/**
	 * @param array<int, MetadataProperty> $metadata
	 * @return array<int, MetadataProperty>
	 */
	public function toClient(array $metadata): array
	{
		$flags = $metadata[EntityMetadataProperties::FLAGS] ?? null;
		$flags2 = $metadata[EntityMetadataProperties::FLAGS2] ?? null;
		if (!$flags instanceof LongMetadataProperty && !$flags2 instanceof LongMetadataProperty) {
			return $metadata;
		}

		$low = 0;
		$high = 0;
		foreach ([
			0 => $flags instanceof LongMetadataProperty ? $flags->getValue() : 0,
			self::BITS_PER_FIELD => $flags2 instanceof LongMetadataProperty ? $flags2->getValue() : 0,
		] as $offset => $value) {
			for ($bit = 0; $bit < self::BITS_PER_FIELD; ++$bit) {
				if ((($value >> $bit) & 1) === 0) {
					continue;
				}

				$translated = $this->translate($offset + $bit);
				if ($translated === null) {
					continue;
				}

				if ($translated < self::BITS_PER_FIELD) {
					$low |= 1 << $translated;
				} else {
					$high |= 1 << ($translated - self::BITS_PER_FIELD);
				}
			}
		}

		if ($flags instanceof LongMetadataProperty) {
			$metadata[EntityMetadataProperties::FLAGS] = new LongMetadataProperty($low);
		}
		if ($flags2 instanceof LongMetadataProperty || $high !== 0) {
			$metadata[EntityMetadataProperties::FLAGS2] = new LongMetadataProperty($high);
		}

		return $metadata;
	}

	private function translate(int $flag): ?int
	{
		if (in_array($flag, $this->missingFlags, true)) {
			return null;
		}

		$shift = count(array_filter($this->missingFlags, static fn(int $missing) => $missing < $flag));

		return $flag - $shift;
	}
}
