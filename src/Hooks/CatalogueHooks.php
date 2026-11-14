<?php
declare(strict_types=1);

namespace CiWoocommerce\Hooks;

use CiWoocommerce\Services\CatalogueMapper;
use CiWoocommerce\Services\EventGuard;
use CiWoocommerce\Services\TrackerService;
use CommerceIntelligence\Sdk\Events;
use WC_Product;

final class CatalogueHooks {
	private CatalogueMapper $mapper;
	private TrackerService $tracker;
	private EventGuard $guard;

	/** @var array<string, true> */
	private array $sent = [];

	public function __construct(CatalogueMapper $mapper, TrackerService $tracker, EventGuard $guard) {
		$this->mapper = $mapper;
		$this->tracker = $tracker;
		$this->guard = $guard;
	}

	public function init(): void {
		add_action('woocommerce_new_product', [$this, 'onProductSaved'], 10, 2);
		add_action('woocommerce_update_product', [$this, 'onProductSaved'], 10, 2);
		add_action('woocommerce_product_set_stock', [$this, 'onStockSet'], 10, 1);
		add_action('woocommerce_variation_set_stock', [$this, 'onStockSet'], 10, 1);
		add_action('shutdown', [$this->tracker, 'flushIfPending'], 20);
	}

	public function onProductSaved($productId, $product = null): void {
		$this->guard->run(function () use ($productId, $product): void {
			$this->send($product instanceof WC_Product ? $product : wc_get_product((int) $productId));
		}, [$this->tracker, 'recordError']);
	}

	public function onStockSet($product): void {
		$this->guard->run(function () use ($product): void {
			$this->send($product instanceof WC_Product ? $product : null);
		}, [$this->tracker, 'recordError']);
	}

	private function send($product): void {
		if (!$product instanceof WC_Product || $product->get_status() !== 'publish') {
			return;
		}
		$parentId = (int) $product->get_parent_id();
		if ($parentId > 0 && get_post_status($parentId) !== 'publish') {
			return;
		}

		$data = $this->mapper->map($product);
		if ($data === null) {
			return;
		}

		$key = md5((string) wp_json_encode($data));
		if (isset($this->sent[$key])) {
			return;
		}
		$this->sent[$key] = true;

		$this->tracker->track(Events::productUpserted($data));
	}
}
