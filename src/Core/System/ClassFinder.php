<?php

declare(strict_types=1);

namespace CiWoocommerce\Core\System;

use DirectoryIterator;
use IteratorIterator;

final class ClassFinder {

	private string $plugin_path;

	public function __construct(string $plugin_path) {
		$this->plugin_path = $plugin_path;
	}

	/**
	 * @return class-string[]
	 */
	public function tmp(string $namespace): array {
		$allClasses = get_declared_classes();
		$namespaceClasses = [];

		foreach ($allClasses as $class) {
			if (strpos($class, $namespace . '\\') === 0) {
				$namespaceClasses[] = $class;
			}
		}

		return $namespaceClasses;
	}

	public function getClassesInNamespace(string $namespace): array {
		$classes = [];
		$namespaceDir = $this->getNamespaceDirectory($namespace);
		if ($namespaceDir === '' || $namespaceDir === '0') {
			return $classes;
		}

		$dirList = new IteratorIterator(new DirectoryIterator($namespaceDir));
		foreach ($dirList as $file) {
			if ($file->isFile() && !$file->isDot()) {
				$class = '\\' . $namespace . '\\' . str_replace('.php', '', $file->getBasename());
				if (class_exists($class)) {
					$classes[] = $class;
				}
			}
		}

		return $classes;
	}

	private function getDefinedNamespaces(): array {
		$composerJsonPath = $this->plugin_path . 'composer.json';
		$composerConfig = json_decode(file_get_contents($composerJsonPath));

		//Apparently PHP doesn't like hyphens, so we use variable variables instead.
		$psr4 = "psr-4";
		return (array)$composerConfig->autoload->$psr4;
	}

	private function getNamespaceDirectory(string $namespace): string {
		$composerNamespaces = $this->getDefinedNamespaces();

		$namespaceFragments = explode('\\', $namespace);

		$nameSpaceDirectory = '';
		foreach ($composerNamespaces as $nsIndex => $nsDir) {
			if (strpos($namespace, (string) $nsIndex) === 0) {
				$nameSpaceDirectory = $nsDir;
				$namespaceFragments = array_filter($namespaceFragments, static function (string $fragment) use ($nsIndex): bool {
					return $fragment !== trim($nsIndex, '\\');
				});
			}
		}

		if (empty($nameSpaceDirectory)) {
			return '';
		}

		return realpath($this->plugin_path . $nameSpaceDirectory) . '/' . implode('/', $namespaceFragments);
	}

}
