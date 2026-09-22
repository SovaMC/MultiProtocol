<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol;

use sova\multiprotocol\protocol\transport\Transport;
use function array_keys;
use function ksort;

final class ProtocolRegistry
{
	/** @var array<int, Protocol> */
	private array $protocols = [];

	/** @var array<int, ProtocolPipeline> */
	private array $pipelines = [];

	public function register(Protocol $protocol): void
	{
		$id = $protocol->version->id;

		if (ProtocolConstants::isNative($id)) {
			throw new ProtocolException($protocol->version . ' is natively supported by the server');
		}

		if (isset($this->protocols[$id])) {
			throw new ProtocolException($protocol->version . ' is already registered');
		}

		$this->protocols[$id] = $protocol;
		ksort($this->protocols);
		$this->pipelines = [];
	}

	public function unregister(int $protocolId): void
	{
		unset($this->protocols[$protocolId]);
		$this->pipelines = [];
	}

	public function get(int $protocolId): ?Protocol
	{
		return $this->protocols[$protocolId] ?? null;
	}

	/**
	 * @return array<int, Protocol>
	 */
	public function getAll(): array
	{
		return $this->protocols;
	}

	public function isVirtual(int $protocolId): bool
	{
		return isset($this->protocols[$protocolId]);
	}

	public function isSupported(int $protocolId): bool
	{
		if (ProtocolConstants::isNative($protocolId)) {
			return true;
		}

		if (!isset($this->protocols[$protocolId])) {
			return false;
		}

		try {
			$this->getPipeline($protocolId);
			return true;
		} catch (ProtocolException) {
			return false;
		}
	}

	/**
	 * @throws ProtocolException
	 */
	public function getPipeline(int $clientProtocolId): ProtocolPipeline
	{
		return $this->pipelines[$clientProtocolId] ??= $this->resolve($clientProtocolId);
	}

	/**
	 * @return list<int>
	 */
	public function getRaknetVersions(): array
	{
		$versions = [Transport::RAKNET_VERSION => true];

		foreach ($this->protocols as $protocol) {
			$versions[$protocol->version->transport->raknetVersion] = true;
		}

		return array_keys($versions);
	}

	/**
	 * @throws ProtocolException
	 */
	private function resolve(int $clientProtocolId): ProtocolPipeline
	{
		$protocols = [];
		$visited = [];
		$current = $clientProtocolId;

		while (!ProtocolConstants::isNative($current)) {
			if (isset($visited[$current])) {
				throw new ProtocolException('Protocol chain of ' . $clientProtocolId . ' contains a cycle at ' . $current);
			}

			$protocol = $this->protocols[$current] ?? throw new ProtocolException('Protocol chain of ' . $clientProtocolId . ' is broken: ' . $current . ' is not registered');

			$visited[$current] = true;
			$protocols[] = $protocol;
			$current = $protocol->targetProtocolId;
		}

		if ($protocols === []) {
			throw new ProtocolException($clientProtocolId . ' is a native protocol and does not need translation');
		}

		$client = $protocols[0]->version;
		if ($client->usesCompressionHeader() !== ProtocolConstants::usesCompressionHeader($current)) {
			throw new ProtocolException($client . ' and native ' . $current . ' use incompatible batch framing');
		}

		return new ProtocolPipeline($protocols, $current);
	}
}
