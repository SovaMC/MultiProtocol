<?php

declare(strict_types=1);

namespace sova\multiprotocol\session\handshake;

use sova\multiprotocol\protocol\transport\CompressionType;

final readonly class LegacyLogin
{
	public function __construct(
		public int $protocolId,
		public CompressionType $compression
	) {
	}
}
