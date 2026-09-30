<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\skin;

use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use sova\multiprotocol\session\ProtocolSession;
use function substr;

final readonly class SkinFormat
{
	private const int OVERRIDE_FLAG_LENGTH = 1;

	public function __construct(
		private int $codecProtocolId
	) {
	}

	public function hasOverrideFlag(ProtocolSession $session): bool
	{
		return $session->get(SkinOverrideSupport::class)->isSupported();
	}

	public function write(ByteBufferWriter $out, SkinData $skin): void
	{
		$encoded = new ByteBufferWriter();
		CommonTypes::putSkin($encoded, $this->codecProtocolId, $skin);

		$out->writeByteArray(substr($encoded->getData(), 0, -self::OVERRIDE_FLAG_LENGTH));
	}
}
