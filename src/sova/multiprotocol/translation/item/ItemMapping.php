<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\item;

use InvalidArgumentException;
use pocketmine\data\bedrock\item\downgrade\ItemIdMetaDowngrader;
use pocketmine\data\bedrock\item\upgrade\ItemIdMetaUpgrader;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use sova\multiprotocol\packet\Direction;
use function array_flip;
use function array_key_exists;

final class ItemMapping
{
	/** @var array<string, array{int, int}|null> */
	private array $toClientCache = [];

	/** @var array<string, array{int, int}|null> */
	private array $toServerCache = [];

	/** @var array<string, string> */
	private readonly array $clientConversions;

	/** @var array<string, string> */
	private readonly array $serverConversions;

	/**
	 * @param array<string, string> $clientRenames
	 * @param array<string, string> $clientAliases
	 */
	public function __construct(
		public readonly ItemTypeDictionary $serverDictionary,
		public readonly ItemTypeDictionary $clientDictionary,
		private readonly ItemIdMetaUpgrader $upgrader,
		private readonly ItemIdMetaDowngrader $clientDowngrader,
		private readonly ItemIdMetaDowngrader $serverDowngrader,
		array $clientRenames = [],
		private readonly array $clientAliases = []
	) {
		$this->clientConversions = $clientAliases + $clientRenames;
		$this->serverConversions = array_flip($this->clientConversions);
	}

	/**
	 * @return array{int, int}|null
	 */
	public function toClient(int $id, int $meta): ?array
	{
		if ($id === 0) {
			return [0, 0];
		}

		$key = $id . ':' . $meta;
		if (!array_key_exists($key, $this->toClientCache)) {
			$this->toClientCache[$key] = $this->convert($this->serverDictionary, $this->clientDictionary, $this->clientDowngrader, $this->clientConversions, $id, $meta);
		}

		return $this->toClientCache[$key];
	}

	/**
	 * @return array{int, int}|null
	 */
	public function toServer(int $id, int $meta): ?array
	{
		if ($id === 0) {
			return [0, 0];
		}

		$key = $id . ':' . $meta;
		if (!array_key_exists($key, $this->toServerCache)) {
			$this->toServerCache[$key] = $this->convert($this->clientDictionary, $this->serverDictionary, $this->serverDowngrader, $this->serverConversions, $id, $meta);
		}

		return $this->toServerCache[$key];
	}

	/**
	 * @return array{int, int}|null
	 */
	public function map(Direction $direction, int $id, int $meta): ?array
	{
		return $direction === Direction::CLIENTBOUND ? $this->toClient($id, $meta) : $this->toServer($id, $meta);
	}

	public function withoutRenames(): self
	{
		return new self($this->serverDictionary, $this->clientDictionary, $this->upgrader, $this->clientDowngrader, $this->serverDowngrader, [], $this->clientAliases);
	}

	public function hasClientItem(string $stringId): bool
	{
		try {
			$this->clientDictionary->fromStringId($stringId);
			return true;
		} catch (InvalidArgumentException) {
			return false;
		}
	}

	/**
	 * @param array<string, string> $renames
	 * @return array{int, int}|null
	 */
	private function convert(ItemTypeDictionary $from, ItemTypeDictionary $to, ItemIdMetaDowngrader $downgrader, array $renames, int $id, int $meta): ?array
	{
		try {
			$stringId = $from->fromIntId($id);
		} catch (InvalidArgumentException) {
			return null;
		}

		$result = $this->lookup($to, $downgrader, $stringId, $meta);
		if ($result === null && isset($renames[$stringId])) {
			$result = $this->lookup($to, $downgrader, $renames[$stringId], $meta);
		}

		return $result;
	}

	/**
	 * @return array{int, int}|null
	 */
	private function lookup(ItemTypeDictionary $to, ItemIdMetaDowngrader $downgrader, string $stringId, int $meta): ?array
	{
		[$currentId, $currentMeta] = $this->upgrader->upgrade($stringId, $meta);
		[$targetId, $targetMeta] = $downgrader->downgrade($currentId, $currentMeta);

		try {
			return [$to->fromStringId($targetId), $targetMeta];
		} catch (InvalidArgumentException) {
			return null;
		}
	}
}
