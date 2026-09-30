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
use function array_find;
use function class_exists;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function property_exists;
use function str_contains;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class ClientDataPatcher
{
	private const string REQUIRED_ANNOTATION = '@required';
	private const string ARRAY_TYPE_PATTERN = '/@var\s+\\\\?([\w\\\\]+)\[\]/';

	private const array KNOWN_DEFAULTS = [
		'TrustedSkin' => true,
	];

	/** @var array<class-string, array{defaults: array<string, mixed>, arrays: array<string, class-string>}> */
	private static array $schemas = [];

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

		if (!self::patchObject($claims, ClientData::class)) {
			return $clientDataJwt;
		}

		$encoded = JwtUtils::b64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

		return implode('.', [$header, $encoded, $signature]);
	}

	/**
	 * @param class-string $class
	 */
	private static function patchObject(stdClass $object, string $class): bool
	{
		$schema = self::schema($class);

		$patched = false;
		foreach ($schema['defaults'] as $name => $default) {
			if (!property_exists($object, $name)) {
				$object->{$name} = $default;
				$patched = true;
			}
		}

		foreach ($schema['arrays'] as $name => $elementClass) {
			$elements = $object->{$name} ?? null;
			if (!is_array($elements)) {
				continue;
			}
			foreach ($elements as $element) {
				if ($element instanceof stdClass && self::patchObject($element, $elementClass)) {
					$patched = true;
				}
			}
		}

		return $patched;
	}

	/**
	 * @param class-string $class
	 * @return array{defaults: array<string, mixed>, arrays: array<string, class-string>}
	 */
	private static function schema(string $class): array
	{
		if (isset(self::$schemas[$class])) {
			return self::$schemas[$class];
		}

		$reflection = new ReflectionClass($class);
		$defaults = [];
		$arrays = [];
		foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			$doc = $property->getDocComment();
			$type = $property->getType();
			if (!is_string($doc) || !str_contains($doc, self::REQUIRED_ANNOTATION) || !$type instanceof ReflectionNamedType) {
				continue;
			}

			$defaults[$property->getName()] = self::KNOWN_DEFAULTS[$property->getName()] ?? match ($type->getName()) {
				'bool' => false,
				'int' => 0,
				'float' => 0.0,
				'string' => '',
				'array' => [],
				default => null,
			};

			$elementClass = self::arrayElementClass($doc, $reflection->getNamespaceName());
			if ($elementClass !== null) {
				$arrays[$property->getName()] = $elementClass;
			}
		}

		return self::$schemas[$class] = ['defaults' => $defaults, 'arrays' => $arrays];
	}

	/**
	 * @return class-string|null
	 */
	private static function arrayElementClass(string $doc, string $namespace): ?string
	{
		if (preg_match(self::ARRAY_TYPE_PATTERN, $doc, $matches) !== 1) {
			return null;
		}

		return array_find([$namespace . '\\' . $matches[1], $matches[1]], static fn(string $candidate) => class_exists($candidate));
	}
}
