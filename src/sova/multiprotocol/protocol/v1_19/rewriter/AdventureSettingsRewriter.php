<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\UpdateAbilitiesPacket;
use pocketmine\network\mcpe\protocol\UpdateAdventureSettingsPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\ability\LegacyAdventureSettings;

/**
 * @template T of UpdateAbilitiesPacket|UpdateAdventureSettingsPacket
 * @extends TypedPacketRewriter<T>
 */
final class AdventureSettingsRewriter extends TypedPacketRewriter
{
	private const int ADVENTURE_SETTINGS_PACKET = 0x37;

	/**
	 * @param class-string<T> $packetClass
	 */
	private function __construct(string $packetClass, int $codecProtocolId)
	{
		parent::__construct($packetClass, $codecProtocolId, Direction::CLIENTBOUND);
	}

	/**
	 * @return list<self<UpdateAbilitiesPacket>|self<UpdateAdventureSettingsPacket>>
	 */
	public static function create(int $codecProtocolId): array
	{
		return [
			new self(UpdateAbilitiesPacket::class, $codecProtocolId),
			new self(UpdateAdventureSettingsPacket::class, $codecProtocolId),
		];
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$settings = $packet->session->get(LegacyAdventureSettings::class);
		self::apply($settings, $this->peek($packet));

		$out = new ByteBufferWriter();
		$settings->write($out);

		$packet->setId(self::ADVENTURE_SETTINGS_PACKET);
		$packet->replacePayload($out->getData());
	}

	private static function apply(LegacyAdventureSettings $settings, DataPacket $update): void
	{
		if ($update instanceof UpdateAbilitiesPacket) {
			$settings->setAbilities($update->getData());
		} elseif ($update instanceof UpdateAdventureSettingsPacket) {
			$settings->setAdventureSettings($update->isWorldImmutable(), $update->isNoAttackingPlayers(), $update->isAutoJump());
		}
	}
}
