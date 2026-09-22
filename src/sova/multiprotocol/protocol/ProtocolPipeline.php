<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol;

use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\session\ProtocolSession;
use function array_reverse;
use function array_slice;
use function array_values;
use function count;

final readonly class ProtocolPipeline
{
	public ProtocolVersion $client;

	/** @var list<Protocol> */
	private array $reversed;

	/**
	 * @param non-empty-list<Protocol> $protocols
	 */
	public function __construct(
		public array $protocols,
		public int $serverProtocolId
	) {
		$this->client = $protocols[0]->version;
		$this->reversed = array_reverse($protocols);
	}

	public function length(): int
	{
		return count($this->protocols);
	}

	/**
	 * @return list<string>
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	public function translate(Direction $direction, string $buffer, ProtocolSession $session, ?Protocol $after = null): array
	{
		$buffers = [$buffer];

		foreach ($this->hops($direction, $after) as $protocol) {
			$packets = $protocol->getPackets();
			$next = [];

			foreach ($buffers as $current) {
				foreach ($packets->translate($direction, $current, $session) as $translated) {
					$next[] = $translated;
				}
			}

			if ($next === []) {
				return [];
			}

			$buffers = $next;
		}

		return $buffers;
	}

	/**
	 * @return list<Protocol>
	 */
	private function hops(Direction $direction, ?Protocol $after): array
	{
		$hops = $direction === Direction::SERVERBOUND ? $this->protocols : $this->reversed;

		if ($after === null) {
			return $hops;
		}

		foreach ($hops as $index => $protocol) {
			if ($protocol === $after) {
				return array_values(array_slice($hops, $index + 1));
			}
		}

		throw new ProtocolException($after->version . ' is not a part of pipeline for ' . $this->client);
	}
}
