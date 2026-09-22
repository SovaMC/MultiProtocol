<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol;

use sova\multiprotocol\packet\PacketRegistry;
use sova\multiprotocol\session\ProtocolSession;

abstract class Protocol
{
	private ?PacketRegistry $packets = null;

	public function __construct(
		public readonly ProtocolVersion $version,
		public readonly int $targetProtocolId
	) {
		if ($version->id === $targetProtocolId) {
			throw new ProtocolException($version . ' cannot target itself');
		}
	}

	abstract protected function registerPackets(PacketRegistry $packets): void;

	final public function getPackets(): PacketRegistry
	{
		return $this->packets ??= $this->createPackets();
	}

	private function createPackets(): PacketRegistry
	{
		$packets = new PacketRegistry($this);
		$this->registerPackets($packets);

		return $packets;
	}

	public function load(): void
	{
	}

	public function onSessionOpen(ProtocolSession $session): void
	{
	}
}
