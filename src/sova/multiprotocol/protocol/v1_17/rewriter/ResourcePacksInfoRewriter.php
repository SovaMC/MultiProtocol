<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_17\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackInfoEntry;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\protocol\v1_16_200\Protocol1_16_200;
use function count;

/**
 * @extends TypedPacketRewriter<ResourcePacksInfoPacket>
 */
final class ResourcePacksInfoRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly int $clientProtocolId,
		int $codecProtocolId
	) {
		parent::__construct(ResourcePacksInfoPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$info = $this->peek($packet);
		$out = new ByteBufferWriter();
		CommonTypes::putBool($out, $info->mustAccept);
		CommonTypes::putBool($out, $info->hasScripts);
		LE::writeUnsignedShort($out, count($info->behaviorPackEntries));
		foreach ($info->behaviorPackEntries as $entry) {
			$entry->write($out, $this->codecProtocolId);
		}
		LE::writeUnsignedShort($out, count($info->resourcePackEntries));
		foreach ($info->resourcePackEntries as $entry) {
			$this->writeResourcePack($out, $entry);
		}
		$packet->replacePayload($out->getData());
	}

	private function writeResourcePack(ByteBufferWriter $out, ResourcePackInfoEntry $entry): void
	{
		CommonTypes::putString($out, $entry->getPackId()->toString());
		CommonTypes::putString($out, $entry->getVersion());
		LE::writeUnsignedLong($out, $entry->getSizeBytes());
		CommonTypes::putString($out, $entry->getEncryptionKey());
		CommonTypes::putString($out, $entry->getSubPackName());
		CommonTypes::putString($out, $entry->getContentId());
		CommonTypes::putBool($out, $entry->hasScripts());
		if ($this->clientProtocolId >= Protocol1_16_200::PROTOCOL) {
			CommonTypes::putBool($out, $entry->isAddonPack());
		}
	}
}
