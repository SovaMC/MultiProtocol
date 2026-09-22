<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol;

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
		return [
			new Protocol1_19_80(Path::join($dataPath, (string) Protocol1_19_80::PROTOCOL)),
		];
	}
}
