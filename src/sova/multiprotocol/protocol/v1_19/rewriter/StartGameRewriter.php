<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\v1_19\rewriter;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\EditorWorldType;
use pocketmine\network\mcpe\protocol\types\EducationUriResource;
use pocketmine\network\mcpe\protocol\types\LevelSettings;
use pocketmine\network\mcpe\protocol\types\ServerTelemetryData;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\packet\PacketWrapper;
use sova\multiprotocol\packet\TypedPacketRewriter;
use sova\multiprotocol\protocol\v1_19_10\Protocol1_19_10;
use sova\multiprotocol\protocol\v1_19_20\Protocol1_19_20;
use sova\multiprotocol\protocol\v1_19_60\Protocol1_19_60;
use sova\multiprotocol\protocol\v1_19_80\Protocol1_19_80;
use sova\multiprotocol\translation\block\DimensionTracker;
use function count;

/**
 * @extends TypedPacketRewriter<StartGamePacket>
 */
final class StartGameRewriter extends TypedPacketRewriter
{
	public function __construct(
		private readonly ItemTypeDictionary $clientItems,
		private readonly int $clientProtocolId,
		int $codecProtocolId
	) {
		parent::__construct(StartGamePacket::class, $codecProtocolId, Direction::CLIENTBOUND);
	}

	protected function createPacket(): StartGamePacket
	{
		$packet = new StartGamePacket();
		$packet->serverTelemetryData = new ServerTelemetryData('', '', '', '');

		return $packet;
	}

	public function rewrite(PacketWrapper $packet): void
	{
		$startGame = $this->peek($packet);
		$packet->session->get(DimensionTracker::class)->setDimension($startGame->levelSettings->spawnSettings->getDimension());

		$out = new ByteBufferWriter();
		$this->write($out, $startGame);
		$packet->replacePayload($out->getData());
	}

	private function write(ByteBufferWriter $out, StartGamePacket $startGame): void
	{
		CommonTypes::putActorUniqueId($out, $startGame->actorUniqueId);
		CommonTypes::putActorRuntimeId($out, $startGame->actorRuntimeId);
		VarInt::writeSignedInt($out, $startGame->playerGamemode);
		CommonTypes::putVector3($out, $startGame->playerPosition);
		LE::writeFloat($out, $startGame->pitch);
		LE::writeFloat($out, $startGame->yaw);

		$this->writeLevelSettings($out, $startGame->levelSettings);

		CommonTypes::putString($out, $startGame->levelId);
		CommonTypes::putString($out, $startGame->worldName);
		CommonTypes::putString($out, $startGame->premiumWorldTemplateId);
		CommonTypes::putBool($out, $startGame->isTrial);
		$startGame->playerMovementSettings->write($out, $this->codecProtocolId);
		LE::writeUnsignedLong($out, $startGame->currentTick);
		VarInt::writeSignedInt($out, $startGame->enchantmentSeed);

		VarInt::writeUnsignedInt($out, count($startGame->blockPalette));
		foreach ($startGame->blockPalette as $entry) {
			CommonTypes::putString($out, $entry->getName());
			$out->writeByteArray($entry->getStates()->getEncodedNbt());
		}

		$items = $this->clientItems->getEntries();
		VarInt::writeUnsignedInt($out, count($items));
		foreach ($items as $entry) {
			CommonTypes::putString($out, $entry->getStringId());
			LE::writeSignedShort($out, $entry->getNumericId());
			CommonTypes::putBool($out, $entry->isComponentBased());
		}

		CommonTypes::putString($out, $startGame->multiplayerCorrelationId);
		CommonTypes::putBool($out, $startGame->enableNewInventorySystem);
		CommonTypes::putString($out, $startGame->serverSoftwareVersion);
		$out->writeByteArray($startGame->playerActorProperties->getEncodedNbt());
		LE::writeUnsignedLong($out, $startGame->blockPaletteChecksum);
		CommonTypes::putUUID($out, $startGame->worldTemplateId);
		if ($this->isAtLeast(Protocol1_19_20::PROTOCOL)) {
			CommonTypes::putBool($out, $startGame->enableClientSideChunkGeneration);
		}
		if ($this->isAtLeast(Protocol1_19_80::PROTOCOL)) {
			CommonTypes::putBool($out, $startGame->blockNetworkIdsAreHashes);
		}
	}

	private function writeLevelSettings(ByteBufferWriter $out, LevelSettings $settings): void
	{
		LE::writeUnsignedLong($out, $settings->seed);
		$settings->spawnSettings->write($out);
		VarInt::writeSignedInt($out, $settings->generator);
		VarInt::writeSignedInt($out, $settings->worldGamemode);
		VarInt::writeSignedInt($out, $settings->difficulty);
		CommonTypes::putBlockPosition($out, $settings->spawnPosition, false);
		CommonTypes::putBool($out, $settings->hasAchievementsDisabled);
		if ($this->isAtLeast(Protocol1_19_10::PROTOCOL)) {
			CommonTypes::putBool($out, $settings->editorWorldType !== EditorWorldType::NON_EDITOR);
		}
		if ($this->isAtLeast(Protocol1_19_80::PROTOCOL)) {
			CommonTypes::putBool($out, $settings->createdInEditorMode);
			CommonTypes::putBool($out, $settings->exportedFromEditorMode);
		}
		VarInt::writeSignedInt($out, $settings->time);
		VarInt::writeSignedInt($out, $settings->eduEditionOffer);
		CommonTypes::putBool($out, $settings->hasEduFeaturesEnabled);
		CommonTypes::putString($out, $settings->eduProductUUID);
		LE::writeFloat($out, $settings->rainLevel);
		LE::writeFloat($out, $settings->lightningLevel);
		CommonTypes::putBool($out, $settings->hasConfirmedPlatformLockedContent);
		CommonTypes::putBool($out, $settings->isMultiplayerGame);
		CommonTypes::putBool($out, $settings->hasLANBroadcast);
		VarInt::writeSignedInt($out, $settings->xboxLiveBroadcastMode);
		VarInt::writeSignedInt($out, $settings->platformBroadcastMode);
		CommonTypes::putBool($out, $settings->commandsEnabled);
		CommonTypes::putBool($out, $settings->isTexturePacksRequired);
		CommonTypes::putGameRules($out, $this->codecProtocolId, $settings->gameRules, true);
		$settings->experiments->write($out);
		CommonTypes::putBool($out, $settings->hasBonusChestEnabled);
		CommonTypes::putBool($out, $settings->hasStartWithMapEnabled);
		VarInt::writeSignedInt($out, $settings->defaultPlayerPermission);
		LE::writeSignedInt($out, $settings->serverChunkTickRadius);
		CommonTypes::putBool($out, $settings->hasLockedBehaviorPack);
		CommonTypes::putBool($out, $settings->hasLockedResourcePack);
		CommonTypes::putBool($out, $settings->isFromLockedWorldTemplate);
		CommonTypes::putBool($out, $settings->useMsaGamertagsOnly);
		CommonTypes::putBool($out, $settings->isFromWorldTemplate);
		CommonTypes::putBool($out, $settings->isWorldTemplateOptionLocked);
		CommonTypes::putBool($out, $settings->onlySpawnV1Villagers);
		if ($this->isAtLeast(Protocol1_19_20::PROTOCOL)) {
			CommonTypes::putBool($out, $settings->disablePersona);
			CommonTypes::putBool($out, $settings->disableCustomSkins);
		}
		if ($this->isAtLeast(Protocol1_19_60::PROTOCOL)) {
			CommonTypes::putBool($out, $settings->muteEmoteAnnouncements);
		}
		CommonTypes::putString($out, $settings->vanillaVersion);
		LE::writeSignedInt($out, $settings->limitedWorldWidth);
		LE::writeSignedInt($out, $settings->limitedWorldLength);
		CommonTypes::putBool($out, $settings->isNewNether);
		($settings->eduSharedUriResource ?? new EducationUriResource('', ''))->write($out);
		CommonTypes::writeOptional($out, $settings->experimentalGameplayOverride, CommonTypes::putBool(...));
		if ($this->isAtLeast(Protocol1_19_20::PROTOCOL)) {
			Byte::writeUnsigned($out, $settings->chatRestrictionLevel);
			CommonTypes::putBool($out, $settings->disablePlayerInteractions);
		}
	}

	private function isAtLeast(int $protocolId): bool
	{
		return $this->clientProtocolId >= $protocolId;
	}
}
