<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\ability;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;

final class LegacyAdventureSettings
{
	private const int WORLD_IMMUTABLE = 0x01;
	private const int NO_PVP = 0x02;
	private const int AUTO_JUMP = 0x20;

	private const array FLAGS = [
		AbilitiesLayer::ABILITY_ALLOW_FLIGHT => 0x40,
		AbilitiesLayer::ABILITY_NO_CLIP => 0x80,
		AbilitiesLayer::ABILITY_WORLD_BUILDER => 0x100,
		AbilitiesLayer::ABILITY_FLYING => 0x200,
		AbilitiesLayer::ABILITY_MUTED => 0x400,
	];

	private const array FLAGS2 = [
		AbilitiesLayer::ABILITY_MINE => 0x01,
		AbilitiesLayer::ABILITY_DOORS_AND_SWITCHES => 0x02,
		AbilitiesLayer::ABILITY_OPEN_CONTAINERS => 0x04,
		AbilitiesLayer::ABILITY_ATTACK_PLAYERS => 0x08,
		AbilitiesLayer::ABILITY_ATTACK_MOBS => 0x10,
		AbilitiesLayer::ABILITY_OPERATOR => 0x20,
		AbilitiesLayer::ABILITY_TELEPORT => 0x80,
		AbilitiesLayer::ABILITY_BUILD => 0x100,
	];

	private ?AbilitiesData $abilities = null;

	private bool $worldImmutable = false;
	private bool $noPvp = false;
	private bool $autoJump = true;

	public function setAbilities(AbilitiesData $abilities): void
	{
		$this->abilities = $abilities;
	}

	public function setAdventureSettings(bool $worldImmutable, bool $noPvp, bool $autoJump): void
	{
		$this->worldImmutable = $worldImmutable;
		$this->noPvp = $noPvp;
		$this->autoJump = $autoJump;
	}

	public function write(ByteBufferWriter $out): void
	{
		self::writeAbilities($out, $this->abilities ?? new AbilitiesData(0, 0, 0, []), $this->flags());
	}

	public static function writeAbilities(ByteBufferWriter $out, AbilitiesData $abilities, int $flags = 0): void
	{
		$values = self::resolve($abilities);

		foreach (self::FLAGS as $ability => $bit) {
			if ($values[$ability] ?? false) {
				$flags |= $bit;
			}
		}

		$flags2 = 0;
		foreach (self::FLAGS2 as $ability => $bit) {
			if ($values[$ability] ?? false) {
				$flags2 |= $bit;
			}
		}

		VarInt::writeUnsignedInt($out, $flags);
		VarInt::writeUnsignedInt($out, $abilities->getCommandPermission());
		VarInt::writeUnsignedInt($out, $flags2);
		VarInt::writeUnsignedInt($out, $abilities->getPlayerPermission());
		VarInt::writeUnsignedInt($out, 0);
		LE::writeSignedLong($out, $abilities->getTargetActorUniqueId());
	}

	private function flags(): int
	{
		$flags = 0;
		if ($this->worldImmutable) {
			$flags |= self::WORLD_IMMUTABLE;
		}
		if ($this->noPvp) {
			$flags |= self::NO_PVP;
		}
		if ($this->autoJump) {
			$flags |= self::AUTO_JUMP;
		}

		return $flags;
	}

	/**
	 * @return array<int, bool>
	 */
	private static function resolve(AbilitiesData $abilities): array
	{
		$values = [];
		foreach ($abilities->getAbilityLayers() as $layer) {
			foreach ($layer->getBoolAbilities() as $ability => $value) {
				$values[$ability] = $value;
			}
		}

		return $values;
	}
}
