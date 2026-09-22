<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\transport;

use pocketmine\network\mcpe\encryption\EncryptionContext;

enum EncryptionType
{
	case CTR;
	case CFB8;

	public function createContext(string $key): EncryptionContext
	{
		return match ($this) {
			self::CTR => EncryptionContext::fakeGCM($key),
			self::CFB8 => EncryptionContext::cfb8($key),
		};
	}
}
