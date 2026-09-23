<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\NbtDataException;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionaryFromDataHelper;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use Symfony\Component\Filesystem\Path;
use function array_map;

final readonly class ProtocolData
{
	public const string BLOCK_PALETTE_FILE = 'block_palette.nbt';
	public const string ITEM_TABLE_FILE = 'item_table.json';

	/**
	 * @param array<int, BlockStateData> $blockStates
	 */
	public function __construct(
		public array $blockStates,
		public ItemTypeDictionary $items,
		public int $itemSchemaId
	) {
	}

	public static function native(int $protocolId): self
	{
		$typeConverter = TypeConverter::getInstance($protocolId);

		return new self(
			array_map(
				static fn($entry) => $entry->generateCurrentStateData(),
				$typeConverter->getBlockTranslator()->getBlockStateDictionary()->getStates()
			),
			$typeConverter->getItemTypeDictionary(),
			ItemTranslator::getItemSchemaId($protocolId)
		);
	}

	/**
	 * @throws NbtDataException
	 */
	public static function load(string $dataPath, int $itemSchemaId): self
	{
		$upgrader = GlobalBlockStateHandlers::getUpgrader()->getBlockStateUpgrader();

		return new self(
			array_map(
				static fn(BlockStateData $state) => $upgrader->upgrade($state),
				BlockStateDictionary::loadPaletteFromString(Filesystem::fileGetContents(Path::join($dataPath, self::BLOCK_PALETTE_FILE)))
			),
			ItemTypeDictionaryFromDataHelper::loadFromString(Filesystem::fileGetContents(Path::join($dataPath, self::ITEM_TABLE_FILE))),
			$itemSchemaId
		);
	}
}
