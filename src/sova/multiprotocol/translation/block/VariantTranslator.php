<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;
use sova\multiprotocol\packet\Direction;

final readonly class VariantTranslator
{
	public function __construct(
		private BlockMapping $blocks
	) {
	}

	/**
	 * @param array<int, MetadataProperty> $metadata
	 * @return array<int, MetadataProperty>
	 */
	public function translate(Direction $direction, array $metadata): array
	{
		$variant = $metadata[EntityMetadataProperties::VARIANT] ?? null;
		if ($variant instanceof IntMetadataProperty) {
			$metadata[EntityMetadataProperties::VARIANT] = new IntMetadataProperty($this->blocks->map($direction, $variant->getValue()));
		}

		return $metadata;
	}
}
