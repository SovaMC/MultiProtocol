<?php

declare(strict_types=1);

namespace sova\multiprotocol\session\login;

use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientData;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use stdClass;
use function implode;
use function is_string;
use function json_decode;
use function json_encode;
use function property_exists;
use function str_contains;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class ClientDataPatcher
{
	private const string REQUIRED_ANNOTATION = '@required';

	/** @var array<string, mixed>|null */
	private static ?array $defaults = null;

	private function __construct()
	{
	}

	/**
	 * @throws JwtException
	 */
	public static function patch(string $clientDataJwt): string
	{
		[$header, $payload, $signature] = JwtUtils::split($clientDataJwt);

		$claims = json_decode(JwtUtils::b64UrlDecode($payload), flags: JSON_THROW_ON_ERROR);
		if (!$claims instanceof stdClass) {
			throw new JwtException('ClientData JWT body is not an object');
		}

		$patched = false;
		foreach (self::defaults() as $name => $default) {
			if (!property_exists($claims, $name)) {
				$claims->{$name} = $default;
				$patched = true;
			}
		}

		if (!$patched) {
			return $clientDataJwt;
		}

		$encoded = JwtUtils::b64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

		return implode('.', [$header, $encoded, $signature]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function defaults(): array
	{
		if (self::$defaults !== null) {
			return self::$defaults;
		}

		$defaults = [];
		foreach ((new ReflectionClass(ClientData::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$doc = $property->getDocComment();
			$type = $property->getType();
			if (!is_string($doc) || !str_contains($doc, self::REQUIRED_ANNOTATION) || !$type instanceof ReflectionNamedType) {
				continue;
			}

			$defaults[$property->getName()] = match ($type->getName()) {
				'bool' => false,
				'int' => 0,
				'float' => 0.0,
				'string' => '',
				'array' => [],
				default => null,
			};
		}

		return self::$defaults = $defaults;
	}
}
