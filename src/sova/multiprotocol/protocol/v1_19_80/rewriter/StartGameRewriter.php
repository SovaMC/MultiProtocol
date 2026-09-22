<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_80\rewriter;

use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\ServerTelemetryData;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use function substr;

/**
 * @extends TypedPacketRewriter<StartGamePacket>
 */
final class StartGameRewriter extends TypedPacketRewriter
{
	private const int NETWORK_PERMISSIONS_LENGTH = 1;

	public function __construct(
		private readonly ItemTypeDictionary $clientItems,
		int $codecProtocolId
	) {
		parent::__construct(StartGamePacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	protected function createPacket(): StartGamePacket
	{
		$packet = new StartGamePacket();
		$packet->serverTelemetryData = new ServerTelemetryData('', '', '', '');

		return $packet;
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$this->decode($packet)->itemTable = $this->clientItems->getEntries();

		$packet->replacePayload(substr($packet->getPayload(), 0, -self::NETWORK_PERMISSIONS_LENGTH));
	}
}
