<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_18_10;

use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\transport\Transport;
use sova\multiprotocol\protocol\v1_18\Protocol1_18;
use sova\multiprotocol\protocol\v1_19\ProtocolResources;

final class Protocol1_18_10 extends Protocol1_18
{
	public const int PROTOCOL = 486;
	public const string VERSION = '1.18.10';

	private const int RAKNET_VERSION = 10;
	private const int BLOCK_PALETTE = 486;
	private const int ITEM_TABLE = 486;
	private const int ACTOR_IDENTIFIERS = 475;
	private const int ITEM_SCHEMA_ID = 61;

	public function __construct(string $dataPath)
	{
		parent::__construct(
			new ProtocolVersion(self::PROTOCOL, self::VERSION, Transport::legacy(self::RAKNET_VERSION)),
			new ProtocolResources($dataPath, self::BLOCK_PALETTE, self::ITEM_TABLE, self::ACTOR_IDENTIFIERS, self::ITEM_SCHEMA_ID)
		);
	}
}
