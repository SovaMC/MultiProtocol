<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\skin;

use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use sova\multiprotocol\session\ProtocolSession;
use sova\multiprotocol\utils\Reflection;
use function substr;

final readonly class SkinFormat
{
	private const int OVERRIDE_FLAG_LENGTH = 1;

	public function __construct(
		private int $codecProtocolId,
		private bool $overrideFlag,
		private bool $stableFullSkinId
	) {
	}

	public function hasOverrideFlag(ProtocolSession $session): bool
	{
		return $this->overrideFlag || $session->get(SkinOverrideSupport::class)->isSupported();
	}

	public function isNative(ProtocolSession $session): bool
	{
		return !$this->stableFullSkinId && $this->hasOverrideFlag($session);
	}

	public function translate(SkinData $skin): SkinData
	{
		if (!$this->stableFullSkinId || $skin->getFullSkinId() === $skin->getSkinId()) {
			return $skin;
		}

		$translated = clone $skin;
		Reflection::set(SkinData::class, $translated, 'fullSkinId', $skin->getSkinId());

		return $translated;
	}

	public function write(ByteBufferWriter $out, SkinData $skin, ProtocolSession $session): void
	{
		$encoded = new ByteBufferWriter();
		CommonTypes::putSkin($encoded, $this->codecProtocolId, $this->translate($skin));
		$data = $encoded->getData();

		$out->writeByteArray($this->hasOverrideFlag($session) ? $data : substr($data, 0, -self::OVERRIDE_FLAG_LENGTH));
	}
}
