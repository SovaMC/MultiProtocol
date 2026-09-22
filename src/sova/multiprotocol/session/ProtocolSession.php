<?php

declare(strict_types=1);

namespace sova\multiprotocol\session;

use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use sova\multiprotocol\packet\Direction;
use sova\multiprotocol\protocol\Protocol;
use sova\multiprotocol\protocol\ProtocolException;
use sova\multiprotocol\protocol\ProtocolPipeline;
use sova\multiprotocol\protocol\ProtocolVersion;

final class ProtocolSession
{
	public ProtocolVersion $client {
		get => $this->pipeline->client;
	}

	public int $serverProtocolId {
		get => $this->pipeline->serverProtocolId;
	}

	/** @var array<class-string, object> */
	private array $storage = [];

	public function __construct(
		public readonly MultiProtocolNetworkSession $networkSession,
		public readonly ProtocolPipeline $pipeline
	) {
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T
	 */
	public function get(string $class): object
	{
		$value = $this->storage[$class] ??= new $class();

		if (!$value instanceof $class) {
			throw new ProtocolException('Storage entry ' . $class . ' has unexpected type');
		}

		return $value;
	}

	public function set(object $value): void
	{
		$this->storage[$value::class] = $value;
	}

	/**
	 * @param class-string $class
	 */
	public function has(string $class): bool
	{
		return isset($this->storage[$class]);
	}

	/**
	 * @param class-string $class
	 */
	public function remove(string $class): void
	{
		unset($this->storage[$class]);
	}

	/**
	 * @return list<string>
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	public function serverbound(string $buffer): array
	{
		return $this->pipeline->translate(Direction::SERVERBOUND, $buffer, $this);
	}

	/**
	 * @return list<string>
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	public function clientbound(string $buffer): array
	{
		return $this->pipeline->translate(Direction::CLIENTBOUND, $buffer, $this);
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	public function reply(Protocol $from, Direction $direction, string $buffer): void
	{
		$buffers = $this->pipeline->translate($direction, $buffer, $this, $from);

		if ($buffers === []) {
			return;
		}

		if ($direction === Direction::CLIENTBOUND) {
			$this->networkSession->sendTranslated($buffers);
		} else {
			$this->networkSession->handleTranslated($buffers);
		}
	}
}
