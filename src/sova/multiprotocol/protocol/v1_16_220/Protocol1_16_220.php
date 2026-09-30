<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16_220;

use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\transport\Transport;
use sova\multiprotocol\protocol\v1_16\Protocol1_16;
use sova\multiprotocol\protocol\v1_16_100\Protocol1_16_100;
use sova\multiprotocol\protocol\v1_16_210\Protocol1_16_210;
use sova\multiprotocol\protocol\v1_19\ProtocolResources;

final class Protocol1_16_220 extends Protocol1_16
{
	public const int PROTOCOL = 431;
	public const string VERSION = '1.16.220';

	private const int RAKNET_VERSION = 10;
	private const int BLOCK_PALETTE = Protocol1_16_210::PROTOCOL;
	private const int ITEM_TABLE = self::PROTOCOL;
	private const int ACTOR_IDENTIFIERS = Protocol1_16_100::PROTOCOL;
	private const int ITEM_SCHEMA_ID = 31;

	public function __construct(string $dataPath)
	{
		parent::__construct(
			new ProtocolVersion(self::PROTOCOL, self::VERSION, Transport::legacy(self::RAKNET_VERSION)),
			new ProtocolResources($dataPath, self::BLOCK_PALETTE, self::ITEM_TABLE, self::ACTOR_IDENTIFIERS, self::ITEM_SCHEMA_ID)
		);
	}
}
