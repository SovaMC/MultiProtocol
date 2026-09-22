<?php

declare(strict_types=1);

namespace sova\multiprotocol\config;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;

final readonly class MultiProtocolConfig
{
	/**
	 * @param list<int> $disabledProtocols
	 */
	public function __construct(
		public array $disabledProtocols = [],
		public bool $debug = false
	) {
	}

	/**
	 * @param array<mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$disabled = $data['disabled-protocols'] ?? [];

		return new self(
			is_array($disabled) ? array_values(array_map(intval(...), array_filter($disabled, is_numeric(...)))) : [],
			(bool) ($data['debug'] ?? false)
		);
	}

	public function isDisabled(int $protocolId): bool
	{
		return in_array($protocolId, $this->disabledProtocols, true);
	}
}
