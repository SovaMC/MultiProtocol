<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\skin;

use Closure;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function substr;

enum SkinLayout
{
	private const int PERSONA_FLAG_COUNT = 3;
	private const string DEFAULT_ENGINE_VERSION = '';
	private const string TRUE = "\x01";

	case LEGACY;
	case NO_OVERRIDE;
	case MODERN;

	/**
	 * @throws DataDecodeException
	 */
	public function convert(ByteBufferReader $in, ByteBufferWriter $out, self $target): void
	{
		$segments = $this->read($in);
		$target->write($out, $segments);
	}

	/**
	 * @return array{head: string, engineVersion: string, animationData: string, tail: string, flags: string, primaryUser: string, override: string}
	 * @throws DataDecodeException
	 */
	private function read(ByteBufferReader $in): array
	{
		$data = $in->getData();

		$head = self::slice($in, $data, static function (ByteBufferReader $in): void {
			for ($i = 0; $i < 3; ++$i) {
				CommonTypes::getString($in);
			}
			self::skipImage($in);
			for ($i = 0, $count = LE::readUnsignedInt($in); $i < $count; ++$i) {
				self::skipImage($in);
				LE::readUnsignedInt($in);
				LE::readFloat($in);
				LE::readUnsignedInt($in);
			}
			self::skipImage($in);
			CommonTypes::getString($in);
		});

		$engineVersion = $this === self::LEGACY ? null : self::slice($in, $data, CommonTypes::getString(...));
		$animationData = self::slice($in, $data, CommonTypes::getString(...));
		$flags = $this === self::LEGACY ? $in->readByteArray(self::PERSONA_FLAG_COUNT) : null;

		$tail = self::slice($in, $data, static function (ByteBufferReader $in): void {
			for ($i = 0; $i < 4; ++$i) {
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
		});

		$flags ??= $in->readByteArray(self::PERSONA_FLAG_COUNT);
		$primaryUser = $this === self::LEGACY ? self::TRUE : $in->readByteArray(1);
		$override = $this === self::MODERN ? $in->readByteArray(1) : self::TRUE;

		if ($engineVersion === null) {
			$writer = new ByteBufferWriter();
			CommonTypes::putString($writer, self::DEFAULT_ENGINE_VERSION);
			$engineVersion = $writer->getData();
		}

		return [
			'head' => $head,
			'engineVersion' => $engineVersion,
			'animationData' => $animationData,
			'tail' => $tail,
			'flags' => $flags,
			'primaryUser' => $primaryUser,
			'override' => $override,
		];
	}

	/**
	 * @param array{head: string, engineVersion: string, animationData: string, tail: string, flags: string, primaryUser: string, override: string} $segments
	 */
	private function write(ByteBufferWriter $out, array $segments): void
	{
		$out->writeByteArray($segments['head']);
		if ($this === self::LEGACY) {
			$out->writeByteArray($segments['animationData'] . $segments['flags'] . $segments['tail']);
			return;
		}

		$out->writeByteArray($segments['engineVersion'] . $segments['animationData'] . $segments['tail'] . $segments['flags'] . $segments['primaryUser']);
		if ($this === self::MODERN) {
			$out->writeByteArray($segments['override']);
		}
	}

	/**
	 * @param Closure(ByteBufferReader): mixed $skip
	 * @throws DataDecodeException
	 */
	private static function slice(ByteBufferReader $in, string $data, Closure $skip): string
	{
		$start = $in->getOffset();
		$skip($in);

		return substr($data, $start, $in->getOffset() - $start);
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
