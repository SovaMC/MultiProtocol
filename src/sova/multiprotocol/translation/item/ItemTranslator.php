<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\item;

use InvalidArgumentException;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\protocol\ProtocolException;
use sova\multiprotocol\translation\block\BlockMapping;

final readonly class ItemTranslator
{
	private const string FALLBACK_ITEM = 'minecraft:info_update';

	private int $clientFallback;
	private int $serverFallback;

	public function __construct(
		private ItemMapping $items,
		private BlockMapping $blocks
	) {
		$this->clientFallback = self::resolveFallback($items->clientDictionary);
		$this->serverFallback = self::resolveFallback($items->serverDictionary);
	}

	public function stack(Direction $direction, ItemStack $stack): ItemStack
	{
		return $this->stackOrNull($direction, $stack) ?? new ItemStack(
			$this->fallback($direction),
			0,
			$stack->getCount(),
			0,
			$stack->getRawExtraData()
		);
	}

	public function stackOrNull(Direction $direction, ItemStack $stack): ?ItemStack
	{
		if ($stack->isNull()) {
			return $stack;
		}

		$mapped = $this->items->map($direction, $stack->getId(), $stack->getMeta());
		if ($mapped === null) {
			return null;
		}

		$blockRuntimeId = $stack->getBlockRuntimeId();

		return new ItemStack(
			$mapped[0],
			$mapped[1],
			$stack->getCount(),
			$blockRuntimeId !== 0 ? $this->blocks->map($direction, $blockRuntimeId) : 0,
			$stack->getRawExtraData()
		);
	}

	public function wrapper(Direction $direction, ItemStackWrapper $wrapper): ItemStackWrapper
	{
		return new ItemStackWrapper($wrapper->getStackId(), $this->stack($direction, $wrapper->getItemStack()), $wrapper->getStackIdVariant());
	}

	public function ingredient(Direction $direction, RecipeIngredient $ingredient): ?RecipeIngredient
	{
		$descriptor = $ingredient->getDescriptor();
		if (!$descriptor instanceof IntIdMetaItemDescriptor || $descriptor->getId() === 0) {
			return $ingredient;
		}

		$mapped = $this->idMeta($direction, $descriptor->getId(), $descriptor->getMeta());

		return $mapped === null ? null : new RecipeIngredient(new IntIdMetaItemDescriptor($mapped[0], $mapped[1]), $ingredient->getCount());
	}

	/**
	 * @return array{int, int}|null
	 */
	public function idMeta(Direction $direction, int $id, int $meta): ?array
	{
		return $this->items->map($direction, $id, $meta);
	}

	public function id(Direction $direction, int $id): ?int
	{
		return $this->items->map($direction, $id, 0)[0] ?? null;
	}

	public function fallback(Direction $direction): int
	{
		return $direction === Direction::CLIENTBOUND ? $this->clientFallback : $this->serverFallback;
	}

	private static function resolveFallback(ItemTypeDictionary $dictionary): int
	{
		foreach ([self::FALLBACK_ITEM, BlockTypeNames::BARRIER] as $name) {
			try {
				return $dictionary->fromStringId($name);
			} catch (InvalidArgumentException) {
				continue;
			}
		}

		throw new ProtocolException('Item dictionary has no fallback item');
	}
}
