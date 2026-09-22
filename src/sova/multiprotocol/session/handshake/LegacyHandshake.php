<?php

declare(strict_types=1);

namespace sova\multiprotocol\session\handshake;

use pmmp\encoding\BE;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RequestNetworkSettingsPacket;
use sova\multiprotocol\network\BatchCodec;
use sova\multiprotocol\packet\PacketHeader;
use sova\multiprotocol\protocol\transport\CompressionType;
use function ord;
use function strlen;
use function zlib_decode;

final class LegacyHandshake
{
	private const int ZLIB_HEADER_MAGIC = 0x78;
	private const int MAX_LOGIN_SIZE = 2 * 1024 * 1024;

	private function __construct()
	{
	}

	public static function detect(string $payload): ?LegacyLogin
	{
		if (strlen($payload) < 2 || self::isNetworkSettingsRequest($payload)) {
			return null;
		}

		$decompressed = @zlib_decode($payload, self::MAX_LOGIN_SIZE);
		if ($decompressed === false) {
			return null;
		}

		try {
			foreach (BatchCodec::split($decompressed) as $buffer) {
				$reader = new ByteBufferReader($buffer);
				if (PacketHeader::read($reader)->id !== ProtocolInfo::LOGIN_PACKET) {
					return null;
				}

				return new LegacyLogin(
					BE::readUnsignedInt($reader),
					ord($payload[0]) === self::ZLIB_HEADER_MAGIC ? CompressionType::ZLIB : CompressionType::DEFLATE
				);
			}
		} catch (DataDecodeException|PacketDecodeException) {
			return null;
		}

		return null;
	}

	public static function createNetworkSettingsRequest(int $protocolId): string
	{
		$packet = new ByteBufferWriter();
		RequestNetworkSettingsPacket::create($protocolId)->encode($packet, $protocolId);

		return BatchCodec::join([$packet->getData()]);
	}

	private static function isNetworkSettingsRequest(string $payload): bool
	{
		try {
			foreach (BatchCodec::split($payload) as $buffer) {
				return PacketHeader::peekId($buffer) === ProtocolInfo::REQUEST_NETWORK_SETTINGS_PACKET;
			}
		} catch (DataDecodeException|PacketDecodeException) {
			return false;
		}

		return false;
	}
}
