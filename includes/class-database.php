<?php
/**
 * Database helper class with comprehensive error handling
 *
 * @package Peanut_Festival
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Database {

    /**
     * Valid table names for this plugin
     */
    private static array $valid_tables = [
        'festivals',
        'shows',
        'performers',
        'venues',
        'volunteers',
        'volunteer_shifts',
        'volunteer_assignments',
        'vendors',
        'sponsors',
        'attendees',
        'transactions',
        'tickets',
        'voting_config',
        'votes',
        'vote_results',
        'messages',
        'message_recipients',
        'check_ins',
        'settings',
        'email_templates',
        'email_logs',
        'performer_applications',
        'vendor_applications',
        'job_queue',
        'booker_links',
        'competitions',
        'competition_matches',
        'match_votes',
    ];

    /**
     * Log database error with structured context
     *
     * @param string $operation The operation that failed
     * @param string $table The table name
     * @param array $context Additional context data
     * @param string $error The error message
     */
    private static function log_error(
        string $operation,
        string $table,
        array $context,
        string $error
    ): void {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'plugin' => 'peanut-festival',
            'operation' => $operation,
            'table' => $table,
            'error' => $error,
            'context' => $context,
        ];

        // Don't log sensitive data
        unset($log_entry['context']['password']);
        unset($log_entry['context']['api_key']);
        unset($log_entry['context']['secret']);

        error_log('Peanut Festival DB Error: ' . wp_json_encode($log_entry));

        // Fire action for external logging integrations
        do_action('peanut_festival_db_error', $log_entry);
    }

    /**
     * Validate table name to prevent SQL injection
     *
     * @param string $table The table name to validate
     * @return bool Whether the table name is valid
     */
    private static function validate_table(string $table): bool {
        return in_array($table, self::$valid_tables, true);
    }

    /**
     * Validate column names against allowed pattern
     *
     * @param array $columns Array of column names
     * @return bool Whether all columns are valid
     */
    private static function validate_columns(array $columns): bool {
        foreach ($columns as $column) {
            // Only allow alphanumeric and underscores
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the full table name with prefix
     *
     * @param string $table The base table name
     * @return string The full table name with prefix
     */
    public static function get_table_name(string $table): string {
        global $wpdb;
        return $wpdb->prefix . 'pf_' . $table;
    }

    /**
     * Insert a row into a table
     *
     * @param string $table The table name (without prefix)
     * @param array $data Column => value pairs to insert
     * @return int|false Insert ID on success, false on failure
     */
    public static function insert(string $table, array $data): int|false {
        global $wpdb;

        try {
            // Validate table name
            if (!self::validate_table($table)) {
                self::log_error('insert', $table, ['data_keys' => array_keys($data)], 'Invalid table name');
                return false;
            }

            // Validate column names
            if (!self::validate_columns(array_keys($data))) {
                self::log_error('insert', $table, ['data_keys' => array_keys($data)], 'Invalid column name detected');
                return false;
            }

            if (empty($data)) {
                self::log_error('insert', $table, [], 'No data provided for insert');
                return false;
            }

            $table_name = self::get_table_name($table);
            $result = $wpdb->insert($table_name, $data);

            if ($result === false) {
                self::log_error('insert', $table, [
                    'data_keys' => array_keys($data),
                    'wpdb_error' => $wpdb->last_error,
                    'last_query' => $wpdb->last_query,
                ], $wpdb->last_error ?: 'Unknown insert error');
                return false;
            }

            return $wpdb->insert_id;

        } catch (Throwable $e) {
            self::log_error('insert', $table, [
                'data_keys' => array_keys($data),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'Exception during insert: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update rows in a table
     *
     * @param string $table The table name (without prefix)
     * @param array $data Column => value pairs to update
     * @param array $where Column => value pairs for WHERE clause
     * @return int|false Number of rows affected, or false on error
     */
    public static function update(string $table, array $data, array $where): int|false {
        global $wpdb;

        try {
            // Validate table name
            if (!self::validate_table($table)) {
                self::log_error('update', $table, ['data_keys' => array_keys($data)], 'Invalid table name');
                return false;
            }

            // Validate column names
            $all_columns = array_merge(array_keys($data), array_keys($where));
            if (!self::validate_columns($all_columns)) {
                self::log_error('update', $table, ['columns' => $all_columns], 'Invalid column name detected');
                return false;
            }

            if (empty($data)) {
                self::log_error('update', $table, [], 'No data provided for update');
                return false;
            }

            if (empty($where)) {
                self::log_error('update', $table, [], 'No WHERE clause provided for update - refusing to update all rows');
                return false;
            }

            $table_name = self::get_table_name($table);
            $result = $wpdb->update($table_name, $data, $where);

            if ($result === false) {
                self::log_error('update', $table, [
                    'data_keys' => array_keys($data),
                    'where_keys' => array_keys($where),
                    'wpdb_error' => $wpdb->last_error,
                    'last_query' => $wpdb->last_query,
                ], $wpdb->last_error ?: 'Unknown update error');
                return false;
            }

            return $result;

        } catch (Throwable $e) {
            self::log_error('update', $table, [
                'data_keys' => array_keys($data),
                'where_keys' => array_keys($where),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'Exception during update: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete rows from a table
     *
     * @param string $table The table name (without prefix)
     * @param array $where Column => value pairs for WHERE clause
     * @return int|false Number of rows deleted, or false on error
     */
    public static function delete(string $table, array $where): int|false {
        global $wpdb;

        try {
            // Validate table name
            if (!self::validate_table($table)) {
                self::log_error('delete', $table, ['where_keys' => array_keys($where)], 'Invalid table name');
                return false;
            }

            // Validate column names
            if (!self::validate_columns(array_keys($where))) {
                self::log_error('delete', $table, ['where_keys' => array_keys($where)], 'Invalid column name detected');
                return false;
            }

            if (empty($where)) {
                self::log_error('delete', $table, [], 'No WHERE clause provided for delete - refusing to delete all rows');
                return false;
            }

            $table_name = self::get_table_name($table);
            $result = $wpdb->delete($table_name, $where);

            if ($result === false) {
                self::log_error('delete', $table, [
                    'where_keys' => array_keys($where),
                    'wpdb_error' => $wpdb->last_error,
                    'last_query' => $wpdb->last_query,
                ], $wpdb->last_error ?: 'Unknown delete error');
                return false;
            }

            return $result;

        } catch (Throwable $e) {
            self::log_error('delete', $table, [
                'where_keys' => array_keys($where),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'Exception during delete: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single row from a table
     *
     * @param string $table The table name (without prefix)
     * @param array $where Column => value pairs for WHERE clause
     * @return object|null Row object on success, null on failure or not found
     */
    public static function get_row(string $table, array $where): ?object {
        global $wpdb;

        try {
            // Validate table name
            if (!self::validate_table($table)) {
                self::log_error('get_row', $table, ['where_keys' => array_keys($where)], 'Invalid table name');
                return null;
            }

            // Validate column names
            if (!self::validate_columns(array_keys($where))) {
                self::log_error('get_row', $table, ['where_keys' => array_keys($where)], 'Invalid column name detected');
                return null;
            }

            if (empty($where)) {
                self::log_error('get_row', $table, [], 'No WHERE clause provided for get_row');
                return null;
            }

            $table_name = self::get_table_name($table);

            $conditions = [];
            $values = [];

            foreach ($where as $column => $value) {
                $conditions[] = "`$column` = %s";
                $values[] = $value;
            }

            $sql = $wpdb->prepare(
                "SELECT * FROM `$table_name` WHERE " . implode(' AND ', $conditions) . " LIMIT 1",
                ...$values
            );

            $result = $wpdb->get_row($sql);

            if ($wpdb->last_error) {
                self::log_error('get_row', $table, [
                    'where_keys' => array_keys($where),
                    'wpdb_error' => $wpdb->last_error,
                ], $wpdb->last_error);
            }

            return $result;

        } catch (Throwable $e) {
            self::log_error('get_row', $table, [
                'where_keys' => array_keys($where),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'Exception during get_row: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get multiple rows from a table
     *
     * @param string $table The table name (without prefix)
     * @param array $where Column => value pairs for WHERE clause
     * @param string $order_by Column to order by
     * @param string $order Order direction (ASC or DESC)
     * @param int $limit Maximum number of rows (0 for no limit)
     * @param int $offset Offset for pagination
     * @return array Array of row objects
     */
    public static function get_results(
        string $table,
        array $where = [],
        string $order_by = 'id',
        string $order = 'DESC',
        int $limit = 0,
        int $offset = 0
    ): array {
        global $wpdb;

        try {
            // Validate table name
            if (!self::validate_table($table)) {
                self::log_error('get_results', $table, ['where_keys' => array_keys($where)], 'Invalid table name');
                return [];
            }

            // Validate column names (including order_by)
            $columns_to_validate = array_keys($where);
            $columns_to_validate[] = $order_by;
            if (!self::validate_columns($columns_to_validate)) {
                self::log_error('get_results', $table, [
                    'columns' => $columns_to_validate,
                ], 'Invalid column name detected');
                return [];
            }

            $table_name = self::get_table_name($table);

            $sql = "SELECT * FROM `$table_name`";
            $values = [];

            if (!empty($where)) {
                $conditions = [];
                foreach ($where as $column => $value) {
                    if (is_array($value)) {
                        if (empty($value)) {
                            continue;
                        }
                        $placeholders = array_fill(0, count($value), '%s');
                        $conditions[] = "`$column` IN (" . implode(', ', $placeholders) . ")";
                        $values = array_merge($values, $value);
                    } else {
                        $conditions[] = "`$column` = %s";
                        $values[] = $value;
                    }
                }
                if (!empty($conditions)) {
                    $sql .= " WHERE " . implode(' AND ', $conditions);
                }
            }

            // Sanitize order direction
            $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
            $sql .= " ORDER BY `$order_by` $order";

            if ($limit > 0) {
                $sql .= " LIMIT %d";
                $values[] = $limit;

                if ($offset > 0) {
                    $sql .= " OFFSET %d";
                    $values[] = $offset;
                }
            }

            if (!empty($values)) {
                $sql = $wpdb->prepare($sql, ...$values);
            }

            $results = $wpdb->get_results($sql);

            if ($wpdb->last_error) {
                self::log_error('get_results', $table, [
                    'where_keys' => array_keys($where),
                    'order_by' => $order_by,
                    'limit' => $limit,
                    'offset' => $offset,
                    'wpdb_error' => $wpdb->last_error,
                ], $wpdb->last_error);
                return [];
            }

            return $results ?: [];

        } catch (Throwable $e) {
            self::log_error('get_results', $table, [
                'where_keys' => array_keys($where),
                'order_by' => $order_by,
                'limit' => $limit,
                'offset' => $offset,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'Exception during get_results: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count rows in a table
     *
     * @param string $table The table name (without prefix)
     * @param array $where Column => value pairs for WHERE clause
     * @return int Row count (0 on error)
     */
    public static function count(string $table, array $where = []): int {
        global $wpdb;

        try {
            // Validate table name
            if (!self::validate_table($table)) {
                self::log_error('count', $table, ['where_keys' => array_keys($where)], 'Invalid table name');
                return 0;
            }

            // Validate column names
            if (!empty($where) && !self::validate_columns(array_keys($where))) {
                self::log_error('count', $table, ['where_keys' => array_keys($where)], 'Invalid column name detected');
                return 0;
            }

            $table_name = self::get_table_name($table);

            $sql = "SELECT COUNT(*) FROM `$table_name`";
            $values = [];

            if (!empty($where)) {
                $conditions = [];
                foreach ($where as $column => $value) {
                    $conditions[] = "`$column` = %s";
                    $values[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }

            if (!empty($values)) {
                $sql = $wpdb->prepare($sql, ...$values);
            }

            $result = $wpdb->get_var($sql);

            if ($wpdb->last_error) {
                self::log_error('count', $table, [
                    'where_keys' => array_keys($where),
                    'wpdb_error' => $wpdb->last_error,
                ], $wpdb->last_error);
                return 0;
            }

            return (int) $result;

        } catch (Throwable $e) {
            self::log_error('count', $table, [
                'where_keys' => array_keys($where),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'Exception during count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Execute a raw SQL query
     *
     * @param string $sql The SQL query (use %s, %d placeholders)
     * @param array $values Values to substitute into placeholders
     * @return int|bool Number of rows affected, or false on error
     */
    public static function query(string $sql, array $values = []): int|bool {
        global $wpdb;

        try {
            if (!empty($values)) {
                $sql = $wpdb->prepare($sql, ...$values);
            }

            $result = $wpdb->query($sql);

            if ($result === false) {
                self::log_error('query', 'raw', [
                    'query_preview' => substr($sql, 0, 200),
                    'wpdb_error' => $wpdb->last_error,
                ], $wpdb->last_error ?: 'Unknown query error');
            }

            return $result;

        } catch (Throwable $e) {
            self::log_error('query', 'raw', [
                'query_preview' => substr($sql, 0, 200),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'Exception during query: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single variable from a query
     *
     * @param string $sql The SQL query (use %s, %d placeholders)
     * @param array $values Values to substitute into placeholders
     * @return mixed The value, or null on error/not found
     */
    public static function get_var(string $sql, array $values = []): mixed {
        global $wpdb;

        try {
            if (!empty($values)) {
                $sql = $wpdb->prepare($sql, ...$values);
            }

            $result = $wpdb->get_var($sql);

            if ($wpdb->last_error) {
                self::log_error('get_var', 'raw', [
                    'query_preview' => substr($sql, 0, 200),
                    'wpdb_error' => $wpdb->last_error,
                ], $wpdb->last_error);
            }

            return $result;

        } catch (Throwable $e) {
            self::log_error('get_var', 'raw', [
                'query_preview' => substr($sql, 0, 200),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'Exception during get_var: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Begin a database transaction
     *
     * @return bool Success
     */
    public static function begin_transaction(): bool {
        global $wpdb;

        try {
            $result = $wpdb->query('START TRANSACTION');
            return $result !== false;
        } catch (Throwable $e) {
            self::log_error('begin_transaction', 'transaction', [
                'exception' => $e->getMessage(),
            ], 'Failed to begin transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Commit a database transaction
     *
     * @return bool Success
     */
    public static function commit(): bool {
        global $wpdb;

        try {
            $result = $wpdb->query('COMMIT');
            return $result !== false;
        } catch (Throwable $e) {
            self::log_error('commit', 'transaction', [
                'exception' => $e->getMessage(),
            ], 'Failed to commit transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Rollback a database transaction
     *
     * @return bool Success
     */
    public static function rollback(): bool {
        global $wpdb;

        try {
            $result = $wpdb->query('ROLLBACK');
            return $result !== false;
        } catch (Throwable $e) {
            self::log_error('rollback', 'transaction', [
                'exception' => $e->getMessage(),
            ], 'Failed to rollback transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a table exists
     *
     * @param string $table The table name (without prefix)
     * @return bool Whether the table exists
     */
    public static function table_exists(string $table): bool {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $result = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table_name
                )
            );
            return $result === $table_name;
        } catch (Throwable $e) {
            self::log_error('table_exists', $table, [
                'exception' => $e->getMessage(),
            ], 'Failed to check table existence: ' . $e->getMessage());
            return false;
        }
    }
}
