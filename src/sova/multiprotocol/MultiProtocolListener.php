<?php

declare(strict_types=1);

namespace sova\multiprotocol;

use pocketmine\event\Listener;
use pocketmine\event\server\NetworkInterfaceRegisterEvent;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\query\DedicatedQueryNetworkInterface;
use sova\multiprotocol\network\NetworkInterfaceReplacer;

final readonly class MultiProtocolListener implements Listener
{
	public function __construct(
		private NetworkInterfaceReplacer $replacer
	) {
	}

	/**
	 * @priority LOWEST
	 * @noinspection PhpUnused
	 */
	public function handleNetworkInterfaceRegister(NetworkInterfaceRegisterEvent $event): void
	{
		$interface = $event->getInterface();

		if ($interface instanceof DedicatedQueryNetworkInterface && $this->replacer->hasReplaced()) {
			$event->cancel();
			return;
		}

		if (!$interface instanceof RakLibInterface) {
			return;
		}

		$event->cancel();
		$this->replacer->replace($interface);
	}
}
