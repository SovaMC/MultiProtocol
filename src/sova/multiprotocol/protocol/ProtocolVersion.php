<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol;

use sova\multiprotocol\protocol\transport\Transport;

final readonly class ProtocolVersion
{
	public function __construct(
		public int $id,
		public string $name,
		public Transport $transport = new Transport()
	) {
	}

	public function usesCompressionHeader(): bool
	{
		return ProtocolConstants::usesCompressionHeader($this->id);
	}

	public function __toString(): string
	{
		return $this->name . ' (' . $this->id . ')';
	}
}
