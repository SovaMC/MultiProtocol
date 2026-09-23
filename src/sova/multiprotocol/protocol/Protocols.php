<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol;

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

		return [$v1_19_80, $v1_19_70, $v1_19_70_24];
	}
}
