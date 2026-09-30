<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\skin\SkinFormat;
use function count;

/**
 * @extends TypedPacketRewriter<PlayerListPacket>
 */
final class PlayerListRewriter extends TypedPacketRewriter
{
	private const int ADD = 0;

	public function __construct(
		private readonly SkinFormat $skins,
		int $codecProtocolId
	) {
		parent::__construct(PlayerListPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		if ($this->skins->hasOverrideFlag($packet->session) || $this->peek($packet)->type !== PlayerListPacket::TYPE_ADD) {
			return;
		}

		$list = $this->peek($packet);

		$out = new ByteBufferWriter();
		Byte::writeUnsigned($out, self::ADD);
		VarInt::writeUnsignedInt($out, count($list->entries));
		foreach ($list->entries as $entry) {
			CommonTypes::putUUID($out, $entry->uuid);
			CommonTypes::putActorUniqueId($out, $entry->actorUniqueId);
			CommonTypes::putString($out, $entry->username);
			CommonTypes::putString($out, $entry->xboxUserId);
			CommonTypes::putString($out, $entry->platformChatId);
			LE::writeSignedInt($out, $entry->buildPlatform);
			$this->skins->write($out, $entry->skinData);
			CommonTypes::putBool($out, $entry->isTeacher);
			CommonTypes::putBool($out, $entry->isHost);
		}
		foreach ($list->entries as $entry) {
			CommonTypes::putBool($out, $entry->skinData->isVerified());
		}

		$packet->replacePayload($out->getData());
	}
}
