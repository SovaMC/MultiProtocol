<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\recipe;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\CreativeContentPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeItemEntry;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\TranslationContext;
use function count;

/**
 * @extends TypedPacketRewriter<CreativeContentPacket>
 */
final class CreativeContentRewriter extends TypedPacketRewriter
{
	private const string PLACEHOLDER_ITEM = 'minecraft:info_update';

	private readonly int $placeholderId;

	public function __construct(
		private readonly TranslationContext $context
	) {
		parent::__construct(CreativeContentPacket::class, $context->codecProtocolId, Direction::CLIENTBOUND);
		$this->placeholderId = $context->mappings->items->serverDictionary->fromStringId(self::PLACEHOLDER_ITEM);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$content = $this->peek($packet);

		$entries = [];
		foreach ($content->getItems() as $entry) {
			if ($entry->getItem()->getId() === $this->placeholderId) {
				continue;
			}
			$item = $this->context->exactItems->stackOrNull($packet->direction, $entry->getItem());
			if ($item !== null) {
				$entries[] = new CreativeItemEntry($entry->getEntryId(), $item, $entry->getGroupId());
			}
		}

		$groups = $this->codecProtocolId >= ProtocolInfo::PROTOCOL_1_21_60 ? $content->getGroups() : [];

		$this->replace($packet, CreativeContentPacket::create($groups, $entries));
		if ($this->context->legacyItems !== null) {
			$out = new ByteBufferWriter();
			VarInt::writeUnsignedInt($out, count($entries));
			foreach ($entries as $entry) {
				VarInt::writeUnsignedInt($out, $entry->getEntryId());
				$this->context->legacyItems->write($out, $entry->getItem());
			}
			$packet->replacePayload($out->getData());
		}
	}
}
