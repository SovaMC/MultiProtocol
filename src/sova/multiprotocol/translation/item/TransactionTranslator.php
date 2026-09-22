<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\item;

use pocketmine\network\mcpe\protocol\types\inventory\ReleaseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\TransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\translation\block\BlockMapping;
use sova\multiprotocol\utils\Reflection;

final readonly class TransactionTranslator
{
	public function __construct(
		private ItemTranslator $items,
		private BlockMapping $blocks
	) {
	}

	public function translate(Direction $direction, TransactionData $data): void
	{
		foreach ($data->getActions() as $action) {
			$action->oldItem = $this->items->wrapper($direction, $action->oldItem);
			$action->newItem = $this->items->wrapper($direction, $action->newItem);
		}

		if ($data instanceof UseItemTransactionData) {
			Reflection::set(UseItemTransactionData::class, $data, 'itemInHand', $this->items->wrapper($direction, $data->getItemInHand()));
			Reflection::set(UseItemTransactionData::class, $data, 'blockRuntimeId', $this->blocks->map($direction, $data->getBlockRuntimeId()));
		} elseif ($data instanceof UseItemOnEntityTransactionData) {
			Reflection::set(UseItemOnEntityTransactionData::class, $data, 'itemInHand', $this->items->wrapper($direction, $data->getItemInHand()));
		} elseif ($data instanceof ReleaseItemTransactionData) {
			Reflection::set(ReleaseItemTransactionData::class, $data, 'itemInHand', $this->items->wrapper($direction, $data->getItemInHand()));
		}
	}
}
