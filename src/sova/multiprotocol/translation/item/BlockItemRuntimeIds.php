<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\item;

use Closure;
use InvalidArgumentException;
use JsonException;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\utils\Filesystem;
use function array_key_exists;
use function is_array;
use function is_int;
use function json_decode;
use function str_replace;
use const JSON_THROW_ON_ERROR;

final class BlockItemRuntimeIds
{
	private const array DATA_SUFFIXES = [
		ProtocolInfo::PROTOCOL_1_20_0 => '-1.20.0',
	];

	/** @var array<string, int|null> */
	private array $cache = [];

	/** @var (Closure(int, int): ?int)|null */
	private ?Closure $lookup = null;

	/**
	 * @param Closure(): (Closure(int, int): ?int) $factory
	 */
	public function __construct(
		private readonly Closure $factory
	) {
	}

	public static function native(int $protocolId): self
	{
		return new self(static function () use ($protocolId): Closure {
			$suffix = self::DATA_SUFFIXES[$protocolId] ?? '';
			$items = TypeConverter::getInstance($protocolId)->getItemTypeDictionary();
			$blockItems = new BlockItemIdMap(self::readJson(BedrockDataFiles::BLOCK_ID_TO_ITEM_ID_MAP_JSON, $suffix));
			$states = self::statesByIdMeta($suffix);

			return static function (int $id, int $meta) use ($items, $blockItems, $states): ?int {
				try {
					$blockId = $blockItems->lookupBlockId($items->fromIntId($id));
				} catch (InvalidArgumentException) {
					return null;
				}

				if ($blockId === null) {
					return null;
				}

				return $states[$blockId . ':' . $meta] ?? $states[$blockId . ':0'] ?? null;
			};
		});
	}

	public function resolve(ItemStack $stack): ItemStack
	{
		if ($stack->isNull() || $stack->getBlockRuntimeId() !== 0) {
			return $stack;
		}

		$key = $stack->getId() . ':' . $stack->getMeta();
		if (!array_key_exists($key, $this->cache)) {
			$this->lookup ??= ($this->factory)();
			$this->cache[$key] = ($this->lookup)($stack->getId(), $stack->getMeta());
		}

		$runtimeId = $this->cache[$key];

		return $runtimeId === null ? $stack : new ItemStack($stack->getId(), $stack->getMeta(), $stack->getCount(), $runtimeId, $stack->getRawExtraData());
	}

	/**
	 * @return array<string, int>
	 * @throws JsonException
	 */
	private static function statesByIdMeta(string $suffix): array
	{
		$palette = BlockStateDictionary::loadPaletteFromString(
			Filesystem::fileGetContents(str_replace('.nbt', $suffix . '.nbt', BedrockDataFiles::CANONICAL_BLOCK_STATES_NBT))
		);
		$metas = self::readJson(BedrockDataFiles::BLOCK_STATE_META_MAP_JSON, $suffix);

		$states = [];
		foreach ($palette as $runtimeId => $state) {
			$meta = $metas[$runtimeId] ?? null;
			if (is_int($meta)) {
				$states[$state->getName() . ':' . $meta] ??= $runtimeId;
			}
		}

		return $states;
	}

	/**
	 * @return array<mixed>
	 * @throws JsonException
	 */
	private static function readJson(string $file, string $suffix): array
	{
		$data = json_decode(Filesystem::fileGetContents(str_replace('.json', $suffix . '.json', $file)), true, flags: JSON_THROW_ON_ERROR);
		if (!is_array($data)) {
			throw new JsonException('Expected JSON array in ' . $file);
		}

		return $data;
	}
}
