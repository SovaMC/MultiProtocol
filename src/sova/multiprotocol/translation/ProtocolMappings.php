<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation;

use pocketmine\data\bedrock\item\downgrade\ItemIdMetaDowngrader;
use pocketmine\nbt\NbtDataException;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionaryFromDataHelper;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use sova\multiprotocol\translation\block\BlockMapping;
use sova\multiprotocol\translation\item\ItemMapping;
use sova\multiprotocol\translation\item\ItemTranslator as ProtocolItemTranslator;
use Symfony\Component\Filesystem\Path;

final readonly class ProtocolMappings
{
	public const string BLOCK_PALETTE_FILE = 'block_palette.nbt';
	public const string ITEM_TABLE_FILE = 'item_table.json';

	public function __construct(
		public BlockMapping $blocks,
		public ItemMapping $items,
		public ProtocolItemTranslator $itemTranslator
	) {
	}

	/**
	 * @param array<string, string> $itemRenames
	 * @throws NbtDataException
	 */
	public static function load(string $dataPath, int $serverProtocolId, int $clientItemSchemaId, array $itemRenames = []): self
	{
		$typeConverter = TypeConverter::getInstance($serverProtocolId);

        $serverStates = array_map(function ($entry) {
            return $entry->generateCurrentStateData();
        }, $typeConverter->getBlockTranslator()->getBlockStateDictionary()->getStates());

		$upgrader = GlobalBlockStateHandlers::getUpgrader()->getBlockStateUpgrader();
        $clientStates = array_map(function ($state) use ($upgrader) {
            return $upgrader->upgrade($state);
        }, BlockStateDictionary::loadPaletteFromString(Filesystem::fileGetContents(Path::join($dataPath, self::BLOCK_PALETTE_FILE))));

		$blocks = BlockMapping::build($serverStates, $clientStates);

		$serverItems = $typeConverter->getItemTypeDictionary();
		$clientItems = ItemTypeDictionaryFromDataHelper::loadFromString(Filesystem::fileGetContents(Path::join($dataPath, self::ITEM_TABLE_FILE)));
		$items = new ItemMapping(
			$serverItems,
			$clientItems,
			GlobalItemDataHandlers::getUpgrader()->getIdMetaUpgrader(),
			new ItemIdMetaDowngrader($clientItems, $clientItemSchemaId),
			new ItemIdMetaDowngrader($serverItems, ItemTranslator::getItemSchemaId($serverProtocolId)),
			$itemRenames
		);

		return new self($blocks, $items, new ProtocolItemTranslator($items, $blocks));
	}
}
