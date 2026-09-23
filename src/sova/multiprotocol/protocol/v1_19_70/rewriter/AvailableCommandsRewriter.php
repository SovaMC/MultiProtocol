<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19_70\rewriter;

use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandOverloadRawData;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandParameterRawData;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandRawData;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use function array_map;

/**
 * @extends TypedPacketRewriter<AvailableCommandsPacket>
 */
final class AvailableCommandsRewriter extends TypedPacketRewriter
{
	private const int TYPE_MASK = 0xffff;
	private const int SPECIAL_FLAGS = AvailableCommandsPacket::ARG_FLAG_ENUM | AvailableCommandsPacket::ARG_FLAG_POSTFIX | AvailableCommandsPacket::ARG_FLAG_SOFT_ENUM;

	private const int PERMISSION_TYPES_START = 32;
	private const int PERMISSION_TYPES_COUNT = 5;
	private const int STRING_TYPE = 39;

	public function __construct(int $codecProtocolId)
	{
		parent::__construct(AvailableCommandsPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$commands = $this->decode($packet);
		$commands->commandData = array_map(self::translateCommand(...), $commands->commandData);
	}

	private static function translateCommand(CommandRawData $command): CommandRawData
	{
		return new CommandRawData(
			$command->getName(),
			$command->getDescription(),
			$command->getFlags(),
			$command->getPermission(),
			$command->getAliasEnumIndex(),
			$command->getChainedSubCommandDataIndexes(),
			array_map(
				static fn(CommandOverloadRawData $overload) => new CommandOverloadRawData(
					$overload->isChaining(),
					array_map(self::translateParameter(...), $overload->getParameters())
				),
				$command->getOverloads()
			)
		);
	}

	private static function translateParameter(CommandParameterRawData $parameter): CommandParameterRawData
	{
		return new CommandParameterRawData(
			$parameter->getName(),
			self::translateType($parameter->getTypeInfo()),
			$parameter->isOptional(),
			$parameter->getFlags()
		);
	}

	private static function translateType(int $typeInfo): int
	{
		if (($typeInfo & AvailableCommandsPacket::ARG_FLAG_VALID) === 0 || ($typeInfo & self::SPECIAL_FLAGS) !== 0) {
			return $typeInfo;
		}

		$type = $typeInfo & self::TYPE_MASK;
		$translated = match (true) {
			$type >= self::PERMISSION_TYPES_START + self::PERMISSION_TYPES_COUNT => $type - self::PERMISSION_TYPES_COUNT,
			$type >= self::PERMISSION_TYPES_START => self::STRING_TYPE,
			default => $type,
		};

		return ($typeInfo & ~self::TYPE_MASK) | $translated;
	}
}
