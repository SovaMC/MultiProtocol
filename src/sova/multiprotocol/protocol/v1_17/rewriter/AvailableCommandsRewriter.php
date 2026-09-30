<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_17\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use function count;

/**
 * @extends TypedPacketRewriter<AvailableCommandsPacket>
 */
final class AvailableCommandsRewriter extends TypedPacketRewriter
{
	public function __construct(int $codecProtocolId)
	{
		parent::__construct(AvailableCommandsPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$commands = $this->peek($packet);

		$out = new ByteBufferWriter();
		VarInt::writeUnsignedInt($out, count($commands->enumValues));
		foreach ($commands->enumValues as $value) {
			CommonTypes::putString($out, $value);
		}

		VarInt::writeUnsignedInt($out, count($commands->postfixes));
		foreach ($commands->postfixes as $postfix) {
			CommonTypes::putString($out, $postfix);
		}

		VarInt::writeUnsignedInt($out, count($commands->enums));
		foreach ($commands->enums as $enum) {
			$enum->write($out, count($commands->enumValues), $this->codecProtocolId);
		}

		VarInt::writeUnsignedInt($out, count($commands->commandData));
		foreach ($commands->commandData as $command) {
			CommonTypes::putString($out, $command->getName());
			CommonTypes::putString($out, $command->getDescription());
			Byte::writeUnsigned($out, $command->getFlags() & 0xff);
			Byte::writeUnsigned($out, CommandPermissions::fromName($command->getPermission()));
			LE::writeSignedInt($out, $command->getAliasEnumIndex());

			VarInt::writeUnsignedInt($out, count($command->getOverloads()));
			foreach ($command->getOverloads() as $overload) {
				VarInt::writeUnsignedInt($out, count($overload->getParameters()));
				foreach ($overload->getParameters() as $parameter) {
					$parameter->write($out);
				}
			}
		}

		VarInt::writeUnsignedInt($out, count($commands->softEnums));
		foreach ($commands->softEnums as $softEnum) {
			$softEnum->write($out);
		}

		VarInt::writeUnsignedInt($out, count($commands->enumConstraints));
		foreach ($commands->enumConstraints as $constraint) {
			$constraint->write($out);
		}

		$packet->replacePayload($out->getData());
	}
}
