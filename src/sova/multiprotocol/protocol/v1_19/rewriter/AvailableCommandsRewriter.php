<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandOverloadRawData;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandParameterRawData;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandRawData;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\command\ArgumentTypeRemap;
use function array_map;
use function array_values;

/**
 * @extends TypedPacketRewriter<AvailableCommandsPacket>
 */
final class AvailableCommandsRewriter extends TypedPacketRewriter
{
	private const int TYPE_MASK = 0xffff;
	private const int SPECIAL_FLAGS = AvailableCommandsPacket::ARG_FLAG_ENUM | AvailableCommandsPacket::ARG_FLAG_POSTFIX | AvailableCommandsPacket::ARG_FLAG_SOFT_ENUM;

	/** @var list<ArgumentTypeRemap> */
	private readonly array $remaps;

	public function __construct(int $codecProtocolId, ArgumentTypeRemap ...$remaps)
	{
		parent::__construct(AvailableCommandsPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
		$this->remaps = array_values($remaps);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$commands = $this->decode($packet);
		$commands->commandData = array_map($this->translateCommand(...), $commands->commandData);
	}

	private function translateCommand(CommandRawData $command): CommandRawData
	{
		return new CommandRawData(
			$command->getName(),
			$command->getDescription(),
			$command->getFlags(),
			$command->getPermission(),
			$command->getAliasEnumIndex(),
			$command->getChainedSubCommandDataIndexes(),
			array_map(
				fn(CommandOverloadRawData $overload) => new CommandOverloadRawData(
					$overload->isChaining(),
					array_map($this->translateParameter(...), $overload->getParameters())
				),
				$command->getOverloads()
			)
		);
	}

	private function translateParameter(CommandParameterRawData $parameter): CommandParameterRawData
	{
		return new CommandParameterRawData(
			$parameter->getName(),
			$this->translateType($parameter->getTypeInfo()),
			$parameter->isOptional(),
			$parameter->getFlags()
		);
	}

	private function translateType(int $typeInfo): int
	{
		if (($typeInfo & AvailableCommandsPacket::ARG_FLAG_VALID) === 0 || ($typeInfo & self::SPECIAL_FLAGS) !== 0) {
			return $typeInfo;
		}

		$type = $typeInfo & self::TYPE_MASK;
		foreach ($this->remaps as $remap) {
			$type = $remap->translate($type);
		}

		return ($typeInfo & ~self::TYPE_MASK) | $type;
	}
}
