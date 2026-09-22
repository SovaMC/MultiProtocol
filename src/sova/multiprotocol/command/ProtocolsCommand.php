<?php

declare(strict_types=1);

namespace sova\multiprotocol\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use pocketmine\utils\TextFormat;
use sova\multiprotocol\MultiProtocol;
use function count;
use function implode;
use function ksort;
use function max;
use function min;

final class ProtocolsCommand extends Command implements PluginOwned
{
	use PluginOwnedTrait;

	public function __construct(MultiProtocol $plugin)
	{
		parent::__construct('protocols', 'Shows supported protocols and connected client versions', '/protocols', ['mp']);
		$this->setPermission('multiprotocol.command.protocols');
		$this->owningPlugin = $plugin;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): bool
	{
		$plugin = $this->getOwningPlugin();
		if (!$plugin instanceof MultiProtocol) {
			return false;
		}

		$native = ProtocolInfo::ACCEPTED_PROTOCOL;
		$sender->sendMessage(TextFormat::GOLD . 'Native: ' . TextFormat::WHITE . count($native) . ' protocols (' . min($native) . ' - ' . max($native) . ')');

		$virtual = [];
		foreach ($plugin->getRegistry()->getAll() as $protocol) {
			$virtual[] = (string) $protocol->version;
		}
		$sender->sendMessage(TextFormat::GOLD . 'Translated: ' . TextFormat::WHITE . ($virtual === [] ? 'none' : implode(', ', $virtual)));

		$online = [];
		foreach ($plugin->getServer()->getOnlinePlayers() as $player) {
			$online[$player->getNetworkSession()->getProtocolId()][] = $player->getName();
		}
		ksort($online);

		foreach ($online as $protocolId => $players) {
			$sender->sendMessage(TextFormat::GRAY . $protocolId . ': ' . TextFormat::WHITE . implode(', ', $players));
		}

		return true;
	}
}
