<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\transport;

use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use sova\multiprotocol\network\compression\ZlibHeaderCompressor;

enum CompressionType
{
	case DEFLATE;
	case ZLIB;

	public function createCompressor(): Compressor
	{
		return match ($this) {
			self::DEFLATE => ZlibCompressor::getInstance(),
			self::ZLIB => ZlibHeaderCompressor::getInstance(),
		};
	}
}
