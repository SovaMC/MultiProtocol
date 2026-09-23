<?php

declare(strict_types=1);

namespace sova\multiprotocol\utils;

use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

final class Reflection
{
	/** @var array<string, ReflectionProperty> */
	private static array $properties = [];

	/** @var array<string, ReflectionMethod> */
	private static array $methods = [];

	private function __construct()
	{
	}

	/**
	 * @param class-string $class
	 * @throws ReflectionException
	 */
	public static function get(string $class, object $instance, string $property): mixed
	{
		return self::property($class, $property)->getValue($instance);
	}

	/**
	 * @param class-string $class
	 * @throws ReflectionException
	 */
	public static function set(string $class, object $instance, string $property, mixed $value): void
	{
		self::property($class, $property)->setValue($instance, $value);
	}

	/**
	 * @param class-string $class
	 * @throws ReflectionException
	 */
	public static function invokeStatic(string $class, string $method, mixed ...$arguments): mixed
	{
		return (self::$methods[$class . '::' . $method] ??= new ReflectionMethod($class, $method))->invoke(null, ...$arguments);
	}

	/**
	 * @param class-string $class
	 * @throws ReflectionException
	 */
	private static function property(string $class, string $property): ReflectionProperty
	{
		return self::$properties[$class . '::' . $property] ??= new ReflectionProperty($class, $property);
	}
}
