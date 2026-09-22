<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol;

use pocketmine\data\bedrock\ItemTagDowngrader;
use pocketmine\data\bedrock\ItemTagToIdMap;
use pocketmine\network\mcpe\cache\CraftingDataCache;
use pocketmine\network\mcpe\cache\CreativeInventoryCache;
use pocketmine\network\mcpe\convert\TypeConverter;

final class ProtocolAliases
{
	private function __construct()
	{
	}

	public static function register(int $clientProtocolId, int $serverProtocolId): void
	{
		TypeConverter::setInstance(TypeConverter::getInstance($serverProtocolId), $clientProtocolId);
		CraftingDataCache::setInstance(CraftingDataCache::getInstance($serverProtocolId), $clientProtocolId);
		CreativeInventoryCache::setInstance(CreativeInventoryCache::getInstance($serverProtocolId), $clientProtocolId);
		ItemTagToIdMap::setInstance(ItemTagToIdMap::getInstance($serverProtocolId), $clientProtocolId);
		ItemTagDowngrader::setInstance(ItemTagDowngrader::getInstance($serverProtocolId), $clientProtocolId);
	}
}
