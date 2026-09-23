<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;
use pocketmine\network\mcpe\protocol\types\recipe\ShapedRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\ShapelessRecipe;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\item\ItemMapping;
use sova\multiprotocol\translation\recipe\LegacyIngredientResolver;
use sova\multiprotocol\translation\recipe\LegacyRecipeIngredient;
use function count;

/**
 * @extends TypedPacketRewriter<CraftingDataPacket>
 */
final class CraftingDataRewriter extends TypedPacketRewriter
{
	private readonly LegacyIngredientResolver $ingredients;

	public function __construct(ItemMapping $items, int $codecProtocolId)
	{
		parent::__construct(CraftingDataPacket::class, $codecProtocolId, Direction::CLIENTBOUND);
		$this->ingredients = new LegacyIngredientResolver($items, $codecProtocolId);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$data = $this->peek($packet);

		$shaped = [
			CraftingDataPacket::ENTRY_SHAPED => $data->shapedRecipes,
			CraftingDataPacket::ENTRY_SHAPED_CHEMISTRY => $data->shapedChemistryRecipes,
		];
		$shapeless = [
			CraftingDataPacket::ENTRY_SHAPELESS => $data->shapelessRecipes,
			CraftingDataPacket::ENTRY_USER_DATA_SHAPELESS => $data->userDataShapelessRecipes,
			CraftingDataPacket::ENTRY_SHAPELESS_CHEMISTRY => $data->shapelessChemistryRecipes,
		];

		$out = new ByteBufferWriter();
		VarInt::writeUnsignedInt($out, count($data->shapedRecipes) + count($data->shapedChemistryRecipes) + count($data->shapelessRecipes) +
			count($data->userDataShapelessRecipes) + count($data->shapelessChemistryRecipes) + count($data->multiRecipes) + count($data->furnaceRecipes));

		foreach ($shapeless as $type => $recipes) {
			foreach ($recipes as $recipe) {
				VarInt::writeSignedInt($out, $type);
				$this->writeShapeless($out, $recipe);
			}
		}
		foreach ($shaped as $type => $recipes) {
			foreach ($recipes as $recipe) {
				VarInt::writeSignedInt($out, $type);
				$this->writeShaped($out, $recipe);
			}
		}
		foreach ($data->multiRecipes as $recipe) {
			VarInt::writeSignedInt($out, CraftingDataPacket::ENTRY_MULTI);
			$recipe->encode($out, $this->codecProtocolId);
		}
		foreach ($data->furnaceRecipes as $recipe) {
			VarInt::writeSignedInt($out, $recipe->getTypeId());
			$recipe->encode($out, $this->codecProtocolId);
		}

		VarInt::writeUnsignedInt($out, count($data->potionTypeRecipes));
		foreach ($data->potionTypeRecipes as $recipe) {
			$recipe->encode($out);
		}
		VarInt::writeUnsignedInt($out, count($data->potionContainerRecipes));
		foreach ($data->potionContainerRecipes as $recipe) {
			$recipe->encode($out);
		}
		VarInt::writeUnsignedInt($out, count($data->materialReducerRecipes));
		foreach ($data->materialReducerRecipes as $recipe) {
			$recipe->encode($out);
		}
		CommonTypes::putBool($out, $data->cleanRecipes);

		$packet->replacePayload($out->getData());
	}

	private function writeShapeless(ByteBufferWriter $out, ShapelessRecipe $recipe): void
	{
		CommonTypes::putString($out, $recipe->getRecipeId());
		VarInt::writeUnsignedInt($out, count($recipe->getInputs()));
		foreach ($recipe->getInputs() as $ingredient) {
			$this->writeIngredient($out, $ingredient);
		}
		$this->writeOutputs($out, $recipe->getOutputs());
		CommonTypes::putUUID($out, $recipe->getUuid());
		CommonTypes::putString($out, $recipe->getBlockName());
		VarInt::writeSignedInt($out, $recipe->getPriority());
		CommonTypes::writeRecipeNetId($out, $recipe->getRecipeNetId());
	}

	private function writeShaped(ByteBufferWriter $out, ShapedRecipe $recipe): void
	{
		CommonTypes::putString($out, $recipe->getRecipeId());
		VarInt::writeSignedInt($out, $recipe->getWidth());
		VarInt::writeSignedInt($out, $recipe->getHeight());
		foreach ($recipe->getInput() as $row) {
			foreach ($row as $ingredient) {
				$this->writeIngredient($out, $ingredient);
			}
		}
		$this->writeOutputs($out, $recipe->getOutput());
		CommonTypes::putUUID($out, $recipe->getUuid());
		CommonTypes::putString($out, $recipe->getBlockName());
		VarInt::writeSignedInt($out, $recipe->getPriority());
		CommonTypes::writeRecipeNetId($out, $recipe->getRecipeNetId());
	}

	/**
	 * @param ItemStack[] $outputs
	 */
	private function writeOutputs(ByteBufferWriter $out, array $outputs): void
	{
		VarInt::writeUnsignedInt($out, count($outputs));
		foreach ($outputs as $item) {
			CommonTypes::putItemStackWithoutStackId($out, $this->codecProtocolId, $item);
		}
	}

	private function writeIngredient(ByteBufferWriter $out, RecipeIngredient $ingredient): void
	{
		LegacyRecipeIngredient::write($out, $this->ingredients->resolve($ingredient));
	}
}
