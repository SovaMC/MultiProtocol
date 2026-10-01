<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19;

use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\Filesystem;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketRegistry;
use sova\multiprotocol\protocol\Protocol;
use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\v1_16_100\Protocol1_16_100;
use sova\multiprotocol\protocol\v1_16_200\Protocol1_16_200;
use sova\multiprotocol\protocol\v1_16_210\Protocol1_16_210;
use sova\multiprotocol\protocol\v1_16_220\Protocol1_16_220;
use sova\multiprotocol\protocol\v1_17_30\Protocol1_17_30;
use sova\multiprotocol\protocol\v1_18_0\Protocol1_18_0;
use sova\multiprotocol\protocol\v1_19\rewriter\ActorFlagsRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\AddActorRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\AddPlayerRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\AdventureSettingsRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\AvailableCommandsRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\ClientboundMapItemDataRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\CommandRequestRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\CraftingDataRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\EmoteRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\ItemStackRequestRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\ItemStackResponseRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\MapInfoRequestRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\ModalFormResponseRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\NetworkChunkPublisherUpdateRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\PlayerAuthInputRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\PlayerListRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\PlayerSkinRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\RequestChunkRadiusRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\SetActorDataRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\SmithingRecipesRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\StartGameRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\StructureBlockUpdateRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\TextRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\UnlockedRecipesRewriter;
use sova\multiprotocol\protocol\v1_19\rewriter\UpdateAttributesRewriter;
use sova\multiprotocol\protocol\v1_19_0\Protocol1_19_0;
use sova\multiprotocol\protocol\v1_19_10\Protocol1_19_10;
use sova\multiprotocol\protocol\v1_19_20\Protocol1_19_20;
use sova\multiprotocol\protocol\v1_19_30\Protocol1_19_30;
use sova\multiprotocol\protocol\v1_19_40\Protocol1_19_40;
use sova\multiprotocol\protocol\v1_19_50\Protocol1_19_50;
use sova\multiprotocol\protocol\v1_19_60\Protocol1_19_60;
use sova\multiprotocol\protocol\v1_19_63\Protocol1_19_63;
use sova\multiprotocol\protocol\v1_19_70\Protocol1_19_70;
use sova\multiprotocol\protocol\v1_19_80\Protocol1_19_80;
use sova\multiprotocol\translation\ability\AbilitiesFilter;
use sova\multiprotocol\translation\actor\ActorIdentifiers;
use sova\multiprotocol\translation\block\BiomeTranslator;
use sova\multiprotocol\translation\block\LegacySignTranslator;
use sova\multiprotocol\translation\command\ArgumentTypeRemap;
use sova\multiprotocol\translation\entity\EntityFlagsTranslator;
use sova\multiprotocol\translation\inventory\ContainerSlotTranslator;
use sova\multiprotocol\translation\inventory\LegacyItemStackRequestReader;
use sova\multiprotocol\translation\inventory\LegacyTransactionReader;
use sova\multiprotocol\translation\item\BlockItemRuntimeIds;
use sova\multiprotocol\translation\item\LegacyItemCodec;
use sova\multiprotocol\translation\ProtocolData;
use sova\multiprotocol\translation\ProtocolMappings;
use sova\multiprotocol\translation\rewriter\ability\AddPlayerAbilitiesRewriter;
use sova\multiprotocol\translation\rewriter\ability\UpdateAbilitiesRewriter;
use sova\multiprotocol\translation\rewriter\block\SignEditRewriter;
use sova\multiprotocol\translation\rewriter\StandardRewriters;
use sova\multiprotocol\translation\skin\SkinFormat;
use sova\multiprotocol\translation\skin\SkinLayout;
use sova\multiprotocol\translation\TranslationContext;

abstract class Protocol1_19 extends Protocol
{
	protected const int CODEC_PROTOCOL = ProtocolInfo::PROTOCOL_1_20_0;

	private const int PHOTO_INFO_REQUEST_PACKET = 0xad;
	private const int ADVENTURE_SETTINGS_PACKET = 0x37;
	private const int RECIPE_BOOK_CONTAINER = 21;
	private const int CAN_DASH_FLAG = 46;

	private const array BIOME_REPLACEMENTS = [
		193 => 29,
	];

	private const array CHERRY_GROVE_REPLACEMENT = [
		192 => 186,
	];

	private const array WILD_UPDATE_BIOME_REPLACEMENTS = [
		190 => 188,
		191 => 6,
	];

	private const int PERMISSION_ARGUMENT_TYPES = 32;
	private const int PERMISSION_ARGUMENT_TYPE_COUNT = 5;
	private const int STRING_ARGUMENT_TYPE = 39;

	private const array REMOVED_ABILITIES = [
		AbilitiesLayer::ABILITY_PRIVILEGED_BUILDER,
	];

	private const array ACTOR_OVERRIDES = [
		EntityIds::CAMEL => EntityIds::HORSE,
		EntityIds::SNIFFER => EntityIds::PIG,
	];

	private const array POTTERY_PATTERNS = [
		'angler', 'archer', 'arms_up', 'blade', 'brewer', 'burn', 'danger', 'explorer', 'friend', 'heart',
		'heartbreak', 'howl', 'miner', 'mourner', 'plenty', 'prize', 'sheaf', 'shelter', 'skull', 'snort',
	];

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
		'minecraft:torchflower_crop' => 'minecraft:wheat',
		'minecraft:pitcher_crop' => 'minecraft:wheat',
	];

	private const array EXPERIMENTAL_REPLACEMENTS = [
		'minecraft:bamboo_planks' => 'minecraft:oak_planks',
		'minecraft:bamboo_mosaic' => 'minecraft:oak_planks',
		'minecraft:bamboo_block' => 'minecraft:hay_block',
		'minecraft:stripped_bamboo_block' => 'minecraft:hay_block',
		'minecraft:bamboo_stairs' => 'minecraft:oak_stairs',
		'minecraft:bamboo_mosaic_stairs' => 'minecraft:oak_stairs',
		'minecraft:bamboo_slab' => 'minecraft:oak_slab',
		'minecraft:bamboo_mosaic_slab' => 'minecraft:oak_slab',
		'minecraft:bamboo_double_slab' => 'minecraft:oak_double_slab',
		'minecraft:bamboo_mosaic_double_slab' => 'minecraft:oak_double_slab',
		'minecraft:bamboo_fence' => 'minecraft:oak_fence',
		'minecraft:bamboo_fence_gate' => 'minecraft:fence_gate',
		'minecraft:bamboo_door' => 'minecraft:wooden_door',
		'minecraft:bamboo_trapdoor' => 'minecraft:trapdoor',
		'minecraft:bamboo_button' => 'minecraft:wooden_button',
		'minecraft:bamboo_pressure_plate' => 'minecraft:wooden_pressure_plate',
		'minecraft:bamboo_standing_sign' => 'minecraft:standing_sign',
		'minecraft:bamboo_wall_sign' => 'minecraft:wall_sign',
		'minecraft:bamboo_sign' => 'minecraft:oak_sign',
		'minecraft:bamboo_raft' => 'minecraft:oak_boat',
		'minecraft:bamboo_chest_raft' => 'minecraft:oak_chest_boat',
		'minecraft:chiseled_bookshelf' => 'minecraft:bookshelf',
		'minecraft:suspicious_sand' => 'minecraft:sand',
		'minecraft:decorated_pot' => 'minecraft:flower_pot',
		'minecraft:torchflower' => 'minecraft:dandelion',
		'minecraft:pitcher_plant' => 'minecraft:sunflower',
		'minecraft:sniffer_egg' => 'minecraft:turtle_egg',
	];

	private const array HANGING_SIGN_WOODS = [
		'oak', 'spruce', 'birch', 'jungle', 'acacia', 'dark_oak', 'mangrove', 'cherry', 'bamboo', 'crimson', 'warped',
	];

	/** @var array<string, ProtocolMappings> */
	private static array $sharedMappings = [];

	private ?ProtocolData $clientData = null;

	public function __construct(
		ProtocolVersion $version,
		protected readonly ProtocolResources $resources
	) {
		parent::__construct($version, self::CODEC_PROTOCOL);
	}

	public function load(): void
	{
		$this->getPackets();
	}

	public function getClientData(): ProtocolData
	{
		return $this->clientData ??= ProtocolData::fromFiles(
			$this->resources->getBlockPaletteFile(),
			$this->resources->getItemTableFile(),
			$this->resources->itemSchemaId
		);
	}

	protected function registerPackets(PacketRegistry $packets): void
	{
		$legacyItems = $this->isBefore(Protocol1_16_220::PROTOCOL) ? new LegacyItemCodec($this->getClientData()->items->fromStringId('minecraft:shield')) : null;
		$legacyTransactions = $legacyItems !== null ? new LegacyTransactionReader($legacyItems, self::CODEC_PROTOCOL, !$this->isBefore(Protocol1_16_210::PROTOCOL)) : null;
		$context = new TranslationContext(
			$this->mappings(),
			self::CODEC_PROTOCOL,
			$this->isBefore(Protocol1_19_80::PROTOCOL) ? new LegacySignTranslator() : null,
			new BiomeTranslator($this->biomeReplacements()),
			$this->isBefore(Protocol1_18_0::PROTOCOL),
			$legacyItems !== null ? BlockItemRuntimeIds::native(self::CODEC_PROTOCOL) : null,
			$legacyItems,
			$legacyTransactions
		);
		$identifiers = ActorIdentifiers::load($this->resources->getActorIdentifiersFile(), $this->actorOverrides());

		$this->registerServerbound($packets, $context);

		$packets
			->add(
				new StartGameRewriter(
					$context->mappings->items->clientDictionary,
					$this->version->id,
					self::CODEC_PROTOCOL,
					$this->isBefore(Protocol1_16_100::PROTOCOL) ? Filesystem::fileGetContents($this->resources->getStartGamePaletteFile()) : null
				),
				new EmoteRewriter(),
				new SmithingRecipesRewriter(!$this->isBefore(Protocol1_19_80::PROTOCOL), self::CODEC_PROTOCOL)
			)
			->add(...StandardRewriters::blocks($context))
			->add(...StandardRewriters::items($context))
			->add(...StandardRewriters::actors($identifiers, self::CODEC_PROTOCOL));

		if ($this->isBefore(Protocol1_19_80::PROTOCOL)) {
			$packets->add(new SignEditRewriter(self::CODEC_PROTOCOL));
		}

		$this->registerClientbound($packets, $context);

		$packets->cancel(Direction::CLIENTBOUND, ProtocolInfo::CAMERA_PRESETS_PACKET, ProtocolInfo::CAMERA_INSTRUCTION_PACKET);

		$this->cancelMissingPackets($packets);
		$this->registerVersionPackets($packets);
	}

	protected function registerVersionPackets(PacketRegistry $packets): void
	{
	}

	/**
	 * @return array<string, string>
	 */
	protected function actorOverrides(): array
	{
		return self::ACTOR_OVERRIDES;
	}

	/**
	 * @return list<ArgumentTypeRemap>
	 */
	protected function commandArgumentRemaps(): array
	{
		if (!$this->isBefore(Protocol1_19_80::PROTOCOL)) {
			return [];
		}

		return [new ArgumentTypeRemap(self::PERMISSION_ARGUMENT_TYPES, self::PERMISSION_ARGUMENT_TYPE_COUNT, self::STRING_ARGUMENT_TYPE)];
	}

	/**
	 * @return array<int, int>
	 */
	protected function biomeReplacements(): array
	{
		$replacements = self::BIOME_REPLACEMENTS;
		if ($this->isBefore(Protocol1_19_80::PROTOCOL)) {
			$replacements += self::CHERRY_GROVE_REPLACEMENT;
		}
		if ($this->isBefore(Protocol1_19_0::PROTOCOL)) {
			$replacements += self::WILD_UPDATE_BIOME_REPLACEMENTS;
		}

		return $replacements;
	}

	/**
	 * @return array<int, int>
	 */
	protected function legacyActionTypes(): array
	{
		return [];
	}

	protected function isBefore(int $protocolId): bool
	{
		return $this->version->id < $protocolId;
	}

	private function registerServerbound(PacketRegistry $packets, TranslationContext $context): void
	{
		$slots = $this->isBefore(Protocol1_19_50::PROTOCOL) ? new ContainerSlotTranslator(self::RECIPE_BOOK_CONTAINER) : null;
		$requests = $this->isBefore(Protocol1_19_50::PROTOCOL) ? new LegacyItemStackRequestReader(
			self::CODEC_PROTOCOL,
			$this->isBefore(Protocol1_19_30::PROTOCOL),
			!$this->isBefore(Protocol1_16_200::PROTOCOL),
			!$this->isBefore(Protocol1_19_40::PROTOCOL),
			$slots,
			$this->legacyActionTypes(),
			$context->legacyItems
		) : null;

		if ($this->isBefore(Protocol1_19_70::PROTOCOL)) {
			$packets->add(new PlayerAuthInputRewriter(
				self::CODEC_PROTOCOL,
				$requests,
				!$this->isBefore(Protocol1_19_0::PROTOCOL),
				!$this->isBefore(Protocol1_16_100::PROTOCOL),
				!$this->isBefore(Protocol1_16_210::PROTOCOL),
				$context->legacyTransactions
			));
		}
		if ($this->isBefore(Protocol1_19_80::PROTOCOL)) {
			$packets
				->add(new RequestChunkRadiusRewriter())
				->cancel(Direction::SERVERBOUND, self::PHOTO_INFO_REQUEST_PACKET);
		}
		if ($requests !== null) {
			$packets->add(new ItemStackRequestRewriter($requests, self::CODEC_PROTOCOL));
		}
		if ($this->isBefore(Protocol1_19_60::PROTOCOL)) {
			$packets->add(new CommandRequestRewriter());
		}
		if ($this->isBefore(Protocol1_19_30::PROTOCOL)) {
			$packets->add(new StructureBlockUpdateRewriter());
		}
		if ($this->isBefore(Protocol1_19_20::PROTOCOL)) {
			$packets->add(new MapInfoRequestRewriter(), new ModalFormResponseRewriter());
		}
		if ($this->isBefore(Protocol1_19_10::PROTOCOL)) {
			$packets->cancel(Direction::SERVERBOUND, self::ADVENTURE_SETTINGS_PACKET);
		}
	}

	private function registerClientbound(PacketRegistry $packets, TranslationContext $context): void
	{
		$remaps = $this->commandArgumentRemaps();
		if ($remaps !== []) {
			$packets->add(new AvailableCommandsRewriter(self::CODEC_PROTOCOL, ...$remaps));
		}

		if ($this->isBefore(Protocol1_19_70::PROTOCOL)) {
			$packets->cancel(Direction::CLIENTBOUND, ProtocolInfo::UNLOCKED_RECIPES_PACKET);
		} else {
			$packets->add(new UnlockedRecipesRewriter());
		}

		$abilities = new AbilitiesFilter(self::REMOVED_ABILITIES);
		if (!$this->isBefore(Protocol1_19_10::PROTOCOL) && $this->isBefore(Protocol1_19_70::PROTOCOL)) {
			$packets->add(
				new UpdateAbilitiesRewriter($abilities, self::CODEC_PROTOCOL),
				new AddPlayerAbilitiesRewriter($abilities, self::CODEC_PROTOCOL)
			);
		}

		if ($this->isBefore(Protocol1_19_63::PROTOCOL)) {
			$skins = new SkinFormat(self::CODEC_PROTOCOL, $this->skinLayout());
			$packets->add(
				new PlayerListRewriter($skins, self::CODEC_PROTOCOL),
				new PlayerSkinRewriter($skins, self::CODEC_PROTOCOL)
			);
		}

		if ($this->isBefore(Protocol1_19_50::PROTOCOL)) {
			$flags = new EntityFlagsTranslator([self::CAN_DASH_FLAG]);
			$packets->add(
				new ActorFlagsRewriter(AddActorPacket::class, $flags, self::CODEC_PROTOCOL),
				new ActorFlagsRewriter(AddPlayerPacket::class, $flags, self::CODEC_PROTOCOL),
				new ActorFlagsRewriter(AddItemActorPacket::class, $flags, self::CODEC_PROTOCOL),
				new ActorFlagsRewriter(SetActorDataPacket::class, $flags, self::CODEC_PROTOCOL),
				new ItemStackResponseRewriter(new ContainerSlotTranslator(self::RECIPE_BOOK_CONTAINER), $this->version->id, self::CODEC_PROTOCOL)
			);
		}

		if ($this->isBefore(Protocol1_19_40::PROTOCOL)) {
			$packets->add(
				new AddActorRewriter($this->version->id, self::CODEC_PROTOCOL),
				new SetActorDataRewriter(self::CODEC_PROTOCOL, !$this->isBefore(Protocol1_16_100::PROTOCOL)),
				new AddPlayerRewriter($this->version->id, self::CODEC_PROTOCOL, $context->legacyItems)
			);
		}

		if ($this->isBefore(Protocol1_19_30::PROTOCOL)) {
			$packets->add(
				new CraftingDataRewriter($context->mappings->items->withoutRenames(), !$this->isBefore(Protocol1_17_30::PROTOCOL), self::CODEC_PROTOCOL, $context->legacyItems),
				new TextRewriter()
			);
		}

		if ($this->isBefore(Protocol1_19_20::PROTOCOL)) {
			$packets->add(
				new NetworkChunkPublisherUpdateRewriter(),
				new UpdateAttributesRewriter(self::CODEC_PROTOCOL, !$this->isBefore(Protocol1_16_100::PROTOCOL)),
				new ClientboundMapItemDataRewriter()
			);
		}

		if ($this->isBefore(Protocol1_19_10::PROTOCOL)) {
			$packets->add(...AdventureSettingsRewriter::create(self::CODEC_PROTOCOL));
		}
	}

	protected function skinLayout(): SkinLayout
	{
		if ($this->isBefore(Protocol1_16_100::PROTOCOL)) {
			return SkinLayout::LEGACY_NO_PLAYFAB_NO_EXPRESSION;
		}
		if ($this->isBefore(Protocol1_16_210::PROTOCOL)) {
			return SkinLayout::LEGACY_NO_PLAYFAB;
		}

		return $this->isBefore(Protocol1_17_30::PROTOCOL) ? SkinLayout::LEGACY : SkinLayout::NO_OVERRIDE;
	}

	private function cancelMissingPackets(PacketRegistry $packets): void
	{
		$missing = [
			Protocol1_19_80::PROTOCOL => [ProtocolInfo::OPEN_SIGN_PACKET, ProtocolInfo::TRIM_DATA_PACKET, ProtocolInfo::COMPRESSED_BIOME_DEFINITION_LIST_PACKET],
			Protocol1_19_50::PROTOCOL => [ProtocolInfo::UPDATE_CLIENT_INPUT_LOCKS_PACKET],
			Protocol1_19_30::PROTOCOL => [ProtocolInfo::SERVER_STATS_PACKET],
			Protocol1_19_20::PROTOCOL => [ProtocolInfo::FEATURE_REGISTRY_PACKET],
			Protocol1_19_10::PROTOCOL => [ProtocolInfo::DEATH_INFO_PACKET, ProtocolInfo::EDITOR_NETWORK_PACKET],
		];

		foreach ($missing as $protocolId => $ids) {
			if ($this->isBefore($protocolId)) {
				$packets->cancel(Direction::CLIENTBOUND, ...$ids);
			}
		}
	}

	private function mappings(): ProtocolMappings
	{
		return self::$sharedMappings[$this->resources->getMappingsKey()] ??= ProtocolMappings::build(
			ProtocolData::native(self::CODEC_PROTOCOL),
			$this->getClientData(),
			$this->itemReplacements(),
			$this->blockReplacements(),
			self::itemAliases()
		);
	}

	/**
	 * @return array<string, string>
	 */
	protected function itemReplacements(): array
	{
		return $this->hangingSignReplacements('minecraft:oak_sign') + self::cherryReplacements() + self::REPLACEMENTS + self::EXPERIMENTAL_REPLACEMENTS;
	}

	/**
	 * @return array<string, string>
	 */
	protected function blockReplacements(): array
	{
		return $this->hangingSignReplacements('minecraft:standing_sign') + self::cherryReplacements() + self::REPLACEMENTS + self::BLOCK_ONLY_REPLACEMENTS + self::EXPERIMENTAL_REPLACEMENTS;
	}

	/**
	 * @return array<string, string>
	 */
	private function hangingSignReplacements(string $replacement): array
	{
		if (!$this->isBefore(Protocol1_19_50::PROTOCOL)) {
			return [];
		}

		$replacements = [];
		foreach (self::HANGING_SIGN_WOODS as $wood) {
			$replacements['minecraft:' . $wood . '_hanging_sign'] = $replacement;
		}

		return $replacements;
	}

	/**
	 * @return array<string, string>
	 */
	private static function itemAliases(): array
	{
		$replacements = [];
		foreach (self::POTTERY_PATTERNS as $pattern) {
			$replacements['minecraft:' . $pattern . '_pottery_sherd'] = 'minecraft:' . $pattern . '_pottery_shard';
		}

		return $replacements;
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
