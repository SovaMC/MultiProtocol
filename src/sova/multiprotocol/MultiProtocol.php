<?php

declare(strict_types=1);

namespace sova\multiprotocol;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use sova\multiprotocol\command\ProtocolsCommand;
use sova\multiprotocol\config\MultiProtocolConfig;
use sova\multiprotocol\network\NetworkInterfaceReplacer;
use sova\multiprotocol\protocol\ProtocolAliases;
use sova\multiprotocol\protocol\ProtocolException;
use sova\multiprotocol\protocol\ProtocolRegistry;
use sova\multiprotocol\protocol\Protocols;
use sova\multiprotocol\protocol\ProtocolVersion;
use sova\multiprotocol\session\MultiProtocolNetworkSession;
use Throwable;
use function count;
use function hrtime;
use function max;
use function min;
use function round;

final class MultiProtocol extends PluginBase
{
	use SingletonTrait;

	private const string PROTOCOL_DATA_DIRECTORY = 'protocol';

	private ProtocolRegistry $registry;
	private MultiProtocolConfig $configuration;

	protected function onLoad(): void
	{
		self::setInstance($this);

		$this->registry = new ProtocolRegistry();
	}

	protected function onEnable(): void
	{
		$this->saveDefaultConfig();
		$this->configuration = MultiProtocolConfig::fromArray($this->getConfig()->getAll());

		$this->registerProtocols();

		$server = $this->getServer();
		$server->getPluginManager()->registerEvents(
			new MultiProtocolListener(new NetworkInterfaceReplacer($server, $this->registry, $this->configuration)),
			$this
		);
		$server->getCommandMap()->register($this->getName(), new ProtocolsCommand($this));

		$native = ProtocolInfo::ACCEPTED_PROTOCOL;
		$this->getLogger()->info('Native protocols: ' . count($native) . ' (' . min($native) . ' - ' . max($native) . '), translated: ' . count($this->registry->getAll()));
	}

	public function getRegistry(): ProtocolRegistry
	{
		return $this->registry;
	}

	public function getConfiguration(): MultiProtocolConfig
	{
		return $this->configuration;
	}

	public static function getClientVersion(Player|NetworkSession $target): ?ProtocolVersion
	{
		$session = $target instanceof Player ? $target->getNetworkSession() : $target;

		return $session instanceof MultiProtocolNetworkSession ? $session->getProtocolSession()?->client : null;
	}

	private function registerProtocols(): void
	{
		foreach (Protocols::all($this->getResourcePath(self::PROTOCOL_DATA_DIRECTORY)) as $protocol) {
			if ($this->configuration->isDisabled($protocol->version->id)) {
				continue;
			}

			try {
				$this->registry->register($protocol);
			} catch (ProtocolException $e) {
				$this->getLogger()->error('Failed to register ' . $protocol->version . ': ' . $e->getMessage());
				continue;
			}

			$start = hrtime(true);
			try {
				$protocol->load();
			} catch (Throwable $e) {
				$this->registry->unregister($protocol->version->id);
				$this->getLogger()->error('Failed to load ' . $protocol->version . ': ' . $e->getMessage());
				$this->getLogger()->logException($e);
				continue;
			}

			$this->getLogger()->debug('Loaded ' . $protocol->version . ' in ' . round((hrtime(true) - $start) / 1e6) . 'ms');
		}

		foreach ($this->registry->getAll() as $protocol) {
			try {
				ProtocolAliases::register($protocol->version->id, $this->registry->getPipeline($protocol->version->id)->serverProtocolId);
			} catch (ProtocolException $e) {
				$this->registry->unregister($protocol->version->id);
				$this->getLogger()->warning($protocol->version . ' has no complete translation chain to a native protocol: ' . $e->getMessage());
			}
		}
	}
}
