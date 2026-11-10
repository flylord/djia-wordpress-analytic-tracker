<?php

declare(strict_types=1);

namespace CiWoocommerce\Core\System;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;

final class Container {

	public static ?Container $instance = null;
	private array $registry;

	public static function createInstance(array $registry = []): self {
		if (self::$instance === null) {
			self::$instance = new self($registry);
		}

		return self::$instance;
	}

	public static function getInstance(): self {
		if (self::$instance === null) {
			throw new ContainerNotCreatedException('Container is not created');
		}

		return self::$instance;
	}

	protected function __construct(array $registry = []) {
		$this->registry = $registry;
	}

	public function set(string $name, Closure $value): void {
		if (array_key_exists($name, $this->registry)) {
			$this->registry[$name] = $value;
		}
	}

	private function getBuildIn(string $name) {
		if (!array_key_exists($name, $this->registry)) {
			throw new Exception(sprintf('Param %s do not exist', $name));
		}

		return $this->registry[$name]();
	}

	public function get(string $className): object {
		if (array_key_exists($className, $this->registry)) {
			return $this->registry[$className]();
		}

		$reflector = new ReflectionClass($className);

		$isSingleton = $reflector->hasMethod('getInstance');
		if ($isSingleton) {
			$getInstance = $reflector->getMethod('getInstance');
			$dependencies = $this->getDependencies($getInstance);
			$obj = call_user_func([$className, 'getInstance'], ...$dependencies);
			$this->set($className, fn() => $obj);
			return $obj;
		}


		$constructor = $reflector->getConstructor();
		if ($constructor === null) {
			$obj = new $className;
			$this->set($className, fn(): object => $obj);
			return $obj;
		}

		$dependencies = $this->getDependencies($constructor);
		$obj = new $className(...$dependencies);
		$this->set($className, fn(): object => $obj);
		return $obj;
	}

	public function call(object $object, string $methodName): mixed {
		$reflector = new ReflectionObject($object);
		$method = $reflector->getMethod($methodName);

		$dependencies = [];

		$parameters = $method->getParameters();
		foreach ($parameters as $parameter) {
			$dependencies[] = $this->get((string)$parameter->getType());
		}

		return call_user_func_array([$object, $methodName], $dependencies);
	}

	private function getDependencies(?ReflectionMethod $fnc = null): array {
		$dependencies = [];

		if (!$fnc instanceof ReflectionMethod) {
			return $dependencies;
		}

		foreach ($fnc->getParameters() as $parameter) {
			$type = $parameter->getType();
			if ($type === null) {
				continue;
			}

            $dependencies[] = $type->isBuiltin() ? $this->getBuildIn($parameter->getName()) : $this->get($type->getName());
		}

		return $dependencies;
	}

}
