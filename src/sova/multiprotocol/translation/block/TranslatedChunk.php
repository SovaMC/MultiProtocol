<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

final readonly class TranslatedChunk
{
	public function __construct(
		public string $payload,
		public int $subChunkCount
	) {
	}
}
