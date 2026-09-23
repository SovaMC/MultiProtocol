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

final class ProtocolData
{
	public const string BLOCK_PALETTE_FILE = 'block_palette.nbt';
	public const string ITEM_TABLE_FILE = 'item_table.json';
	public const string ACTOR_IDENTIFIERS_FILE = 'entity_identifiers.nbt';

	/** @var array<string, array<int, BlockStateData>> */
	private static array $blockPalettes = [];

	/** @var array<string, ItemTypeDictionary> */
	private static array $itemTables = [];

	/**
	 * @param array<int, BlockStateData> $blockStates
	 */
	public function __construct(
		public readonly array $blockStates,
		public readonly ItemTypeDictionary $items,
		public readonly int $itemSchemaId
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
		return self::fromFiles(
			Path::join($dataPath, self::BLOCK_PALETTE_FILE),
			Path::join($dataPath, self::ITEM_TABLE_FILE),
			$itemSchemaId
		);
	}

	/**
	 * @throws NbtDataException
	 */
	public static function fromFiles(string $blockPaletteFile, string $itemTableFile, int $itemSchemaId): self
	{
		return new self(
			self::$blockPalettes[$blockPaletteFile] ??= self::loadBlockPalette($blockPaletteFile),
			self::$itemTables[$itemTableFile] ??= ItemTypeDictionaryFromDataHelper::loadFromString(Filesystem::fileGetContents($itemTableFile)),
			$itemSchemaId
		);
	}

	/**
	 * @return array<int, BlockStateData>
	 * @throws NbtDataException
	 */
	private static function loadBlockPalette(string $file): array
	{
		$upgrader = GlobalBlockStateHandlers::getUpgrader()->getBlockStateUpgrader();

		return array_map(
			static fn(BlockStateData $state) => $upgrader->upgrade($state),
			BlockStateDictionary::loadPaletteFromString(Filesystem::fileGetContents($file))
		);
	}
}
