<?php
declare(strict_types=1);

namespace CiWoocommerce\Hooks;

use CiWoocommerce\Services\EventGuard;
use CiWoocommerce\Services\EventMapper;
use CiWoocommerce\Services\TrackerService;
use CommerceIntelligence\Sdk\Visitor;
use WC_Order;

final class WooCommerceHooks {
	public const ANONYMOUS_ID_META = '_ci_anonymous_id';
	public const SESSION_ID_META = '_ci_session_id';
	public const COMPLETED_META = '_ci_order_completed_tracked';

	private EventMapper $mapper;
	private TrackerService $tracker;
	private EventGuard $guard;

	public function __construct(EventMapper $mapper, TrackerService $tracker, EventGuard $guard) {
		$this->mapper = $mapper;
		$this->tracker = $tracker;
		$this->guard = $guard;
	}

	public function init(): void {
		add_action('woocommerce_add_to_cart', [$this, 'onAddToCart'], 10, 4);
		add_action('woocommerce_cart_item_removed', [$this, 'onCartItemRemoved'], 10, 2);
		add_action('woocommerce_checkout_order_processed', [$this, 'onCheckoutOrderProcessed'], 10, 1);
		add_action('woocommerce_store_api_checkout_order_processed', [$this, 'onStoreApiOrderProcessed'], 10, 1);
		add_action('woocommerce_payment_complete', [$this, 'onPaymentComplete'], 10, 1);
		add_action('woocommerce_order_status_cancelled', [$this, 'onOrderCancelled'], 10, 3);
		add_action('woocommerce_order_refunded', [$this, 'onOrderRefunded'], 10, 2);
		add_action('wp_login', [$this, 'onLogin'], 10, 2);
		add_action('user_register', [$this, 'onUserRegister'], 10, 1);
		add_action('shutdown', [$this->tracker, 'flushIfPending'], 20);
	}

	public function onAddToCart($cartItemKey, $productId, $quantity, $variationId): void {
		$this->guard->run(function () use ($cartItemKey, $productId, $quantity, $variationId): void {
			$this->handleAddToCart($cartItemKey, $productId, $quantity, $variationId);
		}, [$this->tracker, 'recordError']);
	}

	private function handleAddToCart($cartItemKey, $productId, $quantity, $variationId): void {
		$variationId = (int) $variationId;
		$product = wc_get_product($variationId > 0 ? $variationId : (int) $productId);
		if (!$product) {
			return;
		}

		$this->tracker->track($this->mapper->addToCart(
			(int) $productId,
			$variationId,
			(int) $quantity,
			wc_format_decimal($product->get_price(), false, false),
			get_woocommerce_currency(),
			$this->currentAnonymousId(),
			$this->mapper->customerId(get_current_user_id()),
			$this->currentSessionId()
		));
	}

	public function onCartItemRemoved($cartItemKey, $cart): void {
		$this->guard->run(function () use ($cartItemKey, $cart): void {
			$this->handleCartItemRemoved($cartItemKey, $cart);
		}, [$this->tracker, 'recordError']);
	}

	private function handleCartItemRemoved($cartItemKey, $cart): void {
		$removed = isset($cart->removed_cart_contents[$cartItemKey]) ? $cart->removed_cart_contents[$cartItemKey] : null;
		if (!is_array($removed)) {
			return;
		}

		$this->tracker->track($this->mapper->removeFromCart(
			(int) ($removed['product_id'] ?? 0),
			(int) ($removed['variation_id'] ?? 0),
			(int) ($removed['quantity'] ?? 0),
			$this->currentAnonymousId(),
			$this->mapper->customerId(get_current_user_id()),
			$this->currentSessionId()
		));
	}

	public function onCheckoutOrderProcessed($orderId): void {
		$this->guard->run(function () use ($orderId): void {
			$this->handleCheckoutOrderProcessed($orderId);
		}, [$this->tracker, 'recordError']);
	}

	private function handleCheckoutOrderProcessed($orderId): void {
		$this->trackOrderCompleted(wc_get_order((int) $orderId));
	}

	public function onStoreApiOrderProcessed($order): void {
		$this->guard->run(function () use ($order): void {
			$this->handleStoreApiOrderProcessed($order);
		}, [$this->tracker, 'recordError']);
	}

	private function handleStoreApiOrderProcessed($order): void {
		$this->trackOrderCompleted($order);
	}

	public function onPaymentComplete($orderId): void {
		$this->guard->run(function () use ($orderId): void {
			$this->handlePaymentComplete($orderId);
		}, [$this->tracker, 'recordError']);
	}

	private function handlePaymentComplete($orderId): void {
		$order = wc_get_order((int) $orderId);
		if (!$order instanceof WC_Order) {
			return;
		}

		$this->tracker->track($this->mapper->paymentConfirmed(
			$order->get_id(),
			wc_format_decimal($order->get_total(), false, false),
			$order->get_currency(),
			(string) $order->get_payment_method(),
			$this->orderAnonymousId($order),
			$this->mapper->customerId($order->get_customer_id()),
			$this->orderSessionId($order)
		));
	}

	public function onOrderCancelled($orderId, $order = null, $transition = null): void {
		$this->guard->run(function () use ($orderId, $order, $transition): void {
			$this->handleOrderCancelled($orderId, $order, $transition);
		}, [$this->tracker, 'recordError']);
	}

	private function handleOrderCancelled($orderId, $order = null, $transition = null): void {
		$order = $order instanceof WC_Order ? $order : wc_get_order((int) $orderId);
		if (!$order instanceof WC_Order) {
			return;
		}

		$reason = is_array($transition) && isset($transition['note']) && is_string($transition['note']) ? $transition['note'] : '';
		$from = is_array($transition) && isset($transition['from']) && is_string($transition['from']) ? $transition['from'] : '';

		$this->tracker->track($this->mapper->orderCancelled(
			$order->get_id(),
			$this->mapper->cancellationStatus($from, $reason),
			$reason,
			$this->orderAnonymousId($order),
			$this->mapper->customerId($order->get_customer_id())
		));
	}

	public function onOrderRefunded($orderId, $refundId): void {
		$this->guard->run(function () use ($orderId, $refundId): void {
			$this->handleOrderRefunded($orderId, $refundId);
		}, [$this->tracker, 'recordError']);
	}

	private function handleOrderRefunded($orderId, $refundId): void {
		$order = wc_get_order((int) $orderId);
		$refund = wc_get_order((int) $refundId);
		if (!$order instanceof WC_Order || !$refund) {
			return;
		}

		$items = [];
		foreach ($refund->get_items() as $item) {
			$items[] = [
				'product_id' => (int) $item->get_product_id(),
				'variation_id' => (int) $item->get_variation_id(),
				'quantity' => abs((int) $item->get_quantity()),
				'amount' => wc_format_decimal(abs((float) $item->get_total() + (float) $item->get_total_tax()), false, false),
			];
		}

		$this->tracker->track($this->mapper->orderRefunded(
			$order->get_id(),
			(int) $refundId,
			wc_format_decimal(abs((float) $refund->get_amount()), false, false),
			$items,
			$order->get_currency(),
			$this->orderAnonymousId($order),
			$this->mapper->customerId($order->get_customer_id())
		));
	}

	public function onLogin($userLogin, $user): void {
		$this->guard->run(function () use ($userLogin, $user): void {
			$this->handleLogin($userLogin, $user);
		}, [$this->tracker, 'recordError']);
	}

	private function handleLogin($userLogin, $user): void {
		$this->trackUserIdentified((int) ($user->ID ?? 0));
	}

	public function onUserRegister($userId): void {
		$this->guard->run(function () use ($userId): void {
			$this->handleUserRegister($userId);
		}, [$this->tracker, 'recordError']);
	}

	private function handleUserRegister($userId): void {
		$userId = (int) $userId;
		$createdByAdmin = is_admin() && get_current_user_id() !== $userId;

		$this->trackUserIdentified($userId, !$createdByAdmin);
	}

	private function trackUserIdentified(int $userId, bool $attachAnonymousId = true): void {
		$id = $this->mapper->customerId($userId);
		if ($id === null) {
			return;
		}

		$this->tracker->track($this->mapper->userIdentified($id, $attachAnonymousId ? $this->currentAnonymousId() : null));
	}

	private function trackOrderCompleted($order): void {
		if (!$order instanceof WC_Order || $order->get_meta(self::COMPLETED_META) !== '') {
			return;
		}

		$anonymousId = $this->currentAnonymousId();
		if ($anonymousId !== null) {
			$order->update_meta_data(self::ANONYMOUS_ID_META, $anonymousId);
		}
		$sessionId = $this->currentSessionId();
		if ($sessionId !== null) {
			$order->update_meta_data(self::SESSION_ID_META, $sessionId);
		}
		$order->update_meta_data(self::COMPLETED_META, '1');
		$order->save();

		$items = [];
		foreach ($order->get_items() as $item) {
			$quantity = max(1, (int) $item->get_quantity());
			$items[] = [
				'product_id' => (int) $item->get_product_id(),
				'variation_id' => (int) $item->get_variation_id(),
				'quantity' => (int) $item->get_quantity(),
				'unit_price' => wc_format_decimal(((float) $item->get_total() + (float) $item->get_total_tax()) / $quantity, 6, false),
			];
		}

		$this->tracker->track($this->mapper->orderCompleted(
			$order->get_id(),
			$items,
			wc_format_decimal($order->get_total(), false, false),
			$order->get_currency(),
			$anonymousId,
			$this->mapper->customerId($order->get_customer_id()),
			$sessionId
		));
	}

	private function orderAnonymousId(WC_Order $order): ?string {
		$stored = $order->get_meta(self::ANONYMOUS_ID_META);

		return is_string($stored) && $stored !== '' ? $stored : null;
	}

	private function orderSessionId(WC_Order $order): ?string {
		$stored = $order->get_meta(self::SESSION_ID_META);

		return is_string($stored) && $stored !== '' ? $stored : null;
	}

	private function currentSessionId(): ?string {
		return Visitor::sessionId(wp_unslash($_COOKIE));
	}

	private function currentAnonymousId(): ?string {
		return Visitor::anonymousId(wp_unslash($_COOKIE));
	}
}
