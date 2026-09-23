<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\recipe;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;

final class LegacyRecipeIngredient
{
	private function __construct()
	{
	}

	/**
	 * @throws DataDecodeException
	 */
	public static function read(ByteBufferReader $in): RecipeIngredient
	{
		$id = VarInt::readSignedInt($in);
		if ($id === 0) {
			return new RecipeIngredient(new IntIdMetaItemDescriptor(0, 0), 0);
		}

		$meta = VarInt::readSignedInt($in);
		$count = VarInt::readSignedInt($in);

		return new RecipeIngredient(new IntIdMetaItemDescriptor($id, $meta), $count);
	}

	public static function write(ByteBufferWriter $out, RecipeIngredient $ingredient): void
	{
		$descriptor = $ingredient->getDescriptor();
		if (!$descriptor instanceof IntIdMetaItemDescriptor || $descriptor->getId() === 0) {
			VarInt::writeSignedInt($out, 0);
			return;
		}

		VarInt::writeSignedInt($out, $descriptor->getId());
		VarInt::writeSignedInt($out, $descriptor->getMeta());
		VarInt::writeSignedInt($out, $ingredient->getCount());
	}
}
