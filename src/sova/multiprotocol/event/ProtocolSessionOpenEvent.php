<?php

declare(strict_types=1);

namespace sova\multiprotocol\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use sova\multiprotocol\protocol\ProtocolPipeline;
use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\session\MultiProtocolNetworkSession;

final class ProtocolSessionOpenEvent extends Event implements Cancellable
{
	use CancellableTrait;

	public function __construct(
		private readonly MultiProtocolNetworkSession $session,
		private readonly ProtocolPipeline $pipeline
	) {
	}

	public function getSession(): MultiProtocolNetworkSession
	{
		return $this->session;
	}

	public function getPipeline(): ProtocolPipeline
	{
		return $this->pipeline;
	}

	public function getVersion(): ProtocolVersion
	{
		return $this->pipeline->client;
	}
}
