<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_17;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketRegistry;
use sova\multiprotocol\protocol\v1_17\rewriter\ActorPickRequestRewriter;
use sova\multiprotocol\protocol\v1_17\rewriter\AnimateEntityRewriter;
use sova\multiprotocol\protocol\v1_17\rewriter\AvailableCommandsRewriter;
use sova\multiprotocol\protocol\v1_17\rewriter\HurtArmorRewriter;
use sova\multiprotocol\protocol\v1_17\rewriter\NpcRequestRewriter;
use sova\multiprotocol\protocol\v1_17\rewriter\ParticleRewriter;
use sova\multiprotocol\protocol\v1_17\rewriter\ResourcePacksInfoRewriter;
use sova\multiprotocol\protocol\v1_17\rewriter\SetTitleRewriter;
use sova\multiprotocol\protocol\v1_17_10\Protocol1_17_10;
use sova\multiprotocol\protocol\v1_17_30\Protocol1_17_30;
use sova\multiprotocol\protocol\v1_17_40\Protocol1_17_40;
use sova\multiprotocol\protocol\v1_18\Protocol1_18;

abstract class Protocol1_17 extends Protocol1_18
{
	private const array LEGACY_ACTION_TYPES = [
		7 => 9,
		8 => 10,
		9 => 11,
		10 => 12,
		11 => 13,
		12 => 14,
		13 => 15,
		14 => 18,
		15 => 19,
	];

	private const array BIOME_REPLACEMENTS = [
		182 => 3,
		183 => 13,
		184 => 12,
		185 => 30,
		186 => 1,
		187 => 4,
		188 => 3,
		189 => 3,
	];

	private const array CANDLE_COLORS = [
		'white', 'orange', 'magenta', 'light_blue', 'yellow', 'lime', 'pink', 'gray',
		'light_gray', 'cyan', 'purple', 'blue', 'brown', 'green', 'red', 'black',
	];

	private const array REPLACEMENTS = [
		'minecraft:candle' => 'minecraft:torch',
		'minecraft:candle_cake' => 'minecraft:cake',
		'minecraft:sculk' => 'minecraft:black_concrete',
		'minecraft:sculk_vein' => 'minecraft:air',
		'minecraft:sculk_catalyst' => 'minecraft:sculk_sensor',
		'minecraft:sculk_shrieker' => 'minecraft:sculk_sensor',
		'minecraft:reinforced_deepslate' => 'minecraft:deepslate',
		'minecraft:client_request_placeholder_block' => 'minecraft:air',
	];

	protected function registerVersionPackets(PacketRegistry $packets): void
	{
		parent::registerVersionPackets($packets);

		if ($this->isBefore(Protocol1_17_30::PROTOCOL)) {
			$packets
				->add(
					new HurtArmorRewriter(),
					new AnimateEntityRewriter(),
					new ActorPickRequestRewriter()
				)
				->cancel(Direction::CLIENTBOUND, ProtocolInfo::EDUCATION_SETTINGS_PACKET, ProtocolInfo::PHOTO_TRANSFER_PACKET);
		}

		if ($this->isBefore(Protocol1_17_10::PROTOCOL)) {
			$packets->add(
				new AvailableCommandsRewriter(self::CODEC_PROTOCOL),
				new SetTitleRewriter(),
				new ResourcePacksInfoRewriter(),
				new NpcRequestRewriter(),
				new ParticleRewriter(self::CODEC_PROTOCOL)
			);
		}
	}

	protected function itemReplacements(): array
	{
		return parent::itemReplacements() + self::replacements();
	}

	protected function blockReplacements(): array
	{
		return parent::blockReplacements() + self::replacements();
	}

	protected function biomeReplacements(): array
	{
		return self::BIOME_REPLACEMENTS + parent::biomeReplacements();
	}

	protected function legacyActionTypes(): array
	{
		return $this->isBefore(Protocol1_17_40::PROTOCOL) ? self::LEGACY_ACTION_TYPES : parent::legacyActionTypes();
	}

	/**
	 * @return array<string, string>
	 */
	private static function replacements(): array
	{
		$replacements = self::REPLACEMENTS;
		foreach (self::CANDLE_COLORS as $color) {
			$replacements['minecraft:' . $color . '_candle'] = 'minecraft:torch';
			$replacements['minecraft:' . $color . '_candle_cake'] = 'minecraft:cake';
		}

		return $replacements;
	}
}
