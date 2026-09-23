<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_80;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketRegistry;
use sova\multiprotocol\protocol\Protocol;
use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\protocol\v1_19_80\rewriter\EmoteRewriter;
use sova\multiprotocol\protocol\v1_19_80\rewriter\SmithingTrimRewriter;
use sova\multiprotocol\protocol\v1_19_80\rewriter\StartGameRewriter;
use sova\multiprotocol\protocol\v1_19_80\rewriter\UnlockedRecipesRewriter;
use sova\multiprotocol\translation\actor\ActorIdentifiers;
use sova\multiprotocol\translation\ProtocolData;
use sova\multiprotocol\translation\ProtocolMappings;
use sova\multiprotocol\translation\rewriter\StandardRewriters;
use sova\multiprotocol\translation\TranslationContext;
use Symfony\Component\Filesystem\Path;

final class Protocol1_19_80 extends Protocol
{
	public const int PROTOCOL = 582;
	public const string VERSION = '1.19.80';

	private const int ITEM_SCHEMA_ID = 101;
	private const string ACTOR_IDENTIFIERS_FILE = 'entity_identifiers.nbt';

	private const array POTTERY_PATTERNS = [
		'angler', 'archer', 'arms_up', 'blade', 'brewer', 'burn', 'danger', 'explorer', 'friend', 'heart',
		'heartbreak', 'howl', 'miner', 'mourner', 'plenty', 'prize', 'sheaf', 'shelter', 'skull', 'snort',
	];

	private const array ACTOR_OVERRIDES = [
		EntityIds::CAMEL => EntityIds::HORSE,
		EntityIds::SNIFFER => EntityIds::PIG,
	];

	private ?ProtocolData $clientData = null;
	private ?ProtocolMappings $mappings = null;

	public function __construct(
		private readonly string $dataPath
	) {
		parent::__construct(new ProtocolVersion(self::PROTOCOL, self::VERSION), ProtocolInfo::PROTOCOL_1_20_0);
	}

	public function load(): void
	{
		$this->getPackets();
	}

	protected function registerPackets(PacketRegistry $packets): void
	{
		$codec = $this->targetProtocolId;
		$context = new TranslationContext($this->mappings(), $codec);
		$identifiers = ActorIdentifiers::load(Path::join($this->dataPath, self::ACTOR_IDENTIFIERS_FILE), self::ACTOR_OVERRIDES);

		$packets
			->add(
				new StartGameRewriter($context->mappings->items->clientDictionary, $codec),
				new EmoteRewriter(),
				new UnlockedRecipesRewriter(),
				new SmithingTrimRewriter($codec)
			)
			->add(...StandardRewriters::blocks($context))
			->add(...StandardRewriters::items($context))
			->add(...StandardRewriters::actors($identifiers, $codec))
			->cancel(Direction::CLIENTBOUND, ProtocolInfo::CAMERA_PRESETS_PACKET, ProtocolInfo::CAMERA_INSTRUCTION_PACKET);
	}

	public function getClientData(): ProtocolData
	{
		return $this->clientData ??= ProtocolData::load($this->dataPath, self::ITEM_SCHEMA_ID);
	}

	private function mappings(): ProtocolMappings
	{
		return $this->mappings ??= ProtocolMappings::build(ProtocolData::native($this->targetProtocolId), $this->getClientData(), self::itemRenames());
	}

	/**
	 * @return array<string, string>
	 */
	private static function itemRenames(): array
	{
		$renames = [];
		foreach (self::POTTERY_PATTERNS as $pattern) {
			$renames['minecraft:' . $pattern . '_pottery_sherd'] = 'minecraft:' . $pattern . '_pottery_shard';
		}

		return $renames;
	}
}
