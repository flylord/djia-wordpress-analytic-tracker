<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

use Throwable;

final class EventGuard {

	public function run(callable $work, callable $onError): void {
		try {
			$work();
		} catch (Throwable $error) {
			try {
				$onError($error);
			} catch (Throwable $ignored) {
			}
		}
	}
}
