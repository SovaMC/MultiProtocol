<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\actor;

use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\Filesystem;

final readonly class ActorIdentifiers
{
	private const string LIST_TAG = 'idlist';
	private const string ID_TAG = 'id';

	/** @var array<string, true> */
	private array $known;

	/**
	 * @param array<string, string> $overrides
	 */
	public function __construct(
		public CompoundTag $nbt,
		private array $overrides = [],
		private string $fallback = EntityIds::PIG
	) {
		$known = [];
		foreach ($nbt->getListTag(self::LIST_TAG)?->getValue() ?? [] as $entry) {
			if ($entry instanceof CompoundTag) {
				$known[$entry->getString(self::ID_TAG, '')] = true;
			}
		}
		$this->known = $known;
	}

	/**
	 * @param array<string, string> $overrides
	 * @throws NbtDataException
	 */
	public static function load(string $file, array $overrides = []): self
	{
		return new self((new NetworkNbtSerializer())->read(Filesystem::fileGetContents($file))->mustGetCompoundTag(), $overrides);
	}

	public function translate(string $type): string
	{
		return $this->overrides[$type] ?? (isset($this->known[$type]) || $this->known === [] ? $type : $this->fallback);
	}
}
