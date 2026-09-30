<?php

declare(strict_types=1);

namespace sova\multiprotocol\translation;

use sova\multiprotocol\translation\block\BlockActorTranslator;
use sova\multiprotocol\translation\block\BlockMapping;
use sova\multiprotocol\translation\block\ChunkTranslator;
use sova\multiprotocol\translation\block\VariantTranslator;
use sova\multiprotocol\translation\item\ItemTranslator;
use sova\multiprotocol\translation\item\RecipeTranslator;
use sova\multiprotocol\translation\item\TransactionTranslator;

final readonly class TranslationContext
{
	public BlockMapping $blocks;
	public ItemTranslator $items;
	public ChunkTranslator $chunks;
	public VariantTranslator $variants;
	public TransactionTranslator $transactions;
	public RecipeTranslator $recipes;
	public ItemTranslator $exactItems;

	public function __construct(
		public ProtocolMappings $mappings,
		public int $codecProtocolId,
		public ?BlockActorTranslator $blockActors = null
	) {
		$this->blocks = $mappings->blocks;
		$this->items = $mappings->itemTranslator;
		$this->chunks = new ChunkTranslator($this->blocks);
		$this->variants = new VariantTranslator($this->blocks);
		$this->transactions = new TransactionTranslator($this->items, $this->blocks);
		$this->exactItems = $this->items->withoutRenames();
		$this->recipes = new RecipeTranslator($this->exactItems);
	}
}
