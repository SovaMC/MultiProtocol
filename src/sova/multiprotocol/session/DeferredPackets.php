<?php

declare(strict_types=1);

namespace sova\multiprotocol\session;

use function hrtime;

final class DeferredPackets
{
	private const int DELAY_NS = 2_000_000_000;

	/** @var array<string, array{buffer: string, time: int|float}> */
	private array $packets = [];

	public function defer(string $key, string $buffer): void
	{
		unset($this->packets[$key]);
		$this->packets[$key] = ['buffer' => $buffer, 'time' => hrtime(true)];
	}

	/**
	 * @return list<string>
	 */
	public function drain(): array
	{
		$now = hrtime(true);
		$ready = [];
		foreach ($this->packets as $key => $packet) {
			if ($now - $packet['time'] < self::DELAY_NS) {
				continue;
			}
			$ready[] = $packet['buffer'];
			unset($this->packets[$key]);
		}

		return $ready;
	}
}
