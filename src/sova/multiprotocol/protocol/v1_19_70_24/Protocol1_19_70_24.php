<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_70_24;

use sova\multiprotocol\packet\PacketRegistry;
use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\v1_19\Protocol1_19;
use sova\multiprotocol\protocol\v1_19\ProtocolResources;
use sova\multiprotocol\translation\rewriter\recipe\HiddenCreativeItemsRewriter;
use function array_map;

final class Protocol1_19_70_24 extends Protocol1_19
{
	public const int PROTOCOL = 574;
	public const string VERSION = '1.19.70.24';

	private const int BLOCK_PALETTE = 575;
	private const int ITEM_TABLE = 575;
	private const int ACTOR_IDENTIFIERS = 575;
	private const int ITEM_SCHEMA_ID = 91;

	private const array HIDDEN_ITEMS = [
		'minecraft:torchflower_seeds',
	];

	public function __construct(string $dataPath)
	{
		parent::__construct(
			new ProtocolVersion(self::PROTOCOL, self::VERSION),
			new ProtocolResources($dataPath, self::BLOCK_PALETTE, self::ITEM_TABLE, self::ACTOR_IDENTIFIERS, self::ITEM_SCHEMA_ID)
		);
	}

	protected function registerVersionPackets(PacketRegistry $packets): void
	{
		$items = $this->getClientData()->items;

		$packets->add(new HiddenCreativeItemsRewriter(
			array_map($items->fromStringId(...), self::HIDDEN_ITEMS),
			self::CODEC_PROTOCOL
		));
	}
}
