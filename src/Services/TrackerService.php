<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

use CiWoocommerce\Repositories\OutboxRepository;
use CommerceIntelligence\Sdk\Client;
use CommerceIntelligence\Sdk\Config;
use CommerceIntelligence\Sdk\Event;
use Throwable;

final class TrackerService {

	private OutboxRepository $outbox;
	private SettingsService $settings;
	private ?Client $client = null;
	private bool $pending = false;

	public function __construct(OutboxRepository $outbox, SettingsService $settings) {
		$this->outbox = $outbox;
		$this->settings = $settings;
	}

	public function track(Event $event): void {
		$client = $this->client();
		if ($client === null) {
			return;
		}

		$client->track($event);
		$this->pending = true;
	}

	public function recordError(Throwable $error): void {
		$this->settings->recordError($error);
	}

	public function flush(int $max = 100): int {
		$client = $this->client();

		return $client === null ? 0 : $client->flush($max);
	}

	public function flushIfPending(): void {
		if (!$this->pending) {
			return;
		}

		$this->pending = false;
		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		}
		$this->flush(100);
	}

	private function client(): ?Client {
		if ($this->client !== null) {
			return $this->client;
		}
		if (!$this->settings->hasCredentials()) {
			return null;
		}

		$values = $this->settings->get();
		$settings = $this->settings;
		$this->client = new Client(
			new Config($values['endpoint'], $values['key_id'], $values['secret']),
			$this->outbox,
			null,
			static function (Throwable $error) use ($settings): void {
				$settings->recordError($error);
			}
		);

		return $this->client;
	}
}
