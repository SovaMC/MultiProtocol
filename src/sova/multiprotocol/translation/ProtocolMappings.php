<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation;

use pocketmine\data\bedrock\item\downgrade\ItemIdMetaDowngrader;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use sova\multiprotocol\translation\block\BlockMapping;
use sova\multiprotocol\translation\item\ItemMapping;
use sova\multiprotocol\translation\item\ItemTranslator;

final readonly class ProtocolMappings
{
	public function __construct(
		public BlockMapping $blocks,
		public ItemMapping $items,
		public ItemTranslator $itemTranslator
	) {
	}

	/**
	 * @param array<string, string> $itemRenames
	 * @param array<string, string> $blockRenames
	 * @param array<string, string> $itemAliases
	 */
	public static function build(ProtocolData $server, ProtocolData $client, array $itemRenames = [], array $blockRenames = [], array $itemAliases = []): self
	{
		$blocks = BlockMapping::build($server->blockStates, $client->blockStates, $blockRenames);

		$items = new ItemMapping(
			$server->items,
			$client->items,
			GlobalItemDataHandlers::getUpgrader()->getIdMetaUpgrader(),
			new ItemIdMetaDowngrader($client->items, $client->itemSchemaId),
			new ItemIdMetaDowngrader($server->items, $server->itemSchemaId),
			$itemRenames,
			$itemAliases
		);

		return new self($blocks, $items, new ItemTranslator($items, $blocks));
	}
}
