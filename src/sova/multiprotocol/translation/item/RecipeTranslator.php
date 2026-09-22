<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation\item;

use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\recipe\FurnaceRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\MaterialReducerRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\MaterialReducerRecipeOutput;
use pocketmine\network\mcpe\protocol\types\recipe\PotionContainerChangeRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\PotionTypeRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\ShapedRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\ShapelessRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\SmithingTransformRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\SmithingTrimRecipe;
use sova\multiprotocol\packet\Direction;
use function array_filter;
use function array_map;
use function array_values;

final readonly class RecipeTranslator
{
	public function __construct(
		private ItemTranslator $items
	) {
	}

	public function translate(Direction $direction, CraftingDataPacket $data): void
	{
		$shaped = fn(ShapedRecipe $recipe) => $this->shaped($direction, $recipe);
		$shapeless = fn(ShapelessRecipe $recipe) => $this->shapeless($direction, $recipe);

		$data->shapedRecipes = self::filter(array_map($shaped, $data->shapedRecipes));
		$data->shapedChemistryRecipes = self::filter(array_map($shaped, $data->shapedChemistryRecipes));
		$data->shapelessRecipes = self::filter(array_map($shapeless, $data->shapelessRecipes));
		$data->userDataShapelessRecipes = self::filter(array_map($shapeless, $data->userDataShapelessRecipes));
		$data->shapelessChemistryRecipes = self::filter(array_map($shapeless, $data->shapelessChemistryRecipes));
		$data->furnaceRecipes = self::filter(array_map(fn(FurnaceRecipe $recipe) => $this->furnace($direction, $recipe), $data->furnaceRecipes));
		$data->smithingTransformRecipes = self::filter(array_map(fn(SmithingTransformRecipe $recipe) => $this->smithingTransform($direction, $recipe), $data->smithingTransformRecipes));
		$data->smithingTrimRecipes = self::filter(array_map(fn(SmithingTrimRecipe $recipe) => $this->smithingTrim($direction, $recipe), $data->smithingTrimRecipes));
		$data->potionTypeRecipes = self::filter(array_map(fn(PotionTypeRecipe $recipe) => $this->potionType($direction, $recipe), $data->potionTypeRecipes));
		$data->potionContainerRecipes = self::filter(array_map(fn(PotionContainerChangeRecipe $recipe) => $this->potionContainer($direction, $recipe), $data->potionContainerRecipes));
		$data->materialReducerRecipes = self::filter(array_map(fn(MaterialReducerRecipe $recipe) => $this->materialReducer($direction, $recipe), $data->materialReducerRecipes));
	}

	private function shaped(Direction $direction, ShapedRecipe $recipe): ?ShapedRecipe
	{
		$input = [];
		foreach ($recipe->getInput() as $columns) {
			$row = [];
			foreach ($columns as $ingredient) {
				$translated = $this->items->ingredient($direction, $ingredient);
				if ($translated === null) {
					return null;
				}
				$row[] = $translated;
			}
			$input[] = $row;
		}

		$output = $this->stacks($direction, $recipe->getOutput());
		if ($output === null) {
			return null;
		}

		return new ShapedRecipe(
			$recipe->getRecipeId(),
			$input,
			$output,
			$recipe->getUuid(),
			$recipe->getBlockName(),
			$recipe->getPriority(),
			$recipe->isSymmetric(),
			$recipe->getUnlockingRequirement(),
			$recipe->getRecipeNetId()
		);
	}

	private function shapeless(Direction $direction, ShapelessRecipe $recipe): ?ShapelessRecipe
	{
		$inputs = [];
		foreach ($recipe->getInputs() as $ingredient) {
			$translated = $this->items->ingredient($direction, $ingredient);
			if ($translated === null) {
				return null;
			}
			$inputs[] = $translated;
		}

		$outputs = $this->stacks($direction, $recipe->getOutputs());
		if ($outputs === null) {
			return null;
		}

		return new ShapelessRecipe(
			$recipe->getRecipeId(),
			$inputs,
			$outputs,
			$recipe->getUuid(),
			$recipe->getBlockName(),
			$recipe->getPriority(),
			$recipe->getUnlockingRequirement(),
			$recipe->getRecipeNetId()
		);
	}

	private function furnace(Direction $direction, FurnaceRecipe $recipe): ?FurnaceRecipe
	{
		$input = $this->items->idMeta($direction, $recipe->getInputId(), $recipe->getInputMeta() ?? 0);
		$result = $this->items->stackOrNull($direction, $recipe->getResult());
		if ($input === null || $result === null) {
			return null;
		}

		return new FurnaceRecipe(
			$recipe->getTypeId(),
			$input[0],
			$recipe->getInputMeta() === null ? null : $input[1],
			$result,
			$recipe->getBlockName()
		);
	}

	private function smithingTransform(Direction $direction, SmithingTransformRecipe $recipe): ?SmithingTransformRecipe
	{
		$template = $this->items->ingredient($direction, $recipe->getTemplate());
		$input = $this->items->ingredient($direction, $recipe->getInput());
		$addition = $this->items->ingredient($direction, $recipe->getAddition());
		$output = $this->items->stackOrNull($direction, $recipe->getOutput());
		if ($template === null || $input === null || $addition === null || $output === null) {
			return null;
		}

		return new SmithingTransformRecipe($recipe->getRecipeId(), $template, $input, $addition, $output, $recipe->getBlockName(), $recipe->getRecipeNetId());
	}

	private function smithingTrim(Direction $direction, SmithingTrimRecipe $recipe): ?SmithingTrimRecipe
	{
		$template = $this->items->ingredient($direction, $recipe->getTemplate());
		$input = $this->items->ingredient($direction, $recipe->getInput());
		$addition = $this->items->ingredient($direction, $recipe->getAddition());
		if ($template === null || $input === null || $addition === null) {
			return null;
		}

		return new SmithingTrimRecipe($recipe->getRecipeId(), $template, $input, $addition, $recipe->getBlockName(), $recipe->getRecipeNetId());
	}

	private function potionType(Direction $direction, PotionTypeRecipe $recipe): ?PotionTypeRecipe
	{
		$input = $this->items->idMeta($direction, $recipe->getInputItemId(), $recipe->getInputItemMeta());
		$ingredient = $this->items->idMeta($direction, $recipe->getIngredientItemId(), $recipe->getIngredientItemMeta());
		$output = $this->items->idMeta($direction, $recipe->getOutputItemId(), $recipe->getOutputItemMeta());
		if ($input === null || $ingredient === null || $output === null) {
			return null;
		}

		return new PotionTypeRecipe($input[0], $input[1], $ingredient[0], $ingredient[1], $output[0], $output[1]);
	}

	private function potionContainer(Direction $direction, PotionContainerChangeRecipe $recipe): ?PotionContainerChangeRecipe
	{
		$input = $this->items->id($direction, $recipe->getInputItemId());
		$ingredient = $this->items->id($direction, $recipe->getIngredientItemId());
		$output = $this->items->id($direction, $recipe->getOutputItemId());
		if ($input === null || $ingredient === null || $output === null) {
			return null;
		}

		return new PotionContainerChangeRecipe($input, $ingredient, $output);
	}

	private function materialReducer(Direction $direction, MaterialReducerRecipe $recipe): ?MaterialReducerRecipe
	{
		$input = $this->items->idMeta($direction, $recipe->getInputItemId(), $recipe->getInputItemMeta());
		if ($input === null) {
			return null;
		}

		$outputs = [];
		foreach ($recipe->getOutputs() as $output) {
			$id = $this->items->id($direction, $output->getItemId());
			if ($id === null) {
				return null;
			}
			$outputs[] = new MaterialReducerRecipeOutput($id, $output->getCount());
		}

		return new MaterialReducerRecipe($input[0], $input[1], $outputs);
	}

	/**
	 * @param ItemStack[] $stacks
	 * @return list<ItemStack>|null
	 */
	private function stacks(Direction $direction, array $stacks): ?array
	{
		$result = [];
		foreach ($stacks as $stack) {
			$translated = $this->items->stackOrNull($direction, $stack);
			if ($translated === null) {
				return null;
			}
			$result[] = $translated;
		}

		return $result;
	}

	/**
	 * @template T of object
	 * @param array<int, T|null> $recipes
	 * @return list<T>
	 */
	private static function filter(array $recipes): array
	{
		return array_values(array_filter($recipes, static fn(?object $recipe) => $recipe !== null));
	}
}
