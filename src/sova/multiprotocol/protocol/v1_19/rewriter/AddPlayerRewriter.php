<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\protocol\v1_19_10\Protocol1_19_10;
use sova\multiprotocol\translation\ability\LegacyAdventureSettings;
use function count;

/**
 * @extends TypedPacketRewriter<AddPlayerPacket>
 */
final class AddPlayerRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly int $clientProtocolId,
		int $codecProtocolId
	) {
		parent::__construct(AddPlayerPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$player = $this->peek($packet);
		$hasAbilities = $this->clientProtocolId >= Protocol1_19_10::PROTOCOL;

		$out = new ByteBufferWriter();
		CommonTypes::putUUID($out, $player->uuid);
		CommonTypes::putString($out, $player->username);
		if (!$hasAbilities) {
			CommonTypes::putActorUniqueId($out, $player->abilities->getTargetActorUniqueId());
		}
		CommonTypes::putActorRuntimeId($out, $player->actorRuntimeId);
		CommonTypes::putString($out, $player->platformChatId);
		CommonTypes::putVector3($out, $player->position);
		CommonTypes::putVector3Nullable($out, $player->motion);
		LE::writeFloat($out, $player->pitch);
		LE::writeFloat($out, $player->yaw);
		LE::writeFloat($out, $player->headYaw);
		CommonTypes::putItemStackWrapper($out, $this->codecProtocolId, $player->item, false);
		VarInt::writeSignedInt($out, $player->gameMode);
		CommonTypes::putEntityMetadata($out, $this->codecProtocolId, $player->metadata);

		if ($hasAbilities) {
			$player->abilities->encode($out, $this->codecProtocolId);
		} else {
			LegacyAdventureSettings::writeAbilities($out, $player->abilities);
		}

		VarInt::writeUnsignedInt($out, count($player->links));
		foreach ($player->links as $link) {
			CommonTypes::putEntityLink($out, $this->codecProtocolId, $link);
		}

		CommonTypes::putString($out, $player->deviceId);
		LE::writeSignedInt($out, $player->buildPlatform);

		$packet->replacePayload($out->getData());
	}
}
