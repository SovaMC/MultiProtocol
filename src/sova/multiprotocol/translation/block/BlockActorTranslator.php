<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\block;

use pocketmine\nbt\tag\CompoundTag;
use sova\multiprotocol\packet\Direction;

interface BlockActorTranslator
{
	public function translate(Direction $direction, CompoundTag $nbt): CompoundTag;
}
