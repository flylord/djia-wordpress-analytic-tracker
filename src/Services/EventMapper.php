<?php
declare(strict_types=1);

namespace CiWoocommerce\Services;

use CommerceIntelligence\Sdk\Event;
use CommerceIntelligence\Sdk\Events;

final class EventMapper {

	public function addToCart(int $productId, int $variationId, int $quantity, string $price, string $currency, ?string $anonymousId, ?string $userId, ?string $sessionId = null): Event {
		return Event::create('product_added_to_cart', array_merge($this->productIds($productId, $variationId), [
			'quantity' => $quantity,
			'unit_price' => Events::minorUnits($price, $currency),
			'currency' => $currency,
		]), $anonymousId, $userId, $sessionId);
	}

	public function removeFromCart(int $productId, int $variationId, int $quantity, ?string $anonymousId, ?string $userId, ?string $sessionId = null): Event {
		return Event::create('product_removed_from_cart', array_merge($this->productIds($productId, $variationId), [
			'quantity' => $quantity,
		]), $anonymousId, $userId, $sessionId);
	}

	/**
	 * @param array<int, array{product_id: int, variation_id: int, quantity: int, unit_price: string}> $items
	 */
	public function orderCompleted(int $orderId, array $items, string $total, string $currency, ?string $anonymousId, ?string $userId, ?string $sessionId = null): Event {
		$mapped = [];
		foreach ($items as $item) {
			$mapped[] = array_merge($this->productIds((int) $item['product_id'], (int) $item['variation_id']), [
				'quantity' => (int) $item['quantity'],
				'unit_price' => Events::minorUnits((string) $item['unit_price'], $currency),
			]);
		}

		return Event::create('order_completed', [
			'order_id' => (string) $orderId,
			'items' => $mapped,
			'revenue' => Events::minorUnits($total, $currency),
			'currency' => $currency,
		], $anonymousId, $userId, $sessionId);
	}

	public function paymentConfirmed(int $orderId, string $amount, string $currency, string $method, ?string $anonymousId, ?string $userId, ?string $sessionId = null): Event {
		return Event::create('payment_confirmed', [
			'order_id' => (string) $orderId,
			'amount' => Events::minorUnits($amount, $currency),
			'currency' => $currency,
			'method' => $method,
		], $anonymousId, $userId, $sessionId);
	}

	public function orderCancelled(int $orderId, string $status, string $reason, ?string $anonymousId, ?string $userId): Event {
		$properties = ['order_id' => (string) $orderId, 'status' => $status];
		if ($reason !== '') {
			$properties['reason'] = $reason;
		}

		return Event::create('order_cancelled', $properties, $anonymousId, $userId);
	}

	public function cancellationStatus(string $fromStatus, string $note): string {
		$note = strtolower($note);
		if (strpos($note, 'time limit reached') !== false) {
			return 'payment_timeout';
		}
		if ($fromStatus === 'failed') {
			return 'payment_failed';
		}
		if (strpos($note, 'cancelled by customer') !== false) {
			return 'customer_cancelled';
		}

		return 'merchant_cancelled';
	}

	/**
	 * @param array<int, array{product_id: int, variation_id: int, quantity: int, amount: string}> $items
	 */
	public function orderRefunded(int $orderId, int $refundId, string $amount, array $items, string $currency, ?string $anonymousId, ?string $userId): Event {
		$mapped = [];
		foreach ($items as $item) {
			$mapped[] = array_merge($this->productIds((int) $item['product_id'], (int) $item['variation_id']), [
				'quantity' => (int) $item['quantity'],
				'amount' => Events::minorUnits((string) $item['amount'], $currency),
			]);
		}

		$properties = [
			'order_id' => (string) $orderId,
			'refund_id' => (string) $refundId,
			'amount' => Events::minorUnits($amount, $currency),
			'currency' => $currency,
		];
		if ($mapped !== []) {
			$properties['items'] = $mapped;
		}

		return Event::create('order_refunded', $properties, $anonymousId, $userId);
	}

	public function userIdentified(string $userId, ?string $anonymousId): Event {
		return Event::create('user_identified', [], $anonymousId, $userId);
	}

	public function customerId(int $id): ?string {
		return $id > 0 ? (string) $id : null;
	}

	/**
	 * @return array<string, string>
	 */
	private function productIds(int $productId, int $variationId): array {
		$ids = ['product_id' => (string) $productId];
		if ($variationId > 0) {
			$ids['variation_id'] = (string) $variationId;
		}

		return $ids;
	}
}
