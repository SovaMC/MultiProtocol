<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19;

use sova\multiprotocol\translation\ProtocolData;
use Symfony\Component\Filesystem\Path;

final readonly class ProtocolResources
{
	public function __construct(
		private string $dataPath,
		public int $blockPalette,
		public int $itemTable,
		public int $actorIdentifiers,
		public int $itemSchemaId
	) {
	}

	public function getBlockPaletteFile(): string
	{
		return Path::join($this->dataPath, (string) $this->blockPalette, ProtocolData::BLOCK_PALETTE_FILE);
	}

	public function getItemTableFile(): string
	{
		return Path::join($this->dataPath, (string) $this->itemTable, ProtocolData::ITEM_TABLE_FILE);
	}

	public function getActorIdentifiersFile(): string
	{
		return Path::join($this->dataPath, (string) $this->actorIdentifiers, ProtocolData::ACTOR_IDENTIFIERS_FILE);
	}

	public function getMappingsKey(): string
	{
		return $this->blockPalette . ':' . $this->itemTable . ':' . $this->itemSchemaId;
	}
}
