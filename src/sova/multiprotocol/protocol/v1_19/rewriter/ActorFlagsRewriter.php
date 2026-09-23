<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\entity\EntityFlagsTranslator;

/**
 * @template T of AddActorPacket|AddPlayerPacket|AddItemActorPacket|SetActorDataPacket
 * @extends TypedPacketRewriter<T>
 */
final class ActorFlagsRewriter extends TypedPacketRewriter
{
	/**
	 * @param class-string<T> $packetClass
	 */
	public function __construct(
		string $packetClass,
		private readonly EntityFlagsTranslator $flags,
		int $codecProtocolId
	) {
		parent::__construct($packetClass, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$metadata = $this->peek($packet)->metadata;
		$translated = $this->flags->toClient($metadata);
		if ($translated !== $metadata) {
			$this->decode($packet)->metadata = $translated;
		}
	}
}
