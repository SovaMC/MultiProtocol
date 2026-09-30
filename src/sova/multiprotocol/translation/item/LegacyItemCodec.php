<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\item;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackExtraData;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackExtraDataShield;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use function count;

final readonly class LegacyItemCodec
{
	private const int NBT_PRESENT = -1;
	private const int NBT_DATA_VERSION = 1;
	private const int MAX_NBT_DEPTH = 512;
	private const int META_MASK = 0x7fff;
	private const int COUNT_MASK = 0xff;

	public function __construct(
		private int $shieldId
	) {
	}

	public function write(ByteBufferWriter $out, ItemStack $stack): void
	{
		if ($stack->isNull()) {
			VarInt::writeSignedInt($out, 0);
			return;
		}

		VarInt::writeSignedInt($out, $stack->getId());
		VarInt::writeSignedInt($out, (($stack->getMeta() & self::META_MASK) << 8) | ($stack->getCount() & self::COUNT_MASK));

		$extra = $this->readExtraData($stack);
		$nbt = $extra?->getNbt();
		if ($nbt !== null) {
			LE::writeSignedShort($out, self::NBT_PRESENT);
			Byte::writeUnsigned($out, self::NBT_DATA_VERSION);
			$out->writeByteArray((new NetworkNbtSerializer())->write(new TreeRoot($nbt)));
		} else {
			LE::writeSignedShort($out, 0);
		}

		foreach ([$extra?->getCanPlaceOn() ?? [], $extra?->getCanDestroy() ?? []] as $blocks) {
			VarInt::writeSignedInt($out, count($blocks));
			foreach ($blocks as $block) {
				CommonTypes::putString($out, $block);
			}
		}

		if ($stack->getId() === $this->shieldId) {
			VarInt::writeSignedLong($out, $extra instanceof ItemStackExtraDataShield ? $extra->getBlockingTick() : 0);
		}
	}

	public function writeWrapper(ByteBufferWriter $out, ItemStackWrapper $wrapper): void
	{
		VarInt::writeSignedInt($out, $wrapper->getStackId());
		$this->write($out, $wrapper->getItemStack());
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	public function read(ByteBufferReader $in): ItemStack
	{
		$id = VarInt::readSignedInt($in);
		if ($id === 0) {
			return ItemStack::null();
		}

		$aux = VarInt::readSignedInt($in);
		$meta = $aux >> 8;
		$count = $aux & self::COUNT_MASK;

		$nbt = null;
		$nbtLength = LE::readSignedShort($in);
		if ($nbtLength === self::NBT_PRESENT) {
			if (Byte::readUnsigned($in) !== self::NBT_DATA_VERSION) {
				throw new PacketDecodeException('Unexpected item NBT data version');
			}
			$offset = $in->getOffset();
			try {
				$nbt = (new NetworkNbtSerializer())->read($in->getData(), $offset, self::MAX_NBT_DEPTH)->mustGetCompoundTag();
			} catch (NbtDataException $e) {
				throw PacketDecodeException::wrap($e, 'Failed decoding item NBT');
			}
			$in->setOffset($offset);
		} elseif ($nbtLength !== 0) {
			throw new PacketDecodeException('Unexpected item NBT length ' . $nbtLength);
		}

		$canPlaceOn = self::readStrings($in);
		$canDestroy = self::readStrings($in);

		$extra = $id === $this->shieldId
			? new ItemStackExtraDataShield($nbt, $canPlaceOn, $canDestroy, VarInt::readSignedLong($in))
			: new ItemStackExtraData($nbt, $canPlaceOn, $canDestroy);

		$raw = new ByteBufferWriter();
		$extra->write($raw);

		return new ItemStack($id, $meta, $count, 0, $raw->getData());
	}

	private function readExtraData(ItemStack $stack): ?ItemStackExtraData
	{
		if ($stack->getRawExtraData() === '') {
			return null;
		}

		$in = new ByteBufferReader($stack->getRawExtraData());
		try {
			return $stack->getId() === $this->shieldId ? ItemStackExtraDataShield::read($in) : ItemStackExtraData::read($in);
		} catch (DataDecodeException|PacketDecodeException) {
			return null;
		}
	}

	/**
	 * @return list<string>
	 * @throws DataDecodeException
	 */
	private static function readStrings(ByteBufferReader $in): array
	{
		$strings = [];
		for ($i = 0, $count = VarInt::readSignedInt($in); $i < $count; ++$i) {
			$strings[] = CommonTypes::getString($in);
		}

		return $strings;
	}
}
