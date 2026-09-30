<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\skin\SkinFormat;

/**
 * @extends TypedPacketRewriter<PlayerSkinPacket>
 */
final class PlayerSkinRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly SkinFormat $skins,
		int $codecProtocolId
	) {
		parent::__construct(PlayerSkinPacket::class, $codecProtocolId, Direction::CLIENTBOUND, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		if ($this->skins->isNative($packet->session)) {
			return;
		}

		if ($packet->direction === Direction::SERVERBOUND) {
			$packet->passthrough(CommonTypes::getUUID(...));
			$this->skins->toServer($packet->reader(), $packet->writer(), $packet->session);
			return;
		}

		$skin = $this->peek($packet);

		$out = new ByteBufferWriter();
		CommonTypes::putUUID($out, $skin->uuid);
		$this->skins->write($out, $skin->skin, $packet->session);
		CommonTypes::putString($out, $skin->newSkinName);
		CommonTypes::putString($out, $skin->oldSkinName);
		CommonTypes::putBool($out, $skin->skin->isVerified());

		$packet->replacePayload($out->getData());
	}
}
