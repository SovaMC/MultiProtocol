<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_60\rewriter;

use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\protocol\LoginPacket;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\skin\SkinOverrideSupport;
use function is_string;
use function str_starts_with;

/**
 * @extends TypedPacketRewriter<LoginPacket>
 */
final class LoginRewriter extends TypedPacketRewriter
{
	private const string GAME_VERSION_CLAIM = 'GameVersion';

	public function __construct(
		private readonly string $legacySkinVersion,
		int $codecProtocolId
	) {
		parent::__construct(LoginPacket::class, $codecProtocolId, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		try {
			[, $claims] = JwtUtils::parse($this->peek($packet)->clientDataJwt);
		} catch (JwtException) {
			return;
		}

		$gameVersion = $claims[self::GAME_VERSION_CLAIM] ?? null;
		if (is_string($gameVersion) && !str_starts_with($gameVersion, $this->legacySkinVersion)) {
			$packet->session->get(SkinOverrideSupport::class)->setSupported(true);
		}
	}
}
