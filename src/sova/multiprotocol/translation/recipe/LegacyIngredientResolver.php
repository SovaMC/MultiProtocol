<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\recipe;

use InvalidArgumentException;
use pocketmine\data\bedrock\ItemTagToIdMap;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;
use pocketmine\network\mcpe\protocol\types\recipe\StringIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\TagItemDescriptor;
use sova\multiprotocol\translation\item\ItemMapping;

final class LegacyIngredientResolver
{
	private const int WILDCARD_META = 0x7fff;

	/** @var array<string, IntIdMetaItemDescriptor|null> */
	private array $tags = [];

	public function __construct(
		private readonly ItemMapping $items,
		private readonly int $serverProtocolId
	) {
	}

	public function resolve(RecipeIngredient $ingredient): RecipeIngredient
	{
		$descriptor = $ingredient->getDescriptor();

		$resolved = match (true) {
			$descriptor instanceof IntIdMetaItemDescriptor => $descriptor,
			$descriptor instanceof StringIdMetaItemDescriptor => $this->stringId($descriptor->getId(), $descriptor->getMeta()),
			$descriptor instanceof TagItemDescriptor => $this->tags[$descriptor->getTag()] ??= $this->tag($descriptor->getTag()),
			default => null,
		};

		return new RecipeIngredient($resolved, $resolved === null ? 0 : $ingredient->getCount());
	}

	private function stringId(string $stringId, int $meta): ?IntIdMetaItemDescriptor
	{
		try {
			return new IntIdMetaItemDescriptor($this->items->clientDictionary->fromStringId($stringId), $meta);
		} catch (InvalidArgumentException) {
			return null;
		}
	}

	private function tag(string $tag): ?IntIdMetaItemDescriptor
	{
		foreach (ItemTagToIdMap::getInstance($this->serverProtocolId)->getIdsForTag($tag) as $stringId) {
			try {
				$serverId = $this->items->serverDictionary->fromStringId($stringId);
			} catch (InvalidArgumentException) {
				continue;
			}

			$client = $this->items->toClient($serverId, 0);
			if ($client !== null) {
				return new IntIdMetaItemDescriptor($client[0], self::WILDCARD_META);
			}
		}

		return null;
	}
}
