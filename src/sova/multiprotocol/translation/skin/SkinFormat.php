<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\skin;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use sova\multiprotocol\session\ProtocolSession;

final readonly class SkinFormat
{
	public function __construct(
		private int $codecProtocolId,
		private SkinLayout $layout
	) {
	}

	public function getLayout(ProtocolSession $session): SkinLayout
	{
		return $session->get(SkinOverrideSupport::class)->isSupported() ? SkinLayout::MODERN : $this->layout;
	}

	public function isNative(ProtocolSession $session): bool
	{
		return $this->getLayout($session) === SkinLayout::MODERN;
	}

	/**
	 * @throws DataDecodeException
	 */
	public function write(ByteBufferWriter $out, SkinData $skin, ProtocolSession $session): void
	{
		$encoded = new ByteBufferWriter();
		CommonTypes::putSkin($encoded, $this->codecProtocolId, $skin);

		SkinLayout::MODERN->convert(new ByteBufferReader($encoded->getData()), $out, $this->getLayout($session));
	}

	/**
	 * @throws DataDecodeException
	 */
	public function toServer(ByteBufferReader $in, ByteBufferWriter $out, ProtocolSession $session): void
	{
		$this->getLayout($session)->convert($in, $out, SkinLayout::MODERN);
	}
}
