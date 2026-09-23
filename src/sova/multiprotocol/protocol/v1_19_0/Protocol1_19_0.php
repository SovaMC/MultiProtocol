<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_0;

use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\transport\Transport;
use sova\multiprotocol\protocol\v1_19\Protocol1_19;
use sova\multiprotocol\protocol\v1_19\ProtocolResources;

final class Protocol1_19_0 extends Protocol1_19
{
	public const int PROTOCOL = 527;
	public const string VERSION = '1.19.0';

	private const int RAKNET_VERSION = 10;
	private const int BLOCK_PALETTE = 527;
	private const int ITEM_TABLE = 527;
	private const int ACTOR_IDENTIFIERS = 526;
	private const int ITEM_SCHEMA_ID = 71;

	public function __construct(string $dataPath)
	{
		parent::__construct(
			new ProtocolVersion(self::PROTOCOL, self::VERSION, Transport::legacy(self::RAKNET_VERSION)),
			new ProtocolResources($dataPath, self::BLOCK_PALETTE, self::ITEM_TABLE, self::ACTOR_IDENTIFIERS, self::ITEM_SCHEMA_ID)
		);
	}
}
