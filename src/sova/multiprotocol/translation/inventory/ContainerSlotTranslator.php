<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\inventory;

use pocketmine\network\mcpe\protocol\types\inventory\FullContainerName;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestSlotInfo;
use ReflectionClass;
use ReflectionProperty;
use sova\multiprotocol\packet\Direction;

final readonly class ContainerSlotTranslator
{
	public function __construct(
		private int $insertedContainerId
	) {
	}

	public function map(Direction $direction, int $containerId): int
	{
		if ($direction === Direction::SERVERBOUND) {
			return $containerId >= $this->insertedContainerId ? $containerId + 1 : $containerId;
		}

		return $containerId > $this->insertedContainerId ? $containerId - 1 : $containerId;
	}

	public function containerName(Direction $direction, FullContainerName $name): FullContainerName
	{
		return new FullContainerName($this->map($direction, $name->getContainerId()), $name->getDynamicId());
	}

	public function action(Direction $direction, ItemStackRequestAction $action): ItemStackRequestAction
	{
		$class = new ReflectionClass($action);
		do {
			foreach ($class->getProperties() as $property) {
				if ($property->isStatic()) {
					continue;
				}

				$value = $property->getValue($action);
				if ($value instanceof ItemStackRequestSlotInfo) {
					$this->replace($property, $action, new ItemStackRequestSlotInfo(
						$this->containerName($direction, $value->getContainerName()),
						$value->getSlotId(),
						$value->getStackId()
					));
				}
			}
		} while ($class = $class->getParentClass());

		return $action;
	}

	private function replace(ReflectionProperty $property, object $action, ItemStackRequestSlotInfo $value): void
	{
		$property->setValue($action, $value);
	}
}
