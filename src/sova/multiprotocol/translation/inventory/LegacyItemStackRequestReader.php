<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\inventory;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeAutoStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestActionType;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\translation\recipe\LegacyRecipeIngredient;
use sova\multiprotocol\utils\Reflection;
use function array_search;
use function is_int;

final readonly class LegacyItemStackRequestReader
{
	public function __construct(
		private int $codecProtocolId,
		private bool $legacyIngredients,
		private bool $hasFilterStringCause,
		private ?ContainerSlotTranslator $slots
	) {
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	public function read(ByteBufferReader $in): ItemStackRequest
	{
		$requestId = CommonTypes::readItemStackRequestId($in);

		$actions = [];
		for ($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i) {
			$actions[] = $this->readAction($in);
		}

		$filterStrings = [];
		for ($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i) {
			$filterStrings[] = CommonTypes::getString($in);
		}

		$filterStringCause = $this->hasFilterStringCause ? LE::readSignedInt($in) : 0;

		return new ItemStackRequest($requestId, $actions, $filterStrings, $filterStringCause);
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	private function readAction(ByteBufferReader $in): ItemStackRequestAction
	{
		$innerTypeId = Byte::readUnsigned($in);
		$typeId = array_search($innerTypeId, ItemStackRequestActionType::INNER_TYPES, true);
		if (!is_int($typeId)) {
			throw new PacketDecodeException('Unhandled item stack request action type ' . $innerTypeId);
		}

		$action = $this->legacyIngredients && $typeId === CraftRecipeAutoStackRequestAction::ID
			? self::readLegacyCraftRecipeAuto($in)
			: Reflection::invokeStatic(ItemStackRequest::class, 'readAction', $in, $this->codecProtocolId, $typeId);

		if (!$action instanceof ItemStackRequestAction) {
			throw new PacketDecodeException('Unexpected item stack request action');
		}

		return $this->slots?->action(Direction::SERVERBOUND, $action) ?? $action;
	}

	/**
	 * @throws DataDecodeException
	 */
	private static function readLegacyCraftRecipeAuto(ByteBufferReader $in): CraftRecipeAutoStackRequestAction
	{
		$recipeId = CommonTypes::readRecipeNetId($in);
		$repetitions = Byte::readUnsigned($in);

		$ingredients = [];
		for ($i = 0, $count = Byte::readUnsigned($in); $i < $count; ++$i) {
			$ingredients[] = LegacyRecipeIngredient::read($in);
		}

		return new CraftRecipeAutoStackRequestAction($recipeId, $repetitions, 0, $ingredients);
	}
}
