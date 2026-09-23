<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_70;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketRegistry;
use sova\multiprotocol\protocol\Protocol;
use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\v1_19_70\rewriter\AvailableCommandsRewriter;
use sova\multiprotocol\protocol\v1_19_70\rewriter\RequestChunkRadiusRewriter;
use sova\multiprotocol\protocol\v1_19_70\rewriter\SmithingRecipesRewriter;
use sova\multiprotocol\protocol\v1_19_70\rewriter\StartGameRewriter;
use sova\multiprotocol\protocol\v1_19_80\Protocol1_19_80;
use sova\multiprotocol\translation\actor\ActorIdentifiers;
use sova\multiprotocol\translation\block\LegacySignTranslator;
use sova\multiprotocol\translation\ProtocolData;
use sova\multiprotocol\translation\ProtocolMappings;
use sova\multiprotocol\translation\rewriter\StandardRewriters;
use sova\multiprotocol\translation\TranslationContext;
use Symfony\Component\Filesystem\Path;

final class Protocol1_19_70 extends Protocol
{
	public const int PROTOCOL = 575;
	public const string VERSION = '1.19.70';

	private const int ITEM_SCHEMA_ID = 91;
	private const int CODEC_PROTOCOL = ProtocolInfo::PROTOCOL_1_20_0;
	private const int PHOTO_INFO_REQUEST_PACKET = 0xad;
	private const string ACTOR_IDENTIFIERS_FILE = 'entity_identifiers.nbt';

	private const array CHERRY_VARIANTS = [
		'button', 'door', 'double_slab', 'fence', 'fence_gate', 'hanging_sign', 'leaves', 'log', 'planks',
		'pressure_plate', 'sapling', 'sign', 'slab', 'stairs', 'standing_sign', 'trapdoor', 'wall_sign', 'wood',
	];

	private const array REPLACEMENTS = [
		'minecraft:suspicious_gravel' => 'minecraft:gravel',
		'minecraft:calibrated_sculk_sensor' => 'minecraft:sculk_sensor',
	];

	private const array BLOCK_ONLY_REPLACEMENTS = [
		'minecraft:pink_petals' => 'minecraft:air',
	];

	private ?ProtocolData $clientData = null;
	private ?ProtocolMappings $mappings = null;

	public function __construct(
		private readonly string $dataPath,
		private readonly Protocol1_19_80 $target
	) {
		parent::__construct(new ProtocolVersion(self::PROTOCOL, self::VERSION), Protocol1_19_80::PROTOCOL);
	}

	public function load(): void
	{
		$this->getPackets();
	}

	public function getClientData(): ProtocolData
	{
		return $this->clientData ??= ProtocolData::load($this->dataPath, self::ITEM_SCHEMA_ID);
	}

	protected function registerPackets(PacketRegistry $packets): void
	{
		$context = new TranslationContext($this->mappings(), self::CODEC_PROTOCOL, new LegacySignTranslator());
		$identifiers = ActorIdentifiers::load(Path::join($this->dataPath, self::ACTOR_IDENTIFIERS_FILE));

		$packets
			->add(
				new StartGameRewriter($context->mappings->items->clientDictionary, self::CODEC_PROTOCOL),
				new RequestChunkRadiusRewriter(),
				new AvailableCommandsRewriter(self::CODEC_PROTOCOL),
				new SmithingRecipesRewriter(self::CODEC_PROTOCOL)
			)
			->add(...StandardRewriters::blocks($context))
			->add(...StandardRewriters::items($context))
			->add(...StandardRewriters::actors($identifiers, self::CODEC_PROTOCOL))
			->cancel(
				Direction::CLIENTBOUND,
				ProtocolInfo::OPEN_SIGN_PACKET,
				ProtocolInfo::TRIM_DATA_PACKET,
				ProtocolInfo::COMPRESSED_BIOME_DEFINITION_LIST_PACKET
			)
			->cancel(Direction::SERVERBOUND, self::PHOTO_INFO_REQUEST_PACKET);
	}

	private function mappings(): ProtocolMappings
	{
		return $this->mappings ??= ProtocolMappings::build(
			$this->target->getClientData(),
			$this->getClientData(),
			self::itemReplacements(),
			self::blockReplacements()
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function itemReplacements(): array
	{
		return self::cherryReplacements() + self::REPLACEMENTS;
	}

	/**
	 * @return array<string, string>
	 */
	private static function blockReplacements(): array
	{
		return self::cherryReplacements() + self::REPLACEMENTS + self::BLOCK_ONLY_REPLACEMENTS;
	}

	/**
	 * @return array<string, string>
	 */
	private static function cherryReplacements(): array
	{
		$replacements = [
			'minecraft:stripped_cherry_log' => 'minecraft:stripped_birch_log',
			'minecraft:stripped_cherry_wood' => 'minecraft:stripped_birch_wood',
		];
		foreach (self::CHERRY_VARIANTS as $variant) {
			$replacements['minecraft:cherry_' . $variant] = 'minecraft:birch_' . $variant;
		}

		return $replacements;
	}
}
