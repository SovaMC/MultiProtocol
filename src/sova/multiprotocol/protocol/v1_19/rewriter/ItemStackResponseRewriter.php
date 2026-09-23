<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pocketmine\network\mcpe\protocol\ItemStackResponsePacket;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseContainerInfo;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\translation\inventory\ContainerSlotTranslator;
use function array_map;

/**
 * @extends TypedPacketRewriter<ItemStackResponsePacket>
 */
final class ItemStackResponseRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly ContainerSlotTranslator $slots,
		int $codecProtocolId
	) {
		parent::__construct(ItemStackResponsePacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$this->replace($packet, ItemStackResponsePacket::create(array_map($this->response(...), $this->peek($packet)->getResponses())));
	}

	private function response(ItemStackResponse $response): ItemStackResponse
	{
		$containers = $response->getContainerInfos();

		return new ItemStackResponse(
			$response->getResult(),
			$response->getRequestId(),
			$containers === null ? null : array_map($this->container(...), $containers)
		);
	}

	private function container(ItemStackResponseContainerInfo $container): ItemStackResponseContainerInfo
	{
		return new ItemStackResponseContainerInfo(
			$this->slots->containerName(Direction::CLIENTBOUND, $container->getContainerName()),
			$container->getSlots()
		);
	}
}
