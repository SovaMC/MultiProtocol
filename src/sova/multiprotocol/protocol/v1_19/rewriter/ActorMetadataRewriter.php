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
use sova\multiprotocol\translation\entity\EntityMetadataKeyTranslator;

/**
 * @template T of AddActorPacket|AddPlayerPacket|AddItemActorPacket|SetActorDataPacket
 * @extends TypedPacketRewriter<T>
 */
final class ActorMetadataRewriter extends TypedPacketRewriter
{
	/**
	 * @param class-string<T> $packetClass
	 */
	public function __construct(
		string $packetClass,
		private readonly EntityFlagsTranslator $flags,
		private readonly ?EntityMetadataKeyTranslator $keys,
		int $codecProtocolId
	) {
		parent::__construct($packetClass, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$metadata = $this->peek($packet)->metadata;
		$translated = $this->flags->toClient($metadata);
		if ($this->keys !== null) {
			$translated = $this->keys->toClient($translated);
		}
		if ($translated !== $metadata) {
			$this->decode($packet)->metadata = $translated;
		}
	}
}
