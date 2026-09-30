<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\VarInt;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\protocol\ProtocolException;
use function chr;
use function count;
use function intdiv;
use function str_repeat;
use function strlen;
use function substr;
use function unpack;

final readonly class ChunkTranslator
{
	private const int BLOCKS_PER_SUBCHUNK = 4096;
	private const int COLUMN_SIZE = 16;

	private const int SUBCHUNK_VERSION_SINGLE_LAYER = 1;
	private const int SUBCHUNK_VERSION_LAYERED = 8;
	private const int SUBCHUNK_VERSION_INDEXED = 9;

	private const int BIOME_COPY_PREVIOUS = 0x7f;

	private const int LEGACY_MAX_SUBCHUNKS = 16;
	private const int LEGACY_SURFACE_SUBCHUNK = 4;
	private const int LEGACY_MIN_BITS_PER_BLOCK = 1;

	public function __construct(
		private BlockMapping $blocks,
		private BiomeTranslator $biomes = new BiomeTranslator([]),
		private bool $legacyHeight = false
	) {
	}

	/**
	 * @throws DataDecodeException
	 * @throws NbtDataException
	 * @throws ProtocolException
	 */
	public function translate(Direction $direction, string $payload, int $subChunkCount, ?BlockActorTranslator $blockActors = null, int $dimension = DimensionIds::OVERWORLD): TranslatedChunk
	{
		$in = new ByteBufferReader($payload);
		$out = new ByteBufferWriter();

		[$minSubChunk, $maxSubChunk] = self::dimensionBounds($dimension);
		$skipped = $this->legacyHeight ? -$minSubChunk : 0;
		$limit = $this->legacyHeight ? self::LEGACY_MAX_SUBCHUNKS : $subChunkCount;

		$written = 0;
		for ($i = 0; $i < $subChunkCount; ++$i) {
			if ($i < $skipped || $written >= $limit) {
				$this->translateSubChunk($direction, $in, new ByteBufferWriter());
				continue;
			}
			$this->translateSubChunk($direction, $in, $out);
			++$written;
		}

		$sections = $maxSubChunk - $minSubChunk + 1;
		if ($this->legacyHeight) {
			$out->writeByteArray($this->flattenBiomes($in, $sections, self::surfaceSection($minSubChunk)));
		} else {
			for ($i = 0; $i < $sections; ++$i) {
				$this->translateBiomes($in, $out);
			}
		}

		$borderBlocks = Byte::readUnsigned($in);
		Byte::writeUnsigned($out, $borderBlocks);
		$out->writeByteArray($in->readByteArray($borderBlocks));

		if ($blockActors !== null) {
			$this->translateBlockActors($direction, $payload, $in->getOffset(), $out, $blockActors);
		} else {
			$out->writeByteArray(substr($payload, $in->getOffset()));
		}

		return new TranslatedChunk($out->getData(), $written);
	}

	/**
	 * @throws DataDecodeException
	 * @throws ProtocolException
	 */
	public function translateSubChunk(Direction $direction, ByteBufferReader $in, ByteBufferWriter $out): void
	{
		$version = Byte::readUnsigned($in);

		if ($version === self::SUBCHUNK_VERSION_SINGLE_LAYER) {
			Byte::writeUnsigned($out, $version);
			$this->translateStorage($direction, $in, $out);
			return;
		}

		if ($version !== self::SUBCHUNK_VERSION_LAYERED && $version !== self::SUBCHUNK_VERSION_INDEXED) {
			throw new ProtocolException('Unsupported sub-chunk version ' . $version);
		}

		$layers = Byte::readUnsigned($in);
		$index = $version === self::SUBCHUNK_VERSION_INDEXED ? Byte::readUnsigned($in) : null;

		if ($this->legacyHeight || $index === null) {
			Byte::writeUnsigned($out, self::SUBCHUNK_VERSION_LAYERED);
			Byte::writeUnsigned($out, $layers);
		} else {
			Byte::writeUnsigned($out, $version);
			Byte::writeUnsigned($out, $layers);
			Byte::writeUnsigned($out, $index);
		}

		for ($layer = 0; $layer < $layers; ++$layer) {
			$this->translateStorage($direction, $in, $out);
		}
	}

	/**
	 * @throws NbtDataException
	 */
	private function translateBlockActors(Direction $direction, string $payload, int $offset, ByteBufferWriter $out, BlockActorTranslator $blockActors): void
	{
		$serializer = new NetworkNbtSerializer();
		$length = strlen($payload);

		while ($offset < $length) {
			$nbt = $serializer->read($payload, $offset)->mustGetCompoundTag();
			$out->writeByteArray($serializer->write(new TreeRoot($blockActors->translate($direction, $nbt))));
		}
	}

	/**
	 * @return array{int, int}
	 */
	private static function dimensionBounds(int $dimension): array
	{
		return ChunkSerializer::getDimensionChunkBounds(match ($dimension) {
			DimensionIds::NETHER => DimensionIds::NETHER,
			DimensionIds::THE_END => DimensionIds::THE_END,
			default => DimensionIds::OVERWORLD,
		});
	}

	private static function wordCount(int $bitsPerBlock): int
	{
		$blocksPerWord = intdiv(32, $bitsPerBlock);

		return intdiv(self::BLOCKS_PER_SUBCHUNK + $blocksPerWord - 1, $blocksPerWord);
	}

	private static function surfaceSection(int $minSubChunk): int
	{
		return $minSubChunk < 0 ? self::LEGACY_SURFACE_SUBCHUNK - $minSubChunk : 0;
	}

	/**
	 * @throws DataDecodeException
	 */
	private function translateBiomes(ByteBufferReader $in, ByteBufferWriter $out): void
	{
		$header = Byte::readUnsigned($in);
		Byte::writeUnsigned($out, $header);
		$section = self::readBiomePalette($in, $header >> 1);
		if ($section === null) {
			return;
		}

		[$bitsPerBlock, $words, $palette] = $section;
		$out->writeByteArray($words);
		if ($bitsPerBlock !== 0) {
			VarInt::writeSignedInt($out, count($palette));
		}
		foreach ($palette as $biome) {
			VarInt::writeSignedInt($out, $this->biomes->translate($biome));
		}
	}

	/**
	 * @throws DataDecodeException
	 */
	private function flattenBiomes(ByteBufferReader $in, int $sections, int $surface): string
	{
		$sample = null;
		$previous = null;
		for ($i = 0; $i < $sections; ++$i) {
			$section = self::readBiomes($in) ?? $previous;
			$previous = $section;
			if ($i === $surface || ($sample === null && $i > $surface)) {
				$sample = $section;
			}
		}

		$biomes = str_repeat("\x00", self::COLUMN_SIZE * self::COLUMN_SIZE);
		for ($x = 0; $x < self::COLUMN_SIZE; ++$x) {
			for ($z = 0; $z < self::COLUMN_SIZE; ++$z) {
				$biome = $sample === null ? 0 : self::biomeAt($sample, $x, $z);
				$biomes[($z << 4) | $x] = chr($this->biomes->translate($biome) & 0xff);
			}
		}

		return $biomes;
	}

	/**
	 * @return array{int, string, list<int>}|null
	 * @throws DataDecodeException
	 */
	private static function readBiomes(ByteBufferReader $in): ?array
	{
		return self::readBiomePalette($in, Byte::readUnsigned($in) >> 1);
	}

	/**
	 * @return array{int, string, list<int>}|null
	 * @throws DataDecodeException
	 */
	private static function readBiomePalette(ByteBufferReader $in, int $bitsPerBlock): ?array
	{
		if ($bitsPerBlock === self::BIOME_COPY_PREVIOUS) {
			return null;
		}

		$words = '';
		if ($bitsPerBlock !== 0) {
			$blocksPerWord = intdiv(32, $bitsPerBlock);
			$words = $in->readByteArray(intdiv(self::BLOCKS_PER_SUBCHUNK + $blocksPerWord - 1, $blocksPerWord) * 4);
		}

		$paletteSize = $bitsPerBlock !== 0 ? VarInt::readSignedInt($in) : 1;
		$palette = [];
		for ($i = 0; $i < $paletteSize; ++$i) {
			$palette[] = VarInt::readSignedInt($in);
		}

		return [$bitsPerBlock, $words, $palette];
	}

	/**
	 * @param array{int, string, list<int>} $section
	 */
	private static function biomeAt(array $section, int $x, int $z): int
	{
		[$bitsPerBlock, $words, $palette] = $section;
		if ($bitsPerBlock === 0) {
			return $palette[0] ?? 0;
		}

		$blocksPerWord = intdiv(32, $bitsPerBlock);
		$index = ($x << 8) | ($z << 4);
		$word = unpack('V', $words, intdiv($index, $blocksPerWord) * 4);
		$value = (($word === false ? 0 : (int) $word[1]) >> (($index % $blocksPerWord) * $bitsPerBlock)) & ((1 << $bitsPerBlock) - 1);

		return $palette[$value] ?? 0;
	}

	/**
	 * @throws DataDecodeException
	 * @throws ProtocolException
	 */
	private function translateStorage(Direction $direction, ByteBufferReader $in, ByteBufferWriter $out): void
	{
		$header = Byte::readUnsigned($in);

		if (($header & 0x01) === 0) {
			throw new ProtocolException('Persistent block storage is not supported');
		}

		$bitsPerBlock = $header >> 1;
		if ($bitsPerBlock === 0 && $this->legacyHeight) {
			Byte::writeUnsigned($out, (self::LEGACY_MIN_BITS_PER_BLOCK << 1) | 1);
			$out->writeByteArray(str_repeat("\x00", self::wordCount(self::LEGACY_MIN_BITS_PER_BLOCK) * 4));
			VarInt::writeSignedInt($out, 1);
			VarInt::writeSignedInt($out, $this->blocks->map($direction, VarInt::readSignedInt($in)));
			return;
		}

		Byte::writeUnsigned($out, $header);
		if ($bitsPerBlock !== 0) {
			$blocksPerWord = intdiv(32, $bitsPerBlock);
			$wordCount = intdiv(self::BLOCKS_PER_SUBCHUNK + $blocksPerWord - 1, $blocksPerWord);
			$out->writeByteArray($in->readByteArray($wordCount * 4));
		}

		$paletteSize = 1;
		if ($bitsPerBlock !== 0) {
			$paletteSize = VarInt::readSignedInt($in);
			VarInt::writeSignedInt($out, $paletteSize);
		}

		for ($i = 0; $i < $paletteSize; ++$i) {
			VarInt::writeSignedInt($out, $this->blocks->map($direction, VarInt::readSignedInt($in)));
		}
	}
}
