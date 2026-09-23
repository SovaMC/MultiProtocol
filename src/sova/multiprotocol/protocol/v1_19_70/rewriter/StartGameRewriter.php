<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_70\rewriter;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\ServerTelemetryData;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketHeader;
use sova\multiprotocol\packet\PacketWrapper;
use function substr;

final class StartGameRewriter extends AbstractPacketRewriter
{
	private const string NETWORK_PERMISSIONS_PLACEHOLDER = "\x00";
	private const int TRAILING_FIELDS_LENGTH = 2;
	private const int EDITOR_FLAGS_LENGTH = 2;

	public function __construct(
		private readonly ItemTypeDictionary $clientItems,
		private readonly int $codecProtocolId
	) {
		parent::__construct(ProtocolInfo::START_GAME_PACKET, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$startGame = new StartGamePacket();
		$startGame->serverTelemetryData = new ServerTelemetryData('', '', '', '');
		$startGame->decode(new ByteBufferReader($packet->getBuffer() . self::NETWORK_PERMISSIONS_PLACEHOLDER), $this->codecProtocolId);
		$startGame->itemTable = $this->clientItems->getEntries();

		$writer = new ByteBufferWriter();
		$startGame->encode($writer, $this->codecProtocolId);
		$encoded = $writer->getData();

		$reader = new ByteBufferReader($encoded);
		PacketHeader::read($reader);
		$payload = substr($encoded, $reader->getOffset(), -self::TRAILING_FIELDS_LENGTH);

		$offset = self::editorFlagsOffset($payload);
		$packet->replacePayload(substr($payload, 0, $offset) . substr($payload, $offset + self::EDITOR_FLAGS_LENGTH));
	}

	private static function editorFlagsOffset(string $payload): int
	{
		$in = new ByteBufferReader($payload);

		CommonTypes::getActorUniqueId($in);
		CommonTypes::getActorRuntimeId($in);
		VarInt::readSignedInt($in);
		CommonTypes::getVector3($in);
		LE::readFloat($in);
		LE::readFloat($in);

		LE::readUnsignedLong($in);
		SpawnSettings::read($in);
		VarInt::readSignedInt($in);
		VarInt::readSignedInt($in);
		VarInt::readSignedInt($in);
		CommonTypes::getBlockPosition($in, false);
		CommonTypes::getBool($in);
		CommonTypes::getBool($in);

		return $in->getOffset();
	}
}
