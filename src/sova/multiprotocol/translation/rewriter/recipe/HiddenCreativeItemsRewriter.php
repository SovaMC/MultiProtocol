<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\rewriter\recipe;

use pocketmine\network\mcpe\protocol\CreativeContentPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeItemEntry;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use function array_fill_keys;
use function array_filter;
use function array_values;

/**
 * @extends TypedPacketRewriter<CreativeContentPacket>
 */
final class HiddenCreativeItemsRewriter extends TypedPacketRewriter
{
	/** @var array<int, true> */
	private readonly array $hidden;

	/**
	 * @param list<int> $hiddenItemIds
	 */
	public function __construct(array $hiddenItemIds, int $codecProtocolId)
	{
		parent::__construct(CreativeContentPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
		$this->hidden = array_fill_keys($hiddenItemIds, true);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$content = $this->peek($packet);
		$entries = array_values(array_filter(
			$content->getItems(),
			fn(CreativeItemEntry $entry) => !isset($this->hidden[$entry->getItem()->getId()])
		));

		$groups = $this->codecProtocolId >= ProtocolInfo::PROTOCOL_1_21_60 ? $content->getGroups() : [];

		$this->replace($packet, CreativeContentPacket::create($groups, $entries));
	}
}
