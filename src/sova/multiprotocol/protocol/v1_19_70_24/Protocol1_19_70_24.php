<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_70_24;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use sova\multiprotocol\packet\PacketRegistry;
use sova\multiprotocol\protocol\Protocol;
use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\v1_19_70\Protocol1_19_70;
use sova\multiprotocol\translation\ability\AbilitiesFilter;
use sova\multiprotocol\translation\rewriter\ability\AddPlayerAbilitiesRewriter;
use sova\multiprotocol\translation\rewriter\ability\UpdateAbilitiesRewriter;
use sova\multiprotocol\translation\rewriter\recipe\HiddenCreativeItemsRewriter;

final class Protocol1_19_70_24 extends Protocol
{
	public const int PROTOCOL = 574;
	public const string VERSION = '1.19.70.24';

	private const int CODEC_PROTOCOL = ProtocolInfo::PROTOCOL_1_20_0;

	private const array REMOVED_ABILITIES = [
		AbilitiesLayer::ABILITY_PRIVILEGED_BUILDER,
	];

	private const array HIDDEN_ITEMS = [
		'minecraft:torchflower_seeds',
	];

	public function __construct(
		private readonly Protocol1_19_70 $target
	) {
		parent::__construct(new ProtocolVersion(self::PROTOCOL, self::VERSION), Protocol1_19_70::PROTOCOL);
	}

	public function load(): void
	{
		$this->getPackets();
	}

	protected function registerPackets(PacketRegistry $packets): void
	{
		$abilities = new AbilitiesFilter(self::REMOVED_ABILITIES);

		$packets->add(
			new UpdateAbilitiesRewriter($abilities, self::CODEC_PROTOCOL),
			new AddPlayerAbilitiesRewriter($abilities, self::CODEC_PROTOCOL),
			new HiddenCreativeItemsRewriter($this->hiddenItemIds(), self::CODEC_PROTOCOL)
		);
	}

	/**
	 * @return list<int>
	 */
	private function hiddenItemIds(): array
	{
		$items = $this->target->getClientData()->items;

		$ids = [];
		foreach (self::HIDDEN_ITEMS as $name) {
			$ids[] = $items->fromStringId($name);
		}

		return $ids;
	}
}
