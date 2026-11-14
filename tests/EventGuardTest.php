<?php

declare(strict_types=1);

namespace CiWoocommerce\Tests;

use CiWoocommerce\Services\EventGuard;
use PHPUnit\Framework\TestCase;

final class EventGuardTest extends TestCase {

	public function testThrowingWorkDoesNotPropagateAndRecordsError(): void {
		$recorded = [];

		(new EventGuard())->run(
			static function (): void {
				throw new \InvalidArgumentException('bad amount');
			},
			static function (\Throwable $error) use (&$recorded): void {
				$recorded[] = $error->getMessage();
			}
		);

		$this->assertSame(['bad amount'], $recorded);
	}

	public function testFailingRecorderDoesNotPropagate(): void {
		(new EventGuard())->run(
			static function (): void {
				throw new \RuntimeException('a');
			},
			static function (): void {
				throw new \RuntimeException('b');
			}
		);

		$this->addToAssertionCount(1);
	}

	public function testWorkRunsWhenNothingThrows(): void {
		$ran = false;
		$errored = false;

		(new EventGuard())->run(static function () use (&$ran): void {
			$ran = true;
		}, function () use (&$errored): void {
			$errored = true;
		});

		$this->assertTrue($ran);
		$this->assertFalse($errored);
	}
}
