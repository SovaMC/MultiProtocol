<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\skin;

use Closure;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function count;
use function substr;

enum SkinLayout
{
	private const int PERSONA_FLAG_COUNT = 3;
	private const string DEFAULT_ENGINE_VERSION = '';
	private const string TRUE = "\x01";
	private const string EMPTY_STRING = "\x00";
	private const string NO_EXPRESSION = "\x00\x00\x00\x00";

	case LEGACY;
	case LEGACY_NO_PLAYFAB;
	case LEGACY_NO_PLAYFAB_NO_EXPRESSION;
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
	 * @return array{skinId: string, playFabId: string, resourcePatch: string, skinImage: string, animations: list<array{image: string, type: string, frames: string, expression: string}>, capeImage: string, geometry: string, engineVersion: string, animationData: string, tail: string, flags: string, primaryUser: string, override: string}
	 * @throws DataDecodeException
	 */
	private function read(ByteBufferReader $in): array
	{
		$data = $in->getData();

		$skinId = self::slice($in, $data, CommonTypes::getString(...));
		$playFabId = $this->hasPlayFabId() ? self::slice($in, $data, CommonTypes::getString(...)) : self::EMPTY_STRING;
		$resourcePatch = self::slice($in, $data, CommonTypes::getString(...));
		$skinImage = self::slice($in, $data, self::skipImage(...));
		$animations = [];
		for ($i = 0, $count = LE::readUnsignedInt($in); $i < $count; ++$i) {
			$animations[] = [
				'image' => self::slice($in, $data, self::skipImage(...)),
				'type' => self::slice($in, $data, LE::readUnsignedInt(...)),
				'frames' => self::slice($in, $data, LE::readFloat(...)),
				'expression' => $this->hasAnimationExpression() ? self::slice($in, $data, LE::readUnsignedInt(...)) : self::NO_EXPRESSION,
			];
		}
		$capeImage = self::slice($in, $data, self::skipImage(...));
		$geometry = self::slice($in, $data, CommonTypes::getString(...));

		$engineVersion = $this->isLegacy() ? null : self::slice($in, $data, CommonTypes::getString(...));
		$animationData = self::slice($in, $data, CommonTypes::getString(...));
		$flags = $this->isLegacy() ? $in->readByteArray(self::PERSONA_FLAG_COUNT) : null;

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
		$primaryUser = $this->isLegacy() ? self::TRUE : $in->readByteArray(1);
		$override = $this === self::MODERN ? $in->readByteArray(1) : self::TRUE;

		if ($engineVersion === null) {
			$writer = new ByteBufferWriter();
			CommonTypes::putString($writer, self::DEFAULT_ENGINE_VERSION);
			$engineVersion = $writer->getData();
		}

		return [
			'skinId' => $skinId,
			'playFabId' => $playFabId,
			'resourcePatch' => $resourcePatch,
			'skinImage' => $skinImage,
			'animations' => $animations,
			'capeImage' => $capeImage,
			'geometry' => $geometry,
			'engineVersion' => $engineVersion,
			'animationData' => $animationData,
			'tail' => $tail,
			'flags' => $flags,
			'primaryUser' => $primaryUser,
			'override' => $override,
		];
	}

	/**
	 * @param array{skinId: string, playFabId: string, resourcePatch: string, skinImage: string, animations: list<array{image: string, type: string, frames: string, expression: string}>, capeImage: string, geometry: string, engineVersion: string, animationData: string, tail: string, flags: string, primaryUser: string, override: string} $segments
	 */
	private function write(ByteBufferWriter $out, array $segments): void
	{
		$out->writeByteArray($segments['skinId']);
		if ($this->hasPlayFabId()) {
			$out->writeByteArray($segments['playFabId']);
		}
		$out->writeByteArray($segments['resourcePatch'] . $segments['skinImage']);
		LE::writeUnsignedInt($out, count($segments['animations']));
		foreach ($segments['animations'] as $animation) {
			$out->writeByteArray($animation['image'] . $animation['type'] . $animation['frames']);
			if ($this->hasAnimationExpression()) {
				$out->writeByteArray($animation['expression']);
			}
		}
		$out->writeByteArray($segments['capeImage'] . $segments['geometry']);
		if ($this->isLegacy()) {
			$out->writeByteArray($segments['animationData'] . $segments['flags'] . $segments['tail']);
			return;
		}

		$out->writeByteArray($segments['engineVersion'] . $segments['animationData'] . $segments['tail'] . $segments['flags'] . $segments['primaryUser']);
		if ($this === self::MODERN) {
			$out->writeByteArray($segments['override']);
		}
	}

	private function isLegacy(): bool
	{
		return match ($this) {
			self::LEGACY, self::LEGACY_NO_PLAYFAB, self::LEGACY_NO_PLAYFAB_NO_EXPRESSION => true,
			default => false,
		};
	}

	private function hasPlayFabId(): bool
	{
		return $this !== self::LEGACY_NO_PLAYFAB && $this !== self::LEGACY_NO_PLAYFAB_NO_EXPRESSION;
	}

	private function hasAnimationExpression(): bool
	{
		return $this !== self::LEGACY_NO_PLAYFAB_NO_EXPRESSION;
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
