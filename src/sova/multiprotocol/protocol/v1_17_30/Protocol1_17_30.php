<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_17_30;

use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\transport\Transport;
use sova\multiprotocol\protocol\v1_17\Protocol1_17;
use sova\multiprotocol\protocol\v1_19\ProtocolResources;

final class Protocol1_17_30 extends Protocol1_17
{
	public const int PROTOCOL = 465;
	public const string VERSION = '1.17.30';

	private const int RAKNET_VERSION = 10;
	private const int BLOCK_PALETTE = 465;
	private const int ITEM_TABLE = 465;
	private const int ACTOR_IDENTIFIERS = 440;
	private const int ITEM_SCHEMA_ID = 41;

	public function __construct(string $dataPath)
	{
		parent::__construct(
			new ProtocolVersion(self::PROTOCOL, self::VERSION, Transport::legacy(self::RAKNET_VERSION)),
			new ProtocolResources($dataPath, self::BLOCK_PALETTE, self::ITEM_TABLE, self::ACTOR_IDENTIFIERS, self::ITEM_SCHEMA_ID)
		);
	}
}
