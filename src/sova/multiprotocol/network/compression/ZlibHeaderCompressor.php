<?php

declare(strict_types=1);

namespace sova\multiprotocol\network\compression;

use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\compression\DecompressionException;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use pocketmine\utils\Utils;
use function strlen;
use function zlib_decode;
use function zlib_encode;
use const ZLIB_ENCODING_DEFLATE;

final class ZlibHeaderCompressor implements Compressor
{
	private static ?self $instance = null;

	public static function getInstance(): self
	{
		return self::$instance ??= new self(
			ZlibCompressor::DEFAULT_LEVEL,
			ZlibCompressor::DEFAULT_THRESHOLD,
			ZlibCompressor::DEFAULT_MAX_DECOMPRESSION_SIZE
		);
	}

	public function __construct(
		private readonly int $level,
		private readonly ?int $minCompressionSize,
		private readonly int $maxDecompressionSize
	) {
	}

	public function getCompressionThreshold(): ?int
	{
		return $this->minCompressionSize;
	}

	public function decompress(string $payload): string
	{
		$result = @zlib_decode($payload, $this->maxDecompressionSize);
		if ($result === false) {
			throw new DecompressionException("Failed to decompress data");
		}

		return $result;
	}

	public function compress(string $payload): string
	{
		$compressible = $this->minCompressionSize !== null && strlen($payload) >= $this->minCompressionSize;

		return Utils::assumeNotFalse(
			zlib_encode($payload, ZLIB_ENCODING_DEFLATE, $compressible ? $this->level : 0),
			"ZLIB compression failed"
		);
	}

	public function getNetworkId(): int
	{
		return CompressionAlgorithm::ZLIB;
	}
}
