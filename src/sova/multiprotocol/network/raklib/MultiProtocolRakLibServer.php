<?php

declare(strict_types=1);

namespace sova\multiprotocol\network\raklib;

use GlobalLogger;
use pmmp\thread\ThreadSafeArray;
use pocketmine\network\mcpe\raklib\PthreadsChannelReader;
use pocketmine\network\mcpe\raklib\RakLibServer;
use pocketmine\network\mcpe\raklib\SnoozeAwarePthreadsChannelWriter;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\log\ThreadSafeLogger;
use raklib\server\ipc\RakLibToUserThreadMessageSender;
use raklib\server\ipc\UserToRakLibThreadMessageReceiver;
use raklib\server\Server;
use raklib\server\ServerSocket;
use raklib\utils\ExceptionTraceCleaner;
use raklib\utils\InternetAddress;
use function gc_disable;
use function ini_set;

final class MultiProtocolRakLibServer extends RakLibServer
{
	/** @phpstan-var ThreadSafeArray<int, int> */
	private ThreadSafeArray $acceptedVersions;

	/**
	 * @phpstan-param ThreadSafeArray<int, string> $mainToThreadBuffer
	 * @phpstan-param ThreadSafeArray<int, string> $threadToMainBuffer
	 * @param list<int> $acceptedVersions
	 */
	public function __construct(
		ThreadSafeLogger $logger,
		ThreadSafeArray $mainToThreadBuffer,
		ThreadSafeArray $threadToMainBuffer,
		InternetAddress $address,
		int $serverId,
		int $maxMtuSize,
		int $protocolVersion,
		array $acceptedVersions,
		SleeperHandlerEntry $sleeperEntry
	) {
		parent::__construct($logger, $mainToThreadBuffer, $threadToMainBuffer, $address, $serverId, $maxMtuSize, $protocolVersion, $sleeperEntry);

		/** @phpstan-var ThreadSafeArray<int, int> $versions */
		$versions = ThreadSafeArray::fromArray($acceptedVersions);
		$this->acceptedVersions = $versions;
	}

	protected function onRun(): void
	{
		gc_disable();

		ini_set("display_errors", '1');
		ini_set("display_startup_errors", '1');
		GlobalLogger::set($this->logger);

		$versions = [];
		foreach ($this->acceptedVersions as $version) {
			$versions[] = $version;
		}

		$socket = new ServerSocket($this->address->deserialize());
		$manager = new Server(
			$this->serverId,
			$this->logger,
			$socket,
			$this->maxMtuSize,
			new MultiProtocolAcceptor($this->protocolVersion, $versions),
			new UserToRakLibThreadMessageReceiver(new PthreadsChannelReader($this->mainToThreadBuffer)),
			new RakLibToUserThreadMessageSender(new SnoozeAwarePthreadsChannelWriter($this->threadToMainBuffer, $this->sleeperEntry->createNotifier())),
			new ExceptionTraceCleaner($this->mainPath),
			recvMaxSplitParts: 512
		);

		$this->synchronized(function (): void {
			$this->ready = true;
			$this->notify();
		});

		while (!$this->isKilled) {
			$manager->tickProcessor();
		}

		$manager->waitShutdown();
	}
}
