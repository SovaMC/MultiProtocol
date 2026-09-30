<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\skin;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

final class LegacySkinReader
{
	private const int PERSONA_FLAGS = 4;

	private function __construct()
	{
	}

	/**
	 * @throws DataDecodeException
	 */
	public static function skip(ByteBufferReader $in): void
	{
		CommonTypes::getString($in);
		CommonTypes::getString($in);
		CommonTypes::getString($in);
		self::skipImage($in);

		for ($i = 0, $count = LE::readUnsignedInt($in); $i < $count; ++$i) {
			self::skipImage($in);
			LE::readUnsignedInt($in);
			LE::readFloat($in);
			LE::readUnsignedInt($in);
		}

		self::skipImage($in);
		for ($i = 0; $i < 7; ++$i) {
			CommonTypes::getString($in);
		}

		for ($i = 0, $count = LE::readUnsignedInt($in); $i < $count; ++$i) {
			CommonTypes::getString($in);
			CommonTypes::getString($in);
			CommonTypes::getString($in);
			CommonTypes::getBool($in);
			CommonTypes::getString($in);
		}

		for ($i = 0, $count = LE::readUnsignedInt($in); $i < $count; ++$i) {
			CommonTypes::getString($in);
			for ($j = 0, $colors = LE::readUnsignedInt($in); $j < $colors; ++$j) {
				CommonTypes::getString($in);
			}
		}

		for ($i = 0; $i < self::PERSONA_FLAGS; ++$i) {
			CommonTypes::getBool($in);
		}
	}

	/**
	 * @throws DataDecodeException
	 */
	private static function skipImage(ByteBufferReader $in): void
	{
		LE::readUnsignedInt($in);
		LE::readUnsignedInt($in);
		CommonTypes::getString($in);
	}
}
