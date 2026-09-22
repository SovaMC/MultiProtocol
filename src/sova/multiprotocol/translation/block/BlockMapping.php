<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\network\mcpe\convert\BlockStateDictionaryEntry;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\protocol\ProtocolException;
use function array_map;

final readonly class BlockMapping
{
	/**
	 * @param array<int, int> $toClient
	 * @param array<int, int> $toServer
	 */
	public function __construct(
		private array $toClient,
		private array $toServer,
		private int $clientFallback,
		private int $serverFallback
	) {
	}

	/**
	 * @param array<int, BlockStateData> $serverStates
	 * @param array<int, BlockStateData> $clientStates
	 */
	public static function build(array $serverStates, array $clientStates): self
	{
		[$serverIndex, $serverNames] = self::index($serverStates);
		[$clientIndex, $clientNames] = self::index($clientStates);

		$clientFallback = $clientIndex[self::fallbackKey()] ?? throw new ProtocolException('Client palette has no ' . BlockTypeNames::INFO_UPDATE);
		$serverFallback = $serverIndex[self::fallbackKey()] ?? throw new ProtocolException('Server palette has no ' . BlockTypeNames::INFO_UPDATE);

		return new self(
			self::link($serverStates, $clientIndex, $clientNames, $clientFallback),
			self::link($clientStates, $serverIndex, $serverNames, $serverFallback),
			$clientFallback,
			$serverFallback
		);
	}

	public function toClient(int $runtimeId): int
	{
		return $this->toClient[$runtimeId] ?? $this->clientFallback;
	}

	public function toServer(int $runtimeId): int
	{
		return $this->toServer[$runtimeId] ?? $this->serverFallback;
	}

	public function map(Direction $direction, int $runtimeId): int
	{
		return $direction === Direction::CLIENTBOUND ? $this->toClient($runtimeId) : $this->toServer($runtimeId);
	}

	/**
	 * @param array<int, BlockStateData> $states
	 * @return array{array<string, int>, array<string, int>}
	 */
	private static function index(array $states): array
	{
		$byKey = [];
		$byName = [];

		foreach ($states as $runtimeId => $state) {
			$byKey[self::key($state)] ??= $runtimeId;
			$byName[$state->getName()] ??= $runtimeId;
		}

		return [$byKey, $byName];
	}

	/**
	 * @param array<int, BlockStateData> $states
	 * @param array<string, int>         $targetIndex
	 * @param array<string, int>         $targetNames
	 * @return array<int, int>
	 */
	private static function link(array $states, array $targetIndex, array $targetNames, int $fallback): array
	{
		return array_map(function ($state) use ($targetNames, $targetIndex, $fallback) {
			return $targetIndex[self::key($state)] ?? $targetNames[$state->getName()] ?? $fallback;
		}, $states);
	}

	private static function key(BlockStateData $state): string
	{
		return $state->getName() . "\x00" . BlockStateDictionaryEntry::encodeStateProperties($state->getStates());
	}

	private static function fallbackKey(): string
	{
		return BlockTypeNames::INFO_UPDATE . "\x00";
	}
}
