<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ItemStackResponsePacket;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseContainerInfo;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\protocol\v1_16_100\Protocol1_16_100;
use sova\multiprotocol\protocol\v1_16_200\Protocol1_16_200;
use sova\multiprotocol\protocol\v1_16_210\Protocol1_16_210;
use sova\multiprotocol\translation\inventory\ContainerSlotTranslator;
use function array_map;
use function count;

/**
 * @extends TypedPacketRewriter<ItemStackResponsePacket>
 */
final class ItemStackResponseRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly ContainerSlotTranslator $slots,
		private readonly int $clientProtocolId,
		int $codecProtocolId
	) {
		parent::__construct(ItemStackResponsePacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$responses = array_map($this->response(...), $this->peek($packet)->getResponses());
		if ($this->clientProtocolId >= Protocol1_16_210::PROTOCOL) {
			$this->replace($packet, ItemStackResponsePacket::create($responses));
			return;
		}

		$out = new ByteBufferWriter();
		VarInt::writeUnsignedInt($out, count($responses));
		foreach ($responses as $response) {
			if ($this->clientProtocolId >= Protocol1_16_100::PROTOCOL) {
				Byte::writeUnsigned($out, $response->getResult());
			} else {
				CommonTypes::putBool($out, $response->getResult() === ItemStackResponse::RESULT_OK);
			}
			CommonTypes::writeItemStackRequestId($out, $response->getRequestId());
			if ($response->getResult() !== ItemStackResponse::RESULT_OK) {
				continue;
			}
			$containers = $response->getContainerInfos() ?? [];
			VarInt::writeUnsignedInt($out, count($containers));
			foreach ($containers as $container) {
				Byte::writeUnsigned($out, $container->getContainerName()->getContainerId());
				VarInt::writeUnsignedInt($out, count($container->getSlots()));
				foreach ($container->getSlots() as $slot) {
					Byte::writeUnsigned($out, $slot->getSlot());
					Byte::writeUnsigned($out, $slot->getHotbarSlot());
					Byte::writeUnsigned($out, $slot->getCount());
					CommonTypes::writeServerItemStackId($out, $slot->getItemStackId() ?? 0);
					if ($this->clientProtocolId >= Protocol1_16_200::PROTOCOL) {
						CommonTypes::putString($out, $slot->getCustomName());
					}
				}
			}
		}
		$packet->replacePayload($out->getData());
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
