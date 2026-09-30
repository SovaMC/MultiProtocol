<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\inventory;

use Closure;
use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use sova\multiprotocol\translation\item\LegacyItemCodec;
use function substr;

final readonly class LegacyTransactionReader
{
	private const int TYPE_NORMAL = 0;
	private const int TYPE_MISMATCH = 1;
	private const int TYPE_USE_ITEM = 2;
	private const int TYPE_USE_ITEM_ON_ENTITY = 3;
	private const int TYPE_RELEASE_ITEM = 4;

	private const int VECTOR3_LENGTH = 12;

	public function __construct(
		private LegacyItemCodec $items,
		private int $codecProtocolId,
		private bool $hasBlockRuntimeId
	) {
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	public function convertTransaction(ByteBufferReader $in, ByteBufferWriter $out): void
	{
		$this->convertRequest($in, $out);
		$type = self::copy($in, $out, VarInt::readUnsignedInt(...));
		$hasNetworkIds = CommonTypes::getBool($in);
		$this->convertActions($in, $out, $hasNetworkIds);

		match ($type) {
			self::TYPE_NORMAL, self::TYPE_MISMATCH => null,
			self::TYPE_USE_ITEM => $this->convertUseItem($in, $out),
			self::TYPE_USE_ITEM_ON_ENTITY => $this->convertUseItemOnEntity($in, $out),
			self::TYPE_RELEASE_ITEM => $this->convertReleaseItem($in, $out),
			default => throw new PacketDecodeException('Unknown transaction type ' . $type),
		};
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	public function convertItemInteraction(ByteBufferReader $in, ByteBufferWriter $out): void
	{
		$this->convertRequest($in, $out);
		$hasNetworkIds = CommonTypes::getBool($in);
		$this->convertActions($in, $out, $hasNetworkIds);
		$this->convertUseItem($in, $out);
	}

	/**
	 * @throws DataDecodeException
	 */
	private function convertRequest(ByteBufferReader $in, ByteBufferWriter $out): void
	{
		$requestId = self::copy($in, $out, VarInt::readSignedInt(...));
		if ($requestId === 0) {
			return;
		}

		for ($i = 0, $count = self::copy($in, $out, VarInt::readUnsignedInt(...)); $i < $count; ++$i) {
			self::copy($in, $out, Byte::readUnsigned(...));
			$slots = self::copy($in, $out, VarInt::readUnsignedInt(...));
			$out->writeByteArray($in->readByteArray($slots));
		}
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	private function convertActions(ByteBufferReader $in, ByteBufferWriter $out, bool $hasNetworkIds): void
	{
		for ($i = 0, $count = self::copy($in, $out, VarInt::readUnsignedInt(...)); $i < $count; ++$i) {
			$sourceType = self::copy($in, $out, VarInt::readUnsignedInt(...));
			match ($sourceType) {
				NetworkInventoryAction::SOURCE_CONTAINER, NetworkInventoryAction::SOURCE_TODO => self::copy($in, $out, VarInt::readSignedInt(...)),
				NetworkInventoryAction::SOURCE_WORLD => self::copy($in, $out, VarInt::readUnsignedInt(...)),
				NetworkInventoryAction::SOURCE_CREATIVE => null,
				default => throw new PacketDecodeException('Unknown inventory action source type ' . $sourceType),
			};

			self::copy($in, $out, VarInt::readUnsignedInt(...));
			$this->convertItem($in, $out);
			$this->convertItem($in, $out);
			if ($hasNetworkIds) {
				VarInt::readSignedInt($in);
			}
		}
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	private function convertUseItem(ByteBufferReader $in, ByteBufferWriter $out): void
	{
		self::copy($in, $out, VarInt::readUnsignedInt(...));
		self::copy($in, $out, static fn(ByteBufferReader $in) => CommonTypes::getBlockPosition($in, false));
		self::copy($in, $out, VarInt::readSignedInt(...));
		self::copy($in, $out, VarInt::readSignedInt(...));
		$this->convertItem($in, $out);
		$out->writeByteArray($in->readByteArray(self::VECTOR3_LENGTH * 2));

		if ($this->hasBlockRuntimeId) {
			self::copy($in, $out, VarInt::readUnsignedInt(...));
		} else {
			VarInt::writeUnsignedInt($out, 0);
		}
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	private function convertUseItemOnEntity(ByteBufferReader $in, ByteBufferWriter $out): void
	{
		self::copy($in, $out, CommonTypes::getActorRuntimeId(...));
		self::copy($in, $out, VarInt::readUnsignedInt(...));
		self::copy($in, $out, VarInt::readSignedInt(...));
		$this->convertItem($in, $out);
		$out->writeByteArray($in->readByteArray(self::VECTOR3_LENGTH * 2));
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	private function convertReleaseItem(ByteBufferReader $in, ByteBufferWriter $out): void
	{
		self::copy($in, $out, VarInt::readUnsignedInt(...));
		self::copy($in, $out, VarInt::readSignedInt(...));
		$this->convertItem($in, $out);
		$out->writeByteArray($in->readByteArray(self::VECTOR3_LENGTH));
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	private function convertItem(ByteBufferReader $in, ByteBufferWriter $out): void
	{
		CommonTypes::putItemStackWrapper($out, $this->codecProtocolId, new ItemStackWrapper(0, $this->items->read($in)), false);
	}

	/**
	 * @template T
	 * @param Closure(ByteBufferReader): T $read
	 */
	private static function copy(ByteBufferReader $in, ByteBufferWriter $out, Closure $read): mixed
	{
		$start = $in->getOffset();
		$value = $read($in);
		$out->writeByteArray(substr($in->getData(), $start, $in->getOffset() - $start));

		return $value;
	}
}
