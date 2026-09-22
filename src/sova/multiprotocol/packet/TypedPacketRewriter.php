<?php

declare(strict_types=1);

namespace sova\multiprotocol\packet;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use sova\multiprotocol\protocol\ProtocolException;

/**
 * @template T of DataPacket
 */
abstract class TypedPacketRewriter extends AbstractPacketRewriter
{
	/**
	 * @param class-string<T> $packetClass
	 */
	public function __construct(
		private readonly string $packetClass,
		protected readonly int $codecProtocolId,
		Direction $direction,
		Direction ...$directions
	) {
		parent::__construct($packetClass::NETWORK_ID, $direction, ...$directions);
	}

	/**
	 * @return T
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	protected function peek(PacketWrapper $packet): DataPacket
	{
		return $packet->peek($this->packetClass, $this->codecProtocolId, $this->createPacket(...));
	}

	/**
	 * @return T
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	protected function decode(PacketWrapper $packet): DataPacket
	{
		return $packet->decode($this->packetClass, $this->codecProtocolId, $this->createPacket(...));
	}

	/**
	 * @return T
	 */
	protected function createPacket(): DataPacket
	{
		return new ($this->packetClass)();
	}

	/**
	 * @param T $replacement
	 */
	protected function replace(PacketWrapper $packet, DataPacket $replacement): void
	{
		$packet->replace($replacement, $this->codecProtocolId);
	}
}
