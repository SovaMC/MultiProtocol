<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_16;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use sova\multiprotocol\packet\PacketRegistry;
use sova\multiprotocol\protocol\v1_16\rewriter\BlockBreakTransactionRewriter;
use sova\multiprotocol\protocol\v1_16\rewriter\CameraShakeRewriter;
use sova\multiprotocol\protocol\v1_16\rewriter\ContainerCloseRewriter;
use sova\multiprotocol\protocol\v1_16\rewriter\GameRulesChangedRewriter;
use sova\multiprotocol\protocol\v1_16\rewriter\MoveActorDeltaRewriter;
use sova\multiprotocol\protocol\v1_16\rewriter\MovePlayerRewriter;
use sova\multiprotocol\protocol\v1_16\rewriter\PlayerActionRewriter;
use sova\multiprotocol\protocol\v1_16\rewriter\ResourcePackStackRewriter;
use sova\multiprotocol\protocol\v1_16_100\Protocol1_16_100;
use sova\multiprotocol\protocol\v1_16_200\Protocol1_16_200;
use sova\multiprotocol\protocol\v1_16_210\Protocol1_16_210;
use sova\multiprotocol\protocol\v1_17\Protocol1_17;
use sova\multiprotocol\translation\command\ArgumentTypeRemap;
use function str_contains;

abstract class Protocol1_16 extends Protocol1_17
{
	private const array ACTION_TYPES_422 = [
		7 => 9, 8 => 10, 9 => 12, 10 => 13, 11 => 14, 12 => 15, 13 => 18, 14 => 19,
	];

	private const array ACTION_TYPES_419 = [
		7 => 9, 8 => 10, 9 => 12, 10 => 13, 11 => 14, 12 => 18, 13 => 19,
	];

	private const array ACTOR_OVERRIDES = [
		EntityIds::GOAT => EntityIds::SHEEP,
		EntityIds::AXOLOTL => EntityIds::TROPICALFISH,
		EntityIds::GLOW_SQUID => EntityIds::SQUID,
	];

	private const array REPLACEMENTS = [
		'minecraft:deepslate' => 'minecraft:stone',
		'minecraft:cobbled_deepslate' => 'minecraft:cobblestone',
		'minecraft:polished_deepslate' => 'minecraft:polished_andesite',
		'minecraft:deepslate_bricks' => 'minecraft:stone_bricks',
		'minecraft:cracked_deepslate_bricks' => 'minecraft:cracked_stone_bricks',
		'minecraft:deepslate_tiles' => 'minecraft:stone_bricks',
		'minecraft:cracked_deepslate_tiles' => 'minecraft:cracked_stone_bricks',
		'minecraft:chiseled_deepslate' => 'minecraft:chiseled_stone_bricks',
		'minecraft:infested_deepslate' => 'minecraft:infested_stone',
		'minecraft:reinforced_deepslate' => 'minecraft:stone',
		'minecraft:deepslate_coal_ore' => 'minecraft:coal_ore',
		'minecraft:deepslate_iron_ore' => 'minecraft:iron_ore',
		'minecraft:deepslate_gold_ore' => 'minecraft:gold_ore',
		'minecraft:deepslate_redstone_ore' => 'minecraft:redstone_ore',
		'minecraft:lit_deepslate_redstone_ore' => 'minecraft:lit_redstone_ore',
		'minecraft:deepslate_lapis_ore' => 'minecraft:lapis_ore',
		'minecraft:deepslate_diamond_ore' => 'minecraft:diamond_ore',
		'minecraft:deepslate_emerald_ore' => 'minecraft:emerald_ore',
		'minecraft:copper_ore' => 'minecraft:iron_ore',
		'minecraft:deepslate_copper_ore' => 'minecraft:iron_ore',
		'minecraft:raw_iron_block' => 'minecraft:iron_block',
		'minecraft:raw_gold_block' => 'minecraft:gold_block',
		'minecraft:raw_copper_block' => 'minecraft:orange_terracotta',
		'minecraft:copper_block' => 'minecraft:orange_terracotta',
		'minecraft:amethyst_block' => 'minecraft:purpur_block',
		'minecraft:budding_amethyst' => 'minecraft:purpur_block',
		'minecraft:amethyst_cluster' => 'minecraft:air',
		'minecraft:small_amethyst_bud' => 'minecraft:air',
		'minecraft:medium_amethyst_bud' => 'minecraft:air',
		'minecraft:large_amethyst_bud' => 'minecraft:air',
		'minecraft:calcite' => 'minecraft:diorite',
		'minecraft:tuff' => 'minecraft:andesite',
		'minecraft:dripstone_block' => 'minecraft:granite',
		'minecraft:pointed_dripstone' => 'minecraft:air',
		'minecraft:smooth_basalt' => 'minecraft:basalt',
		'minecraft:tinted_glass' => 'minecraft:black_stained_glass',
		'minecraft:moss_block' => 'minecraft:grass_block',
		'minecraft:moss_carpet' => 'minecraft:green_carpet',
		'minecraft:azalea' => 'minecraft:oak_leaves',
		'minecraft:flowering_azalea' => 'minecraft:oak_leaves',
		'minecraft:azalea_leaves' => 'minecraft:oak_leaves',
		'minecraft:azalea_leaves_flowered' => 'minecraft:oak_leaves',
		'minecraft:cave_vines' => 'minecraft:weeping_vines',
		'minecraft:cave_vines_body_with_berries' => 'minecraft:weeping_vines',
		'minecraft:cave_vines_head_with_berries' => 'minecraft:weeping_vines',
		'minecraft:hanging_roots' => 'minecraft:air',
		'minecraft:spore_blossom' => 'minecraft:air',
		'minecraft:glow_lichen' => 'minecraft:air',
		'minecraft:big_dripleaf' => 'minecraft:waterlily',
		'minecraft:small_dripleaf_block' => 'minecraft:air',
		'minecraft:dirt_with_roots' => 'minecraft:dirt',
		'minecraft:powder_snow' => 'minecraft:snow',
		'minecraft:lightning_rod' => 'minecraft:end_rod',
		'minecraft:glow_frame' => 'minecraft:frame',
		'minecraft:sculk_sensor' => 'minecraft:black_concrete',
		'minecraft:calibrated_sculk_sensor' => 'minecraft:black_concrete',
		'minecraft:sculk_catalyst' => 'minecraft:black_concrete',
		'minecraft:sculk_shrieker' => 'minecraft:black_concrete',
		'minecraft:camera' => 'minecraft:air',
		'minecraft:nether_sprouts' => 'minecraft:air',
		'minecraft:sticky_piston_arm_collision' => 'minecraft:piston_arm_collision',
		'minecraft:glow_berries' => 'minecraft:sweet_berries',
		'minecraft:glow_ink_sac' => 'minecraft:ink_sac',
		'minecraft:raw_iron' => 'minecraft:iron_ingot',
		'minecraft:raw_gold' => 'minecraft:gold_ingot',
		'minecraft:raw_copper' => 'minecraft:brick',
		'minecraft:copper_ingot' => 'minecraft:brick',
		'minecraft:amethyst_shard' => 'minecraft:prismarine_shard',
		'minecraft:spyglass' => 'minecraft:stick',
		'minecraft:axolotl_bucket' => 'minecraft:tropical_fish_bucket',
		'minecraft:powder_snow_bucket' => 'minecraft:bucket',
	];

	private const array LEGACY_BLOCK_REPLACEMENTS = [
		'minecraft:blackstone_double_slab' => 'minecraft:blackstone_slab',
		'minecraft:polished_blackstone_double_slab' => 'minecraft:polished_blackstone_slab',
		'minecraft:polished_blackstone_brick_double_slab' => 'minecraft:polished_blackstone_brick_slab',
		'minecraft:crimson_double_slab' => 'minecraft:crimson_slab',
		'minecraft:warped_double_slab' => 'minecraft:warped_slab',
		'minecraft:crimson_pressure_plate' => 'minecraft:wooden_pressure_plate',
		'minecraft:warped_pressure_plate' => 'minecraft:wooden_pressure_plate',
		'minecraft:crimson_hyphae' => 'minecraft:crimson_stem',
		'minecraft:stripped_crimson_hyphae' => 'minecraft:stripped_crimson_stem',
		'minecraft:stripped_warped_hyphae' => 'minecraft:stripped_warped_stem',
		'minecraft:soul_torch' => 'minecraft:torch',
		'minecraft:soul_campfire' => 'minecraft:campfire',
		'minecraft:iron_chain' => 'minecraft:air',
		'minecraft:dead_tube_coral' => 'minecraft:air',
		'minecraft:dead_brain_coral' => 'minecraft:air',
		'minecraft:dead_bubble_coral' => 'minecraft:air',
		'minecraft:unknown' => 'minecraft:info_update',
	];

	protected function registerVersionPackets(PacketRegistry $packets): void
	{
		parent::registerVersionPackets($packets);

		$packets->add(new GameRulesChangedRewriter(self::CODEC_PROTOCOL));
		if ($this->isBefore(Protocol1_16_210::PROTOCOL)) {
			$packets->add(
				new CameraShakeRewriter(),
				new PlayerActionRewriter(self::CODEC_PROTOCOL),
				new BlockBreakTransactionRewriter(self::CODEC_PROTOCOL)
			);
		}
		if ($this->isBefore(Protocol1_16_100::PROTOCOL)) {
			$packets->add(
				new ResourcePackStackRewriter(self::CODEC_PROTOCOL),
				new ContainerCloseRewriter(),
				new MovePlayerRewriter(),
				new MoveActorDeltaRewriter()
			);
		}
	}

	protected function commandArgumentRemaps(): array
	{
		$remaps = parent::commandArgumentRemaps();
		if ($this->isBefore(Protocol1_16_210::PROTOCOL)) {
			$remaps[] = new ArgumentTypeRemap(57, 6, 32);
			$remaps[] = new ArgumentTypeRemap(2, 1, 31);
		}
		if ($this->isBefore(Protocol1_16_100::PROTOCOL)) {
			$remaps[] = new ArgumentTypeRemap(30, 1, 30);
			$remaps[] = new ArgumentTypeRemap(7, 1, 29);
		}

		return $remaps;
	}

	protected function legacyActionTypes(): array
	{
		return match (true) {
			$this->isBefore(Protocol1_16_200::PROTOCOL) => self::ACTION_TYPES_419,
			$this->isBefore(Protocol1_16_210::PROTOCOL) => self::ACTION_TYPES_422,
			default => parent::legacyActionTypes(),
		};
	}

	protected function itemReplacements(): array
	{
		return self::replacements() + parent::itemReplacements();
	}

	protected function blockReplacements(): array
	{
		$legacy = $this->isBefore(Protocol1_16_100::PROTOCOL) ? self::LEGACY_BLOCK_REPLACEMENTS : [];

		return $legacy + self::replacements() + parent::blockReplacements();
	}

	protected function actorOverrides(): array
	{
		return self::ACTOR_OVERRIDES + parent::actorOverrides();
	}

	/**
	 * @return array<string, string>
	 */
	private static function replacements(): array
	{
		return self::REPLACEMENTS + self::cavesAndCliffsVariants();
	}

	/**
	 * @return array<string, string>
	 */
	private static function cavesAndCliffsVariants(): array
	{
		$replacements = [];
		foreach (['copper', 'deepslate'] as $material) {
			foreach (self::shapes() as $suffix => $stone) {
				foreach (self::variants($material) as $name) {
					$full = 'minecraft:' . $name . $suffix;
					$replacements[$full] = match (true) {
						$material !== 'copper' => $stone,
						str_contains($name, 'double_cut_copper') => 'minecraft:brick_double_slab',
						default => self::copperShape($suffix),
					};
				}
			}
		}

		return $replacements;
	}

	/**
	 * @return array<string, string>
	 */
	private static function shapes(): array
	{
		return [
			'' => 'minecraft:stone_bricks',
			'_slab' => 'minecraft:stone_brick_slab',
			'_double_slab' => 'minecraft:stone_brick_double_slab',
			'_stairs' => 'minecraft:stone_brick_stairs',
			'_wall' => 'minecraft:cobblestone_wall',
		];
	}

	/**
	 * @return list<string>
	 */
	private static function variants(string $material): array
	{
		if ($material === 'deepslate') {
			return ['cobbled_deepslate', 'polished_deepslate', 'deepslate_brick', 'deepslate_tile'];
		}

		$variants = [];
		foreach (['', 'exposed_', 'weathered_', 'oxidized_'] as $age) {
			foreach (['', 'waxed_'] as $wax) {
				$variants[] = $wax . $age . 'copper';
				$variants[] = $wax . $age . 'cut_copper';
			}
		}
		foreach (['', 'exposed_', 'weathered_', 'oxidized_'] as $age) {
			foreach (['', 'waxed_'] as $wax) {
				$variants[] = $wax . $age . 'double_cut_copper';
			}
		}

		return $variants;
	}

	private static function copperShape(string $suffix): string
	{
		return match ($suffix) {
			'_slab' => 'minecraft:brick_slab',
			'_double_slab' => 'minecraft:brick_double_slab',
			'_stairs' => 'minecraft:brick_stairs',
			'_wall' => 'minecraft:brick_wall',
			default => 'minecraft:orange_terracotta',
		};
	}
}
