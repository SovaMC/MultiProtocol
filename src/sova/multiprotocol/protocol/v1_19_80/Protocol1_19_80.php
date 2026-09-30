<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_80;

use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\v1_19\Protocol1_19;
use sova\multiprotocol\protocol\v1_19\ProtocolResources;

final class Protocol1_19_80 extends Protocol1_19
{
	public const int PROTOCOL = 582;
	public const string VERSION = '1.19.80';

	private const int BLOCK_PALETTE = 582;
	private const int ITEM_TABLE = 582;
	private const int ACTOR_IDENTIFIERS = 582;
	private const int ITEM_SCHEMA_ID = 101;

	public function __construct(string $dataPath)
	{
		parent::__construct(
			new ProtocolVersion(self::PROTOCOL, self::VERSION),
			new ProtocolResources($dataPath, self::BLOCK_PALETTE, self::ITEM_TABLE, self::ACTOR_IDENTIFIERS, self::ITEM_SCHEMA_ID)
		);
	}
}
