<?php
declare(strict_types=1);

namespace CiWoocommerce\Crons;

use CiWoocommerce\Services\TrackerService;

final class FlushOutbox {
	public const HOOK = 'ci_woocommerce_flush_outbox';
	public const SCHEDULE = 'ci_woocommerce_every_minute';

	private TrackerService $tracker;

	public function __construct(TrackerService $tracker) {
		$this->tracker = $tracker;
	}

	public function init(): void {
		add_filter('cron_schedules', [$this, 'addSchedule']);
		add_action('init', [$this, 'schedule']);
		add_action(self::HOOK, [$this, 'run']);
	}

	public function addSchedule(array $schedules): array {
		$schedules[self::SCHEDULE] = [
			'interval' => 60,
			'display' => __('Every minute', 'ci-woocommerce'),
		];

		return $schedules;
	}

	public function schedule(): void {
		if (!wp_next_scheduled(self::HOOK)) {
			wp_schedule_event(time(), self::SCHEDULE, self::HOOK);
		}
	}

	public function unscheduleAll(): void {
		wp_clear_scheduled_hook(self::HOOK);
	}

	public function run(): void {
		$this->tracker->flush(100);
	}
}
