<?php

declare(strict_types=1);

namespace sova\multiprotocol\packet;

use Closure;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use sova\multiprotocol\protocol\Protocol;
use sova\multiprotocol\protocol\ProtocolException;
use sova\multiprotocol\session\ProtocolSession;
use function strlen;
use function substr;

final class PacketWrapper
{
	public readonly int $sourceId;

	private readonly string $payload;
	private readonly ByteBufferReader $reader;
	private ByteBufferWriter $writer;

	private PacketHeader $header;

	private bool $cancelled = false;

	private ?DataPacket $packet = null;
	private int $packetProtocolId = 0;
	private bool $packetModified = false;

	/** @var list<string> */
	private array $before = [];

	/** @var list<string> */
	private array $after = [];

	/**
	 * @throws DataDecodeException
	 */
	public function __construct(
		private readonly string $buffer,
		public readonly Direction $direction,
		public readonly Protocol $protocol,
		public readonly ProtocolSession $session
	) {
		$headerReader = new ByteBufferReader($buffer);
		$this->header = PacketHeader::read($headerReader);
		$this->sourceId = $this->header->id;
		$this->payload = substr($buffer, $headerReader->getOffset());
		$this->reader = new ByteBufferReader($this->payload);
		$this->writer = new ByteBufferWriter();
	}

	public function getId(): int
	{
		return $this->header->id;
	}

	public function setId(int $id): void
	{
		$this->header->id = $id;
	}

	public function getBuffer(): string
	{
		return $this->buffer;
	}

	public function getHeader(): PacketHeader
	{
		return $this->header;
	}

	public function reader(): ByteBufferReader
	{
		return $this->reader;
	}

	public function writer(): ByteBufferWriter
	{
		return $this->writer;
	}

	/**
	 * @template T
	 * @param Closure(ByteBufferReader): T $read
	 * @phpstan-return T
	 */
	public function passthrough(Closure $read): mixed
	{
		$start = $this->reader->getOffset();
		$value = $read($this->reader);
		$this->writer->writeByteArray(substr($this->payload, $start, $this->reader->getOffset() - $start));

		return $value;
	}

	/**
	 * @throws DataDecodeException
	 */
	public function passthroughBytes(int $length): void
	{
		$this->writer->writeByteArray($this->reader->readByteArray($length));
	}

	public function passthroughAll(): void
	{
		$offset = $this->reader->getOffset();
		if ($offset < strlen($this->payload)) {
			$this->writer->writeByteArray(substr($this->payload, $offset));
			$this->reader->setOffset(strlen($this->payload));
		}
	}

	public function discardRemaining(): void
	{
		$this->reader->setOffset(strlen($this->payload));
	}

	public function getRemaining(): string
	{
		return substr($this->payload, $this->reader->getOffset());
	}

	/**
	 * @template T of DataPacket
	 * @param class-string<T>     $class
	 * @param (Closure(): T)|null $factory
	 * @return T
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	public function peek(string $class, int $protocolId, ?Closure $factory = null): DataPacket
	{
		if ($this->packet === null) {
			$packet = $factory !== null ? $factory() : new $class();
			$packet->decode(new ByteBufferReader($this->buffer), $protocolId);
			$this->packet = $packet;
			$this->packetProtocolId = $protocolId;
		}

		if (!$this->packet instanceof $class) {
			throw new ProtocolException('Packet ' . $this->sourceId . ' was already decoded as ' . $this->packet::class);
		}

		return $this->packet;
	}

	/**
	 * @template T of DataPacket
	 * @param class-string<T>     $class
	 * @param (Closure(): T)|null $factory
	 * @return T
	 * @throws PacketDecodeException
	 * @throws ProtocolException
	 */
	public function decode(string $class, int $protocolId, ?Closure $factory = null): DataPacket
	{
		$packet = $this->peek($class, $protocolId, $factory);
		$this->packetModified = true;

		return $packet;
	}

	public function replace(DataPacket $packet, int $protocolId): void
	{
		$this->packet = $packet;
		$this->packetProtocolId = $protocolId;
		$this->packetModified = true;
	}

	/**
	 * @throws DataDecodeException
	 */
	public function flush(): void
	{
		if ($this->packet === null || !$this->packetModified) {
			return;
		}

		$packet = $this->packet;
		$packet->senderSubId = $this->header->senderSubId;
		$packet->recipientSubId = $this->header->recipientSubId;

		$encoder = new ByteBufferWriter();
		$packet->encode($encoder, $this->packetProtocolId);
		$encoded = $encoder->getData();

		$decoder = new ByteBufferReader($encoded);
		$this->header = PacketHeader::read($decoder);
		$this->writer = new ByteBufferWriter(substr($encoded, $decoder->getOffset()));
		$this->discardRemaining();
		$this->packet = null;
		$this->packetModified = false;
	}

	/**
	 * @throws DataDecodeException
	 */
	public function getPayload(): string
	{
		$this->flush();
		$this->passthroughAll();

		return $this->writer->getData();
	}

	public function replacePayload(string $payload): void
	{
		$this->packet = null;
		$this->packetModified = false;
		$this->writer = new ByteBufferWriter($payload);
		$this->discardRemaining();
	}

	public function cancel(): void
	{
		$this->cancelled = true;
	}

	public function isCancelled(): bool
	{
		return $this->cancelled;
	}

	public function sendBefore(string $buffer): void
	{
		$this->before[] = $buffer;
	}

	public function sendAfter(string $buffer): void
	{
		$this->after[] = $buffer;
	}

	public function reply(string $buffer): void
	{
		$this->session->reply($this->protocol, $this->direction->opposite(), $buffer);
	}

	/**
	 * @return list<string>
	 * @throws DataDecodeException
	 */
	public function build(): array
	{
		$result = $this->before;

		if (!$this->cancelled) {
			$this->flush();
			$this->passthroughAll();
			$result[] = $this->header->encode() . $this->writer->getData();
		}

		foreach ($this->after as $buffer) {
			$result[] = $buffer;
		}

		return $result;
	}
}
