<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class SettingsService {
	public const OPTION = 'ci_woocommerce_settings';
	public const LAST_ERROR_OPTION = 'ci_woocommerce_last_error';
	public const DEFAULT_ENDPOINT = 'http://ci-collector:8080';

	/**
	 * @return array{endpoint: string, key_id: string, secret: string}
	 */
	public function get(): array {
		$stored = get_option(self::OPTION, []);
		$stored = is_array($stored) ? $stored : [];

		return [
			'endpoint' => isset($stored['endpoint']) && is_string($stored['endpoint']) && $stored['endpoint'] !== '' ? $stored['endpoint'] : self::DEFAULT_ENDPOINT,
			'key_id' => isset($stored['key_id']) && is_string($stored['key_id']) ? $stored['key_id'] : '',
			'secret' => isset($stored['secret']) && is_string($stored['secret']) ? $stored['secret'] : '',
		];
	}

	public function hasCredentials(): bool {
		$settings = $this->get();

		return $settings['key_id'] !== '' && $settings['secret'] !== '';
	}

	/**
	 * Saves the settings; an empty secret keeps the stored one.
	 */
	public function save(string $endpoint, string $keyId, string $secret): void {
		$current = $this->get();

		update_option(self::OPTION, [
			'endpoint' => $endpoint !== '' ? $endpoint : self::DEFAULT_ENDPOINT,
			'key_id' => $keyId,
			'secret' => $secret !== '' ? $secret : $current['secret'],
		], false);
	}

	public function recordError(Throwable $error): void {
		update_option(self::LAST_ERROR_OPTION, [
			'message' => $error->getMessage(),
			'time' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
		], false);
	}

	/**
	 * @return array{message: string, time: DateTimeImmutable}|null
	 */
	public function lastError(): ?array {
		$stored = get_option(self::LAST_ERROR_OPTION, null);
		if (!is_array($stored) || !isset($stored['message'], $stored['time']) || !is_string($stored['message']) || !is_string($stored['time'])) {
			return null;
		}

		return [
			'message' => $stored['message'],
			'time' => new DateTimeImmutable($stored['time'], new DateTimeZone('UTC')),
		];
	}
}
