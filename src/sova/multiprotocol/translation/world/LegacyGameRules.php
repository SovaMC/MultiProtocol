<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\world;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\GameRule;
use function count;

final class LegacyGameRules
{
	private function __construct()
	{
	}

	/**
	 * @param array<string, GameRule> $rules
	 */
	public static function write(ByteBufferWriter $out, array $rules, int $codecProtocolId, bool $isStartGame): void
	{
		VarInt::writeUnsignedInt($out, count($rules));
		foreach ($rules as $name => $rule) {
			CommonTypes::putString($out, $name);
			VarInt::writeUnsignedInt($out, $rule->getTypeId());
			$rule->encode($out, $codecProtocolId, $isStartGame);
		}
	}
}
