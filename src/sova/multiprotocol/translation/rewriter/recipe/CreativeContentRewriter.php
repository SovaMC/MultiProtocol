<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\recipe;

use pocketmine\network\mcpe\protocol\CreativeContentPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeItemEntry;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;

/**
 * @extends TypedPacketRewriter<CreativeContentPacket>
 */
final class CreativeContentRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(CreativeContentPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$content = $this->peek($packet);

		$entries = [];
		foreach ($content->getItems() as $entry) {
			$item = $this->context->exactItems->stackOrNull($packet->direction, $entry->getItem());
			if ($item !== null) {
				$entries[] = new CreativeItemEntry($entry->getEntryId(), $item, $entry->getGroupId());
			}
		}

		$groups = $this->codecProtocolId >= ProtocolInfo::PROTOCOL_1_21_60 ? $content->getGroups() : [];

		$this->replace($packet, CreativeContentPacket::create($groups, $entries));
	}
}
