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
use function intdiv;
use function strlen;
use function substr;

final readonly class ChunkTranslator
{
	private const int BLOCKS_PER_SUBCHUNK = 4096;

	private const int SUBCHUNK_VERSION_SINGLE_LAYER = 1;
	private const int SUBCHUNK_VERSION_LAYERED = 8;
	private const int SUBCHUNK_VERSION_INDEXED = 9;

	private const int BIOME_COPY_PREVIOUS = 0x7f;

	public function __construct(
		private BlockMapping $blocks
	) {
	}

	/**
	 * @throws DataDecodeException
	 * @throws NbtDataException
	 * @throws ProtocolException
	 */
	public function translate(Direction $direction, string $payload, int $subChunkCount, ?BlockActorTranslator $blockActors = null, int $dimension = DimensionIds::OVERWORLD): string
	{
		$in = new ByteBufferReader($payload);
		$out = new ByteBufferWriter();

		for ($i = 0; $i < $subChunkCount; ++$i) {
			$this->translateSubChunk($direction, $in, $out);
		}

		if ($blockActors !== null) {
			$start = $in->getOffset();
			for ($i = 0, $count = self::biomeSectionCount($dimension); $i < $count; ++$i) {
				self::skipBiomePalette($in);
			}
			$in->readByteArray(Byte::readUnsigned($in));
			$out->writeByteArray(substr($payload, $start, $in->getOffset() - $start));

			$this->translateBlockActors($direction, $payload, $in->getOffset(), $out, $blockActors);

			return $out->getData();
		}

		$out->writeByteArray(substr($payload, $in->getOffset()));

		return $out->getData();
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

	private static function biomeSectionCount(int $dimension): int
	{
		[$minSubChunk, $maxSubChunk] = ChunkSerializer::getDimensionChunkBounds(match ($dimension) {
			DimensionIds::NETHER => DimensionIds::NETHER,
			DimensionIds::THE_END => DimensionIds::THE_END,
			default => DimensionIds::OVERWORLD,
		});

		return $maxSubChunk - $minSubChunk + 1;
	}

	/**
	 * @throws DataDecodeException
	 */
	private static function skipBiomePalette(ByteBufferReader $in): void
	{
		$bitsPerBlock = Byte::readUnsigned($in) >> 1;
		if ($bitsPerBlock === self::BIOME_COPY_PREVIOUS) {
			return;
		}

		if ($bitsPerBlock !== 0) {
			$blocksPerWord = intdiv(32, $bitsPerBlock);
			$in->readByteArray(intdiv(self::BLOCKS_PER_SUBCHUNK + $blocksPerWord - 1, $blocksPerWord) * 4);
		}

		$paletteSize = $bitsPerBlock !== 0 ? VarInt::readSignedInt($in) : 1;
		for ($i = 0; $i < $paletteSize; ++$i) {
			VarInt::readSignedInt($in);
		}
	}

	/**
	 * @throws DataDecodeException
	 * @throws ProtocolException
	 */
	public function translateSubChunk(Direction $direction, ByteBufferReader $in, ByteBufferWriter $out): void
	{
		$version = Byte::readUnsigned($in);
		Byte::writeUnsigned($out, $version);

		$layers = match ($version) {
			self::SUBCHUNK_VERSION_SINGLE_LAYER => 1,
			self::SUBCHUNK_VERSION_LAYERED => self::copyByte($in, $out),
			self::SUBCHUNK_VERSION_INDEXED => self::copyLayersWithIndex($in, $out),
			default => throw new ProtocolException('Unsupported sub-chunk version ' . $version),
		};

		for ($layer = 0; $layer < $layers; ++$layer) {
			$this->translateStorage($direction, $in, $out);
		}
	}

	/**
	 * @throws DataDecodeException
	 * @throws ProtocolException
	 */
	private function translateStorage(Direction $direction, ByteBufferReader $in, ByteBufferWriter $out): void
	{
		$header = Byte::readUnsigned($in);
		Byte::writeUnsigned($out, $header);

		if (($header & 0x01) === 0) {
			throw new ProtocolException('Persistent block storage is not supported');
		}

		$bitsPerBlock = $header >> 1;
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

	/**
	 * @throws DataDecodeException
	 */
	private static function copyByte(ByteBufferReader $in, ByteBufferWriter $out): int
	{
		$value = Byte::readUnsigned($in);
		Byte::writeUnsigned($out, $value);

		return $value;
	}

	/**
	 * @throws DataDecodeException
	 */
	private static function copyLayersWithIndex(ByteBufferReader $in, ByteBufferWriter $out): int
	{
		$layers = self::copyByte($in, $out);
		self::copyByte($in, $out);

		return $layers;
	}
}
