<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

use pocketmine\block\tile\Sign;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use sova\multiprotocol\packet\Direction;
use function in_array;

final class LegacySignTranslator implements BlockActorTranslator
{
	private const string ID_TAG = 'id';
	private const array SIGN_IDS = ['Sign', 'HangingSign'];

	private const array TEXT_TAGS = [
		Sign::TAG_TEXT_BLOB,
		Sign::TAG_TEXT_COLOR,
		Sign::TAG_GLOWING_TEXT,
		Sign::TAG_PERSIST_FORMATTING,
	];

	public function translate(Direction $direction, CompoundTag $nbt): CompoundTag
	{
		if (!in_array($nbt->getString(self::ID_TAG, ''), self::SIGN_IDS, true)) {
			return $nbt;
		}

		return $direction === Direction::CLIENTBOUND ? self::downgrade($nbt) : self::upgrade($nbt);
	}

	private static function downgrade(CompoundTag $nbt): CompoundTag
	{
		$front = $nbt->getCompoundTag(Sign::TAG_FRONT_TEXT);
		if ($front === null) {
			return $nbt;
		}

		foreach (self::TEXT_TAGS as $name) {
			$tag = $front->getTag($name);
			if ($tag !== null) {
				$nbt->setTag($name, $tag->safeClone());
			}
		}

		return $nbt->setByte(Sign::TAG_LEGACY_BUG_RESOLVE, 1);
	}

	private static function upgrade(CompoundTag $nbt): CompoundTag
	{
		if ($nbt->getCompoundTag(Sign::TAG_FRONT_TEXT) !== null || !$nbt->getTag(Sign::TAG_TEXT_BLOB) instanceof StringTag) {
			return $nbt;
		}

		$front = new CompoundTag();
		foreach (self::TEXT_TAGS as $name) {
			$tag = $nbt->getTag($name);
			if ($tag !== null) {
				$front->setTag($name, $tag->safeClone());
			}
		}

		return $nbt
			->setTag(Sign::TAG_FRONT_TEXT, $front)
			->setTag(Sign::TAG_BACK_TEXT, CompoundTag::create()->setString(Sign::TAG_TEXT_BLOB, ''));
	}
}
