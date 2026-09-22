<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\VarInt;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\protocol\ProtocolException;
use function intdiv;
use function substr;

final readonly class ChunkTranslator
{
	private const int BLOCKS_PER_SUBCHUNK = 4096;

	private const int SUBCHUNK_VERSION_SINGLE_LAYER = 1;
	private const int SUBCHUNK_VERSION_LAYERED = 8;
	private const int SUBCHUNK_VERSION_INDEXED = 9;

	public function __construct(
		private BlockMapping $blocks
	) {
	}

	/**
	 * @throws DataDecodeException
	 * @throws ProtocolException
	 */
	public function translate(Direction $direction, string $payload, int $subChunkCount): string
	{
		$in = new ByteBufferReader($payload);
		$out = new ByteBufferWriter();

		for ($i = 0; $i < $subChunkCount; ++$i) {
			$this->translateSubChunk($direction, $in, $out);
		}

		$out->writeByteArray(substr($payload, $in->getOffset()));

		return $out->getData();
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
