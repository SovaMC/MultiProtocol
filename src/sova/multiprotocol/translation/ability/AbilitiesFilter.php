<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\ability;

use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use function array_diff_key;
use function array_fill_keys;
use function array_map;

final readonly class AbilitiesFilter
{
	/** @var array<int, true> */
	private array $removed;

	/**
	 * @param list<int> $removedAbilities
	 */
	public function __construct(array $removedAbilities)
	{
		$this->removed = array_fill_keys($removedAbilities, true);
	}

	public function filter(AbilitiesData $data): AbilitiesData
	{
		return new AbilitiesData(
			$data->getCommandPermission(),
			$data->getPlayerPermission(),
			$data->getTargetActorUniqueId(),
			array_map($this->filterLayer(...), $data->getAbilityLayers())
		);
	}

	private function filterLayer(AbilitiesLayer $layer): AbilitiesLayer
	{
		return new AbilitiesLayer(
			$layer->getLayerId(),
			array_diff_key($layer->getBoolAbilities(), $this->removed),
			$layer->getFlySpeed(),
			$layer->getVerticalFlySpeed(),
			$layer->getWalkSpeed()
		);
	}
}
