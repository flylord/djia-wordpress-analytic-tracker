<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

use Throwable;

final class SiteCodeInstaller {
	public const ITEM_ID = 'ci-tracker';
	private const CONTAINER = 'DjiaBuilder\\Core\\System\\Container';
	private const GLOBALS = 'DjiaBuilder\\Content\\Documents\\GlobalsRepository';
	private const PUBLISHER = 'DjiaBuilder\\Pipeline\\Publish\\Publisher';

	private SettingsService $settings;

	public function __construct(SettingsService $settings) {
		$this->settings = $settings;
	}

	public function snippet(string $publicKey, string $trackerUrl): string {
		$key = json_encode($publicKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		return "<script>\n"
			. "window.djia_ci = window.djia_ci || function () { (window.djia_ci.q = window.djia_ci.q || []).push(arguments); };\n"
			. "djia_ci('init', " . $key . ", {adapter: 'djia-builder'});\n"
			. "</script>\n"
			. '<script async src="' . htmlspecialchars($trackerUrl, ENT_QUOTES, 'UTF-8') . '"></script>';
	}

	public function configuredSnippet(): ?string {
		$values = $this->settings->get();
		if ($values['public_key'] === '' || $values['tracker_url'] === '') {
			return null;
		}

		return $this->snippet($values['public_key'], $values['tracker_url']);
	}

	/**
	 * Writes the tracker into DJIA Builder global site code and publishes it.
	 */
	public function install(): bool {
		if (!$this->builderAvailable()) {
			return false;
		}

		$snippet = $this->configuredSnippet();
		if ($snippet === null || !current_user_can('unfiltered_html')) {
			return false;
		}
		if ($this->isInstalled()) {
			return true;
		}

		try {
			if ($this->hasUnpublishedChanges()) {
				return false;
			}
			$this->write(function (array $scripts) use ($snippet): array {
				$scripts = $this->without($scripts);
				$scripts[] = [
					'id' => self::ITEM_ID,
					'name' => 'Commerce Intelligence tracker',
					'placement' => 'body_close',
					'html' => $snippet,
					'consent' => 'analytics',
					'load' => 'immediate',
					'scope' => ['kind' => 'site'],
					'enabled' => true,
				];

				return $scripts;
			});
		} catch (Throwable $error) {
			$this->settings->recordError($error);

			return false;
		}

		return true;
	}

	public function uninstall(): void {
		if (!$this->builderAvailable() || !current_user_can('unfiltered_html')) {
			return;
		}

		try {
			$repo = $this->service(self::GLOBALS);
			if ($this->findItem($this->scripts($repo->draft())) === null || $this->hasUnpublishedChanges()) {
				return;
			}
			$this->write(function (array $scripts): array {
				return $this->without($scripts);
			});
		} catch (Throwable $error) {
			$this->settings->recordError($error);
		}
	}

	public function hasUnpublishedChanges(): bool {
		if (!$this->builderAvailable()) {
			return false;
		}

		try {
			$repo = $this->service(self::GLOBALS);

			return !self::equalApartFromItem($repo->draft(), $repo->published(), self::ITEM_ID);
		} catch (Throwable $error) {
			return true;
		}
	}

	public static function equalApartFromItem(array $draft, array $published, string $itemId): bool {
		return self::comparable($draft, $itemId) === self::comparable($published, $itemId);
	}

	private static function comparable(array $globals, string $itemId): string {
		$plain = json_decode((string) json_encode($globals), true);
		$plain = is_array($plain) ? $plain : [];
		unset($plain['revision']);
		$code = isset($plain['customCode']) && is_array($plain['customCode']) ? $plain['customCode'] : [];
		if (isset($code['scripts']) && is_array($code['scripts'])) {
			$code['scripts'] = array_values(array_filter($code['scripts'], static fn ($item): bool => !is_array($item) || ($item['id'] ?? null) !== $itemId));
			if ($code['scripts'] === []) {
				unset($code['scripts']);
			}
		}
		if ($code === []) {
			unset($plain['customCode']);
		} else {
			$plain['customCode'] = $code;
		}

		return (string) json_encode(self::sorted($plain));
	}

	private static function sorted(array $value): array {
		if (array_keys($value) !== range(0, count($value) - 1)) {
			ksort($value);
		}

		return array_map(static fn ($v) => is_array($v) ? self::sorted($v) : $v, $value);
	}

	public function isInstalled(): bool {
		$snippet = $this->configuredSnippet();
		if ($snippet === null || !$this->builderAvailable()) {
			return false;
		}

		try {
			$item = $this->findItem($this->scripts($this->service(self::GLOBALS)->published()));
		} catch (Throwable $error) {
			return false;
		}

		return $item !== null && ($item['html'] ?? null) === $snippet && ($item['enabled'] ?? true) !== false;
	}

	private function write(callable $change): void {
		$repo = $this->service(self::GLOBALS);
		$user = wp_get_current_user();
		$draft = $repo->draft();
		$code = $this->toArray($draft['customCode'] ?? []);
		$code['scripts'] = array_values($change($this->scripts($draft)));
		$draft['customCode'] = $code;

		$repo->save((int) $draft['revision'], $draft, $user);
		$this->service(self::PUBLISHER)->publishGlobals($user);
	}

	private function builderAvailable(): bool {
		return class_exists(self::CONTAINER) && class_exists(self::GLOBALS) && class_exists(self::PUBLISHER);
	}

	private function service(string $class): object {
		return call_user_func([self::CONTAINER, 'getInstance'])->get($class);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function scripts(array $globals): array {
		$code = $this->toArray($globals['customCode'] ?? []);

		return array_values(array_filter(array_map([$this, 'toArray'], $this->toArray($code['scripts'] ?? [])), static fn (array $item): bool => $item !== []));
	}

	private function findItem(array $scripts): ?array {
		foreach ($scripts as $item) {
			if (($item['id'] ?? null) === self::ITEM_ID) {
				return $item;
			}
		}

		return null;
	}

	private function without(array $scripts): array {
		return array_values(array_filter($scripts, static fn (array $item): bool => ($item['id'] ?? null) !== self::ITEM_ID));
	}

	/**
	 * @param mixed $value
	 */
	private function toArray($value): array {
		if (is_object($value)) {
			$value = get_object_vars($value);
		}

		return is_array($value) ? $value : [];
	}
}
