<?php
/**
 * Real-WordPress contract test for the tickets.payment_id UNIQUE migration.
 *
 * A site that was hit by the replayed-confirm bug can already hold several
 * tickets for one PaymentIntent. Adding the UNIQUE index would then fail, and a
 * failing migration blocks every later migration and retries on every request.
 * The migration must instead DETECT and REPORT the duplicates (never delete
 * them — they may already have been scanned at the door or refunded), leave
 * activation healthy, and add the index once an operator has resolved them.
 */

namespace Peanut_Festival\Tests\ContractWp;

use WP_UnitTestCase;

class TicketPaymentUniqueMigrationTest extends WP_UnitTestCase {

    private string $table;

    public function set_up(): void {
        parent::set_up();

        \Peanut_Festival_Activator::activate();
        \Peanut_Festival_Migrations::run();

        global $wpdb;
        $this->table = $wpdb->prefix . 'pf_tickets';
        $wpdb->query("DELETE FROM {$this->table}");
        delete_option(\Peanut_Festival_Migrations::DUPLICATE_PAYMENTS_OPTION);

        // Simulate a pre-fix install: no UNIQUE index on payment_id yet.
        $this->drop_unique_index();
    }

    private function drop_unique_index(): void {
        global $wpdb;
        $has = $wpdb->get_results("SHOW INDEX FROM {$this->table} WHERE Key_name = 'payment_id_unique'");
        if (!empty($has)) {
            $wpdb->query("ALTER TABLE {$this->table} DROP INDEX payment_id_unique");
        }
    }

    private function has_unique_index(): bool {
        global $wpdb;
        $rows = $wpdb->get_results("SHOW INDEX FROM {$this->table} WHERE Key_name = 'payment_id_unique'");
        return !empty($rows) && (int) $rows[0]->Non_unique === 0;
    }

    private function insert_ticket(string $code, ?string $payment_id): int {
        global $wpdb;
        $wpdb->insert($this->table, [
            'attendee_id' => 1,
            'show_id' => 3,
            'quantity' => 1,
            'ticket_code' => $code,
            'payment_id' => $payment_id,
            'payment_status' => 'completed',
        ]);
        return (int) $wpdb->insert_id;
    }

    public function test_clean_table_gets_the_unique_index(): void {
        $this->insert_ticket('A0000001', 'pi_one');
        $this->insert_ticket('A0000002', 'pi_two');
        $this->insert_ticket('A0000003', null);
        $this->insert_ticket('A0000004', null);

        $result = \Peanut_Festival_Migrations::ensure_ticket_payment_unique_index();

        $this->assertSame('added', $result['status']);
        $this->assertTrue($this->has_unique_index());
        $this->assertFalse(get_option(\Peanut_Festival_Migrations::DUPLICATE_PAYMENTS_OPTION));
    }

    public function test_existing_duplicates_are_reported_not_deleted_and_do_not_fail(): void {
        $keep = $this->insert_ticket('B0000001', 'pi_dupe');
        $dupe = $this->insert_ticket('B0000002', 'pi_dupe');
        $this->insert_ticket('B0000003', 'pi_fine');

        $result = \Peanut_Festival_Migrations::ensure_ticket_payment_unique_index();

        $this->assertSame('duplicates', $result['status']);
        $this->assertFalse($this->has_unique_index(), 'Index must not be forced over duplicate data.');

        global $wpdb;
        $this->assertSame(3, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}"), 'No ticket may be deleted.');

        $report = get_option(\Peanut_Festival_Migrations::DUPLICATE_PAYMENTS_OPTION);
        $this->assertIsArray($report);
        $this->assertSame(1, $report['payment_count']);
        $this->assertSame(1, $report['extra_tickets']);
        $this->assertSame([$keep, $dupe], $report['payments']['pi_dupe']);
    }

    public function test_full_migration_run_succeeds_even_with_duplicates(): void {
        $this->insert_ticket('C0000001', 'pi_dupe');
        $this->insert_ticket('C0000002', 'pi_dupe');

        update_option('peanut_festival_db_version', '1.6.0');
        $result = \Peanut_Festival_Migrations::run();

        $this->assertTrue($result['success'], wp_json_encode($result));
        $this->assertFalse(\Peanut_Festival_Migrations::needs_migration());
        $this->assertIsArray(get_option(\Peanut_Festival_Migrations::DUPLICATE_PAYMENTS_OPTION));
    }

    public function test_index_is_added_once_duplicates_are_resolved(): void {
        $this->insert_ticket('D0000001', 'pi_dupe');
        $dupe = $this->insert_ticket('D0000002', 'pi_dupe');

        $this->assertSame('duplicates', \Peanut_Festival_Migrations::ensure_ticket_payment_unique_index()['status']);

        // After archiving the original payment link, an operator preserves the row.
        global $wpdb;
        $wpdb->update($this->table, ['payment_id' => null], ['id' => $dupe]);
        $this->assertSame(2, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}"));

        $this->assertSame('added', \Peanut_Festival_Migrations::ensure_ticket_payment_unique_index()['status']);
        $this->assertTrue($this->has_unique_index());
        $this->assertFalse(get_option(\Peanut_Festival_Migrations::DUPLICATE_PAYMENTS_OPTION));
    }

    public function test_duplicate_report_is_not_truncated_by_group_concat_limits(): void {
        global $wpdb;
        $previous = (int) $wpdb->get_var('SELECT @@SESSION.group_concat_max_len');
        $ids = [];
        for ($i = 0; $i < 12; $i++) {
            $ids[] = $this->insert_ticket(sprintf('L%07d', $i), 'pi_large_replay');
        }

        try {
            $wpdb->query('SET SESSION group_concat_max_len = 4');
            $result = \Peanut_Festival_Migrations::ensure_ticket_payment_unique_index();
            $this->assertSame('duplicates', $result['status']);
            $this->assertSame($ids, $result['duplicates']['pi_large_replay']);
            $report = get_option(\Peanut_Festival_Migrations::DUPLICATE_PAYMENTS_OPTION);
            $this->assertSame(11, $report['extra_tickets']);
            $this->assertSame($ids, $report['payments']['pi_large_replay']);
        } finally {
            $wpdb->query('SET SESSION group_concat_max_len = ' . $previous);
        }
    }

    public function test_empty_string_payment_ids_are_normalised_to_null(): void {
        $this->insert_ticket('E0000001', '');
        $this->insert_ticket('E0000002', '');

        $this->assertSame('added', \Peanut_Festival_Migrations::ensure_ticket_payment_unique_index()['status']);

        global $wpdb;
        $this->assertSame(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE payment_id = ''"));
    }

    public function test_is_idempotent(): void {
        $this->assertSame('added', \Peanut_Festival_Migrations::ensure_ticket_payment_unique_index()['status']);
        $this->assertSame('exists', \Peanut_Festival_Migrations::ensure_ticket_payment_unique_index()['status']);
    }
}
