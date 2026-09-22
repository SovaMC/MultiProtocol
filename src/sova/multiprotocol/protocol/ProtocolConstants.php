<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use function in_array;

final class ProtocolConstants
{
	public const int LEGACY_BASE_PROTOCOL = ProtocolInfo::PROTOCOL_1_20_0;

	private function __construct()
	{
	}

	public static function usesCompressionHeader(int $protocolId): bool
	{
		return $protocolId >= ProtocolInfo::PROTOCOL_1_20_60;
	}

	public static function isNative(int $protocolId): bool
	{
		return in_array($protocolId, ProtocolInfo::ACCEPTED_PROTOCOL, true);
	}
}
