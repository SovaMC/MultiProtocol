<?php

declare(strict_types=1);

namespace sova\multiprotocol\network;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\compression\DecompressionException;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use sova\multiprotocol\protocol\ProtocolConstants;
use function ord;
use function strlen;
use function substr;

final class BatchCodec
{
	private function __construct()
	{
	}

	/**
	 * @throws DecompressionException
	 */
	public static function decompress(string $payload, int $protocolId, Compressor $compressor): string
	{
		if (!ProtocolConstants::usesCompressionHeader($protocolId)) {
			return $compressor->decompress($payload);
		}

		if (strlen($payload) < 1) {
			throw new DecompressionException('Empty batch payload');
		}

		$algorithm = ord($payload[0]);
		$body = substr($payload, 1);

		return match ($algorithm) {
			CompressionAlgorithm::NONE => $body,
			$compressor->getNetworkId() => $compressor->decompress($body),
			default => throw new DecompressionException('Batch compressed with unexpected algorithm ' . $algorithm),
		};
	}

	/**
	 * @return list<string>
	 * @throws PacketDecodeException
	 */
	public static function split(string $batch): array
	{
		$buffers = [];
		foreach (PacketBatch::decodeRaw(new ByteBufferReader($batch)) as $buffer) {
			$buffers[] = $buffer;
		}

		return $buffers;
	}

	/**
	 * @param list<string> $buffers
	 */
	public static function join(array $buffers): string
	{
		$writer = new ByteBufferWriter();
		PacketBatch::encodeRaw($writer, $buffers);

		return $writer->getData();
	}
}
