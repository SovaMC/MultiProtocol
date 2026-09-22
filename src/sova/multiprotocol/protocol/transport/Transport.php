<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\transport;

final readonly class Transport
{
	public const int RAKNET_VERSION = 11;

	public function __construct(
		public int $raknetVersion = self::RAKNET_VERSION,
		public CompressionType $compression = CompressionType::DEFLATE,
		public EncryptionType $encryption = EncryptionType::CTR,
		public HandshakeType $handshake = HandshakeType::NETWORK_SETTINGS
	) {
	}

	public static function legacy(int $raknetVersion, EncryptionType $encryption = EncryptionType::CTR): self
	{
		return new self(
			$raknetVersion,
			$raknetVersion < 10 ? CompressionType::ZLIB : CompressionType::DEFLATE,
			$encryption,
			HandshakeType::LEGACY_LOGIN
		);
	}

	public function isLegacyHandshake(): bool
	{
		return $this->handshake === HandshakeType::LEGACY_LOGIN;
	}

	public function hasCustomCompression(): bool
	{
		return $this->compression !== CompressionType::DEFLATE;
	}

	public function hasCustomEncryption(): bool
	{
		return $this->encryption !== EncryptionType::CTR;
	}
}
