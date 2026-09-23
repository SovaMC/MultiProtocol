<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use sova\multiprotocol\packet\AbstractPacketRewriter;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use function trim;

final class ModalFormResponseRewriter extends AbstractPacketRewriter
{
	private const string CLOSED_FORM_DATA = 'null';

	public function __construct()
	{
		parent::__construct(ProtocolInfo::MODAL_FORM_RESPONSE_PACKET, Direction::SERVERBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$packet->passthrough(VarInt::readUnsignedInt(...));
		$formData = CommonTypes::getString($packet->reader());
		$out = $packet->writer();

		if (trim($formData) === self::CLOSED_FORM_DATA) {
			CommonTypes::putBool($out, false);
			CommonTypes::putBool($out, true);
			Byte::writeUnsigned($out, ModalFormResponsePacket::CANCEL_REASON_CLOSED);
			return;
		}

		CommonTypes::putBool($out, true);
		CommonTypes::putString($out, $formData);
		CommonTypes::putBool($out, false);
	}
}
