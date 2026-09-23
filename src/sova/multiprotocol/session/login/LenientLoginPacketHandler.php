<?php

declare(strict_types=1);

namespace sova\multiprotocol\session\login;

use pocketmine\network\mcpe\handler\LoginPacketHandler;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientData;
use pocketmine\network\PacketHandlingException;

final class LenientLoginPacketHandler extends LoginPacketHandler
{
	protected function parseClientData(string $clientDataJwt): ClientData
	{
		try {
			$clientDataJwt = ClientDataPatcher::patch($clientDataJwt);
		} catch (JwtException $e) {
			throw PacketHandlingException::wrap($e);
		}

		return parent::parseClientData($clientDataJwt);
	}
}
