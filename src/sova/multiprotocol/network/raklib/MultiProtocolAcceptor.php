<?php

declare(strict_types=1);

namespace sova\multiprotocol\network\raklib;

use raklib\server\ProtocolAcceptor;
use function array_fill_keys;

final readonly class MultiProtocolAcceptor implements ProtocolAcceptor
{
	/** @var array<int, true> */
	private array $versions;

	/**
	 * @param list<int> $versions
	 */
	public function __construct(
		private int $primaryVersion,
		array $versions
	) {
		$this->versions = array_fill_keys($versions, true) + [$primaryVersion => true];
	}

	public function accepts(int $protocolVersion): bool
	{
		return isset($this->versions[$protocolVersion]);
	}

	public function getPrimaryVersion(): int
	{
		return $this->primaryVersion;
	}
}
