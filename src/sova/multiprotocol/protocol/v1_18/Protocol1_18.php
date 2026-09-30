<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_18;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketRegistry;
use sova\multiprotocol\protocol\v1_18\rewriter\InteractRewriter;
use sova\multiprotocol\protocol\v1_18\rewriter\PlayerActionRewriter;
use sova\multiprotocol\protocol\v1_18\rewriter\SpawnParticleEffectRewriter;
use sova\multiprotocol\protocol\v1_18_10\Protocol1_18_10;
use sova\multiprotocol\protocol\v1_18_30\Protocol1_18_30;
use sova\multiprotocol\protocol\v1_19\Protocol1_19;
use sova\multiprotocol\translation\command\ArgumentTypeRemap;

abstract class Protocol1_18 extends Protocol1_19
{
	private const int COMPARE_OPERATOR_ARGUMENT_TYPE = 7;
	private const int STRING_ARGUMENT_TYPE = 38;
	private const int EQUIPMENT_ARGUMENT_TYPES = 32;
	private const int EQUIPMENT_ARGUMENT_TYPE_COUNT = 6;
	private const int LEGACY_STRING_ARGUMENT_TYPE = 32;

	private const array LEGACY_ACTION_TYPES = [
		7 => 9,
		8 => 10,
		9 => 11,
		10 => 12,
		11 => 13,
		12 => 14,
		13 => 15,
		14 => 16,
		15 => 17,
		16 => 18,
		17 => 19,
	];

	private const array ACTOR_OVERRIDES = [
		EntityIds::CHEST_BOAT => EntityIds::BOAT,
		EntityIds::ALLAY => EntityIds::BAT,
		EntityIds::WARDEN => EntityIds::IRON_GOLEM,
		EntityIds::TADPOLE => EntityIds::SALMON,
		EntityIds::FROG => EntityIds::RABBIT,
	];

	private const array REPLACEMENTS = [
		'minecraft:mangrove_log' => 'minecraft:oak_log',
		'minecraft:stripped_mangrove_log' => 'minecraft:stripped_oak_log',
		'minecraft:mangrove_wood' => 'minecraft:oak_wood',
		'minecraft:stripped_mangrove_wood' => 'minecraft:stripped_oak_wood',
		'minecraft:mangrove_planks' => 'minecraft:oak_planks',
		'minecraft:mangrove_slab' => 'minecraft:oak_slab',
		'minecraft:mangrove_double_slab' => 'minecraft:oak_double_slab',
		'minecraft:mangrove_stairs' => 'minecraft:oak_stairs',
		'minecraft:mangrove_fence' => 'minecraft:oak_fence',
		'minecraft:mangrove_fence_gate' => 'minecraft:fence_gate',
		'minecraft:mangrove_door' => 'minecraft:wooden_door',
		'minecraft:mangrove_trapdoor' => 'minecraft:trapdoor',
		'minecraft:mangrove_button' => 'minecraft:wooden_button',
		'minecraft:mangrove_pressure_plate' => 'minecraft:wooden_pressure_plate',
		'minecraft:mangrove_standing_sign' => 'minecraft:standing_sign',
		'minecraft:mangrove_wall_sign' => 'minecraft:wall_sign',
		'minecraft:mangrove_sign' => 'minecraft:oak_sign',
		'minecraft:mangrove_leaves' => 'minecraft:oak_leaves',
		'minecraft:mangrove_propagule' => 'minecraft:oak_sapling',
		'minecraft:mangrove_roots' => 'minecraft:oak_wood',
		'minecraft:muddy_mangrove_roots' => 'minecraft:dirt',
		'minecraft:mangrove_boat' => 'minecraft:oak_boat',
		'minecraft:mud' => 'minecraft:dirt',
		'minecraft:packed_mud' => 'minecraft:coarse_dirt',
		'minecraft:mud_bricks' => 'minecraft:brick_block',
		'minecraft:mud_brick_slab' => 'minecraft:brick_slab',
		'minecraft:mud_brick_double_slab' => 'minecraft:brick_double_slab',
		'minecraft:mud_brick_stairs' => 'minecraft:brick_stairs',
		'minecraft:mud_brick_wall' => 'minecraft:cobblestone_wall',
		'minecraft:ochre_froglight' => 'minecraft:glowstone',
		'minecraft:verdant_froglight' => 'minecraft:sea_lantern',
		'minecraft:pearlescent_froglight' => 'minecraft:shroomlight',
		'minecraft:frog_spawn' => 'minecraft:waterlily',
		'minecraft:chest_boat' => 'minecraft:oak_boat',
		'minecraft:spruce_chest_boat' => 'minecraft:spruce_boat',
		'minecraft:birch_chest_boat' => 'minecraft:birch_boat',
		'minecraft:jungle_chest_boat' => 'minecraft:jungle_boat',
		'minecraft:acacia_chest_boat' => 'minecraft:acacia_boat',
		'minecraft:dark_oak_chest_boat' => 'minecraft:dark_oak_boat',
		'minecraft:mangrove_chest_boat' => 'minecraft:oak_boat',
		'minecraft:cherry_chest_boat' => 'minecraft:birch_boat',
	];

	protected function registerVersionPackets(PacketRegistry $packets): void
	{
		$packets
			->add(new PlayerActionRewriter())
			->cancel(Direction::CLIENTBOUND, ProtocolInfo::TOAST_REQUEST_PACKET, ProtocolInfo::LESSON_PROGRESS_PACKET);

		if ($this->isBefore(Protocol1_18_30::PROTOCOL)) {
			$packets
				->add(new SpawnParticleEffectRewriter())
				->cancel(
					Direction::CLIENTBOUND,
					ProtocolInfo::DIMENSION_DATA_PACKET,
					ProtocolInfo::AGENT_ACTION_EVENT_PACKET,
					ProtocolInfo::CHANGE_MOB_PROPERTY_PACKET
				);
		}

		if ($this->isBefore(Protocol1_18_10::PROTOCOL)) {
			$packets
				->add(new InteractRewriter())
				->cancel(Direction::CLIENTBOUND, ProtocolInfo::PLAYER_START_ITEM_COOLDOWN_PACKET, ProtocolInfo::TICKING_AREAS_LOAD_STATUS_PACKET);
		}
	}

	protected function itemReplacements(): array
	{
		return parent::itemReplacements() + self::REPLACEMENTS;
	}

	protected function blockReplacements(): array
	{
		return parent::blockReplacements() + self::REPLACEMENTS;
	}

	protected function actorOverrides(): array
	{
		return self::ACTOR_OVERRIDES + parent::actorOverrides();
	}

	protected function commandArgumentRemaps(): array
	{
		$remaps = parent::commandArgumentRemaps();
		$remaps[] = new ArgumentTypeRemap(self::COMPARE_OPERATOR_ARGUMENT_TYPE, 1, self::STRING_ARGUMENT_TYPE);
		if ($this->isBefore(Protocol1_18_30::PROTOCOL)) {
			$remaps[] = new ArgumentTypeRemap(self::EQUIPMENT_ARGUMENT_TYPES, self::EQUIPMENT_ARGUMENT_TYPE_COUNT, self::LEGACY_STRING_ARGUMENT_TYPE);
		}

		return $remaps;
	}

	protected function legacyActionTypes(): array
	{
		return $this->isBefore(Protocol1_18_10::PROTOCOL) ? self::LEGACY_ACTION_TYPES : [];
	}
}
