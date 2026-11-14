<?php
declare(strict_types=1);

namespace CiWoocommerce\Commands;

use CiWoocommerce\Services\CatalogueMapper;
use CiWoocommerce\Services\TrackerService;
use Generator;
use WP_CLI;

final class SyncCatalogueCommand {
	public const PAGE = 100;

	private CatalogueMapper $mapper;
	private TrackerService $tracker;

	public function __construct(CatalogueMapper $mapper, TrackerService $tracker) {
		$this->mapper = $mapper;
		$this->tracker = $tracker;
	}

	/**
	 * Sends product_upserted for every published product and variation.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<n>]
	 * : Events per flush.
	 * ---
	 * default: 100
	 * ---
	 */
	public function __invoke(array $args, array $assoc): void {
		$sync = $this->tracker->catalogueSync(max(1, (int) ($assoc['batch'] ?? 100)));
		if ($sync === null) {
			WP_CLI::error('Set the key id and secret in Settings, Commerce Intelligence first.');
		}

		$count = $sync->sync($this->products());

		WP_CLI::success(sprintf('Sent %d products.', $count));
	}

	private function products(): Generator {
		$page = 1;
		do {
			$products = wc_get_products([
				'status' => 'publish',
				'type' => array_merge(array_keys(wc_get_product_types()), ['variation']),
				'limit' => self::PAGE,
				'page' => $page,
				'orderby' => 'ID',
				'order' => 'ASC',
			]);
			foreach ($products as $product) {
				$data = $this->mapper->map($product);
				if ($data !== null) {
					yield $data;
				}
			}
			++$page;
		} while (count($products) === self::PAGE);
	}
}
