<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol;

use sova\multiprotocol\protocol\v1_19_0\Protocol1_19_0;
use sova\multiprotocol\protocol\v1_19_10\Protocol1_19_10;
use sova\multiprotocol\protocol\v1_19_20\Protocol1_19_20;
use sova\multiprotocol\protocol\v1_19_21\Protocol1_19_21;
use sova\multiprotocol\protocol\v1_19_30\Protocol1_19_30;
use sova\multiprotocol\protocol\v1_19_40\Protocol1_19_40;
use sova\multiprotocol\protocol\v1_19_50\Protocol1_19_50;
use sova\multiprotocol\protocol\v1_19_60\Protocol1_19_60;
use sova\multiprotocol\protocol\v1_19_63\Protocol1_19_63;
use sova\multiprotocol\protocol\v1_19_70\Protocol1_19_70;
use sova\multiprotocol\protocol\v1_19_70_24\Protocol1_19_70_24;
use sova\multiprotocol\protocol\v1_19_80\Protocol1_19_80;
use Symfony\Component\Filesystem\Path;

final class Protocols
{
	private function __construct()
	{
	}

	/**
	 * @return list<Protocol>
	 */
	public static function all(string $dataPath): array
	{
		$v1_19_80 = new Protocol1_19_80(Path::join($dataPath, (string) Protocol1_19_80::PROTOCOL));
		$v1_19_70 = new Protocol1_19_70(Path::join($dataPath, (string) Protocol1_19_70::PROTOCOL), $v1_19_80);
		$v1_19_70_24 = new Protocol1_19_70_24($v1_19_70);

		return [
			$v1_19_80,
			$v1_19_70,
			$v1_19_70_24,
			new Protocol1_19_63($dataPath),
			new Protocol1_19_60($dataPath),
			new Protocol1_19_50($dataPath),
			new Protocol1_19_40($dataPath),
			new Protocol1_19_30($dataPath),
			new Protocol1_19_21($dataPath),
			new Protocol1_19_20($dataPath),
			new Protocol1_19_10($dataPath),
			new Protocol1_19_0($dataPath),
		];
	}
}
