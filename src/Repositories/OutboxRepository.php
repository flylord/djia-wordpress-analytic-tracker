<?php
declare(strict_types=1);

namespace CiWoocommerce\Repositories;

use CommerceIntelligence\Sdk\Outbox;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use wpdb;

final class OutboxRepository implements Outbox {
	public const TABLENAME = 'ci_outbox';

	private wpdb $wpdb;

	public function __construct(wpdb $wpdb) {
		$this->wpdb = $wpdb;
	}

	public function push(string $eventId, array $payload): void {
		$json = wp_json_encode($payload);
		if ($json === false) {
			throw new RuntimeException('Outbox payload is not encodable');
		}

		$inserted = $this->wpdb->insert($this->table(), [
			'event_id' => $eventId,
			'payload' => $json,
			'created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
		], ['%s', '%s', '%s']);

		if ($inserted === false) {
			throw new RuntimeException('Outbox insert failed: ' . $this->wpdb->last_error);
		}
	}

	public function peek(int $limit): array {
		if ($limit <= 0) {
			return [];
		}

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare("SELECT event_id, payload FROM {$this->table()} ORDER BY id ASC LIMIT %d", $limit),
			ARRAY_A
		);

		$result = [];
		$corrupt = [];
		foreach (is_array($rows) ? $rows : [] as $row) {
			$payload = json_decode((string) $row['payload'], true);
			if (is_array($payload)) {
				$result[(string) $row['event_id']] = $payload;
			} else {
				$corrupt[] = (string) $row['event_id'];
			}
		}

		if ($corrupt !== []) {
			$this->remove($corrupt);
		}

		return $result;
	}

	public function remove(array $eventIds): void {
		if ($eventIds === []) {
			return;
		}

		$placeholders = implode(', ', array_fill(0, count($eventIds), '%s'));
		$this->wpdb->query(
			$this->wpdb->prepare("DELETE FROM {$this->table()} WHERE event_id IN ({$placeholders})", array_map('strval', array_values($eventIds)))
		);
	}

	private function table(): string {
		return $this->wpdb->prefix . self::TABLENAME;
	}
}
