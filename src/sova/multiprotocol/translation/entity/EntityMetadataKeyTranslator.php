<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\entity;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;

final readonly class EntityMetadataKeyTranslator
{
	private const int LEGACY_HIGH_KEYS_START = 120;
	private const int LEGACY_BASE_RUNTIME_ID = 120;
	private const int LEGACY_BUOYANCY_DATA = 119;

	public function __construct(
		private bool $legacyHighKeys,
		private bool $withoutRiderRotation
	) {
	}

	/**
	 * @param array<int, MetadataProperty> $metadata
	 * @return array<int, MetadataProperty>
	 */
	public function toClient(array $metadata): array
	{
		$translated = [];
		foreach ($metadata as $key => $property) {
			$clientKey = $this->translate($key);
			if ($clientKey !== null && !isset($translated[$clientKey])) {
				$translated[$clientKey] = $property;
			}
		}

		return $translated;
	}

	private function translate(int $key): ?int
	{
		if ($this->legacyHighKeys && $key >= self::LEGACY_HIGH_KEYS_START) {
			$key = $key === EntityMetadataProperties::BASE_RUNTIME_ID ? self::LEGACY_BASE_RUNTIME_ID : $key + 1;
		}

		if (!$this->withoutRiderRotation) {
			return $key;
		}

		if ($key === EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET) {
			return null;
		}
		if ($key > EntityMetadataProperties::RIDER_SEAT_ROTATION_OFFSET) {
			--$key;
		}

		return $key === EntityMetadataProperties::BUOYANCY_DATA ? self::LEGACY_BUOYANCY_DATA : $key;
	}
}
