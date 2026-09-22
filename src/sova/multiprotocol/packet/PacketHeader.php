<?php

declare(strict_types=1);

namespace sova\multiprotocol\packet;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\DataPacket;

final class PacketHeader
{
	private const int SUBCLIENT_ID_MASK = 0x03;
	private const int SENDER_SUBCLIENT_ID_SHIFT = 10;
	private const int RECIPIENT_SUBCLIENT_ID_SHIFT = 12;

	public function __construct(
		public int $id,
		public int $senderSubId = 0,
		public int $recipientSubId = 0
	) {
	}

	/**
	 * @throws DataDecodeException
	 */
	public static function read(ByteBufferReader $in): self
	{
		return self::fromRaw(VarInt::readUnsignedInt($in));
	}

	/**
	 * @throws DataDecodeException
	 */
	public static function peekId(string $buffer): int
	{
		return VarInt::unpackUnsignedInt($buffer) & DataPacket::PID_MASK;
	}

	private static function fromRaw(int $raw): self
	{
		return new self(
			$raw & DataPacket::PID_MASK,
			($raw >> self::SENDER_SUBCLIENT_ID_SHIFT) & self::SUBCLIENT_ID_MASK,
			($raw >> self::RECIPIENT_SUBCLIENT_ID_SHIFT) & self::SUBCLIENT_ID_MASK
		);
	}

	public function encode(): string
	{
		return VarInt::packUnsignedInt(
			$this->id |
			($this->senderSubId << self::SENDER_SUBCLIENT_ID_SHIFT) |
			($this->recipientSubId << self::RECIPIENT_SUBCLIENT_ID_SHIFT)
		);
	}
}
