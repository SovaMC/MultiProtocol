<?php

declare(strict_types=1);

namespace sova\multiprotocol\packet;

enum Direction: string
{
	case SERVERBOUND = 'serverbound';
	case CLIENTBOUND = 'clientbound';

	public function opposite(): self
	{
		return match ($this) {
			self::SERVERBOUND => self::CLIENTBOUND,
			self::CLIENTBOUND => self::SERVERBOUND,
		};
	}
}
