<?php

declare(strict_types=1);

namespace sova\multiprotocol\packet;

use Closure;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use sova\multiprotocol\protocol\Protocol;
use sova\multiprotocol\protocol\ProtocolException;
use sova\multiprotocol\session\ProtocolSession;
use function substr;

final class PacketRegistry
{
	/** @var array<string, array<int, list<PacketRewriter>>> */
	private array $rewriters = [];

	/** @var array<string, array<int, int>> */
	private array $remaps = [];

	/** @var array<string, array<int, true>> */
	private array $cancelled = [];

	public function __construct(
		private readonly Protocol $protocol
	) {
	}

	public function add(PacketRewriter ...$rewriters): self
	{
		foreach ($rewriters as $rewriter) {
			foreach ($rewriter->getDirections() as $direction) {
				$this->rewriters[$direction->value][$rewriter->getPacketId()][] = $rewriter;
			}
		}

		return $this;
	}

	/**
	 * @param Closure(PacketWrapper): void $rewriter
	 */
	public function serverbound(int $id, Closure $rewriter): self
	{
		return $this->add(new ClosurePacketRewriter($id, Direction::SERVERBOUND, $rewriter));
	}

	/**
	 * @param Closure(PacketWrapper): void $rewriter
	 */
	public function clientbound(int $id, Closure $rewriter): self
	{
		return $this->add(new ClosurePacketRewriter($id, Direction::CLIENTBOUND, $rewriter));
	}

	public function remap(Direction $direction, int $from, int $to): self
	{
		$this->remaps[$direction->value][$from] = $to;

		return $this;
	}

	public function cancel(Direction $direction, int ...$ids): self
	{
		foreach ($ids as $id) {
			$this->cancelled[$direction->value][$id] = true;
		}

		return $this;
	}

	public function hasRewriter(Direction $direction, int $id): bool
	{
		return isset($this->rewriters[$direction->value][$id]);
	}

	/**
	 * @return list<string>
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	public function translate(Direction $direction, string $buffer, ProtocolSession $session): array
	{
		$id = PacketHeader::peekId($buffer);

		if (isset($this->cancelled[$direction->value][$id])) {
			return [];
		}

		$rewriters = $this->rewriters[$direction->value][$id] ?? null;
		$target = $this->remaps[$direction->value][$id] ?? $id;

		if ($rewriters === null) {
			return [$target === $id ? $buffer : self::replaceId($buffer, $target)];
		}

		$packet = new PacketWrapper($buffer, $direction, $this->protocol, $session);
		$packet->setId($target);

		foreach ($rewriters as $rewriter) {
			$rewriter->rewrite($packet);
			if ($packet->isCancelled()) {
				break;
			}
		}

		return $packet->build();
	}

	/**
	 * @throws DataDecodeException
	 */
	private static function replaceId(string $buffer, int $id): string
	{
		$reader = new ByteBufferReader($buffer);
		$header = PacketHeader::read($reader);
		$header->id = $id;

		return $header->encode() . substr($buffer, $reader->getOffset());
	}
}
