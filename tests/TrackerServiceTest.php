<?php

declare(strict_types=1);

namespace CiWoocommerce\Tests;

use CiWoocommerce\Repositories\OutboxRepository;
use CiWoocommerce\Services\SettingsService;
use CiWoocommerce\Services\TrackerService;
use PHPUnit\Framework\TestCase;

final class TrackerServiceTest extends TestCase {

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testFlushIfPendingSwallowsErrors(): void {
		eval('class wpdb {}');
		$tracker = new TrackerService(new OutboxRepository(new \wpdb()), new SettingsService());
		$pending = new \ReflectionProperty(TrackerService::class, 'pending');
		$pending->setAccessible(true);
		$pending->setValue($tracker, true);

		$tracker->flushIfPending();

		$this->addToAssertionCount(1);
	}
}
