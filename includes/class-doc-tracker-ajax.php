<?php
/**
 * TGS Doc Tracker - AJAX Handlers
 *
 * @package tgs_doc_tracker
 */

if (!defined('ABSPATH')) {
    exit;
}

class TGS_Doc_Tracker_Ajax
{
    // ── Helper: kiểm tra nonce + quyền ──────────────────────────────────
    private static function check()
    {
        if (!check_ajax_referer('tgs_doc_tracker_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce không hợp lệ.'], 403);
        }
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Không có quyền.'], 403);
        }
    }

    // ── Upload file tạm ──────────────────────────────────────────────────
    public static function upload_temp()
    {
        self::check();

        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'Không tìm thấy file.']);
        }

        $ticket_type = sanitize_text_field($_POST['ticket_type'] ?? 'unknown');
        $source_type = sanitize_text_field($_POST['source_type'] ?? 'manual');
        $user_id     = get_current_user_id();
        $session_key = $user_id . '_' . $ticket_type;

        $result = TGS_Doc_Tracker_Upload::upload_temp($_FILES['file'], $session_key, $source_type);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    // ── Xóa file tạm ────────────────────────────────────────────────────
    public static function delete_temp()
    {
        self::check();

        $session_id = intval($_POST['session_id'] ?? 0);
        if (!$session_id) {
            wp_send_json_error(['message' => 'session_id không hợp lệ.']);
        }

        $result = TGS_Doc_Tracker_Upload::delete_temp($session_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['message' => 'Đã xóa file.']);
    }

    // ── Lấy danh sách file tạm ──────────────────────────────────────────
    public static function list_temp()
    {
        self::check();

        $ticket_type = sanitize_text_field($_POST['ticket_type'] ?? '');
        $user_id     = get_current_user_id();
        $session_key = $user_id . '_' . $ticket_type;

        $files = TGS_Doc_Tracker_Upload::list_temp($session_key);
        wp_send_json_success(['files' => $files]);
    }

    // ── Danh sách phiếu lệch (discrepancy report) ────────────────────────
    public static function discrepancy_list()
    {
        self::check();

        global $wpdb;

        $blog_id    = get_current_blog_id();
        $status     = sanitize_text_field($_POST['status'] ?? '');
        $type       = sanitize_text_field($_POST['type'] ?? '');
        $sku        = sanitize_text_field($_POST['sku'] ?? '');
        $date_from  = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to    = sanitize_text_field($_POST['date_to'] ?? '');
        $draw       = intval($_POST['draw'] ?? 1);
        $start      = intval($_POST['start'] ?? 0);
        $length     = max(1, min(200, intval($_POST['length'] ?? 20)));

        $table = TGS_Shop_Database::table('local_doc_tracker_discrepancy');

        $where  = ['blog_id = %d', 'is_deleted = 0'];
        $params = [$blog_id];

        if ($status) {
            $where[]  = 'resolution_status = %s';
            $params[] = $status;
        }
        if ($type) {
            $where[]  = 'discrepancy_type = %s';
            $params[] = $type;
        }
        if ($sku) {
            $where[]  = 'local_product_sku LIKE %s';
            $params[] = '%' . $wpdb->esc_like($sku) . '%';
        }
        if ($date_from) {
            $where[]  = 'created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        if ($date_to) {
            $where[]  = 'created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` {$where_sql}",
            ...$params
        ));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` {$where_sql}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            ...array_merge($params, [$length, $start])
        ));

        wp_send_json_success([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $rows,
        ]);
    }

    // ── Cập nhật trạng thái lệch ─────────────────────────────────────────
    public static function update_discrepancy()
    {
        self::check();

        if (!current_user_can('manage_options') && !current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => 'Không có quyền cập nhật.'], 403);
        }

        global $wpdb;

        $blog_id          = get_current_blog_id();
        $discrepancy_id   = intval($_POST['discrepancy_id'] ?? 0);
        $resolution_status = sanitize_text_field($_POST['resolution_status'] ?? '');
        $resolution_note  = sanitize_textarea_field($_POST['resolution_note'] ?? '');
        $resolution_method = sanitize_text_field($_POST['resolution_method'] ?? '');

        $allowed_statuses = ['pending', 'in_progress', 'resolved', 'ignored'];
        if (!in_array($resolution_status, $allowed_statuses)) {
            wp_send_json_error(['message' => 'Trạng thái không hợp lệ.']);
        }

        $table = TGS_Shop_Database::table('local_doc_tracker_discrepancy');

        $data = [
            'resolution_status' => $resolution_status,
            'resolution_note'   => $resolution_note,
            'resolution_method' => $resolution_method,
            'updated_at'        => current_time('mysql'),
        ];

        if ($resolution_status === 'resolved') {
            $data['resolved_by'] = get_current_user_id();
            $data['resolved_at'] = current_time('mysql');
        }

        $updated = $wpdb->update(
            $table,
            $data,
            ['discrepancy_id' => $discrepancy_id, 'blog_id' => $blog_id]
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'Không thể cập nhật.']);
        }
        wp_send_json_success(['message' => 'Đã cập nhật trạng thái lệch.']);
    }

    // ── Cập nhật hàng loạt nhiều bản ghi lệch ────────────────────────────
    public static function bulk_update_discrepancy()
    {
        self::check();

        global $wpdb;

        $blog_id           = get_current_blog_id();
        $resolution_status = sanitize_text_field($_POST['resolution_status'] ?? '');
        $resolution_note   = sanitize_textarea_field($_POST['resolution_note'] ?? '');
        $resolution_method = sanitize_text_field($_POST['resolution_method'] ?? '');

        $allowed_statuses = ['pending', 'in_progress', 'resolved', 'ignored'];
        if (!in_array($resolution_status, $allowed_statuses)) {
            wp_send_json_error(['message' => 'Trạng thái không hợp lệ.']);
        }

        $raw_ids = $_POST['discrepancy_ids'] ?? [];
        if (!is_array($raw_ids) || empty($raw_ids)) {
            wp_send_json_error(['message' => 'Không có bản ghi nào được chọn.']);
        }
        $ids = array_values(array_filter(array_map('intval', $raw_ids)));
        if (empty($ids)) {
            wp_send_json_error(['message' => 'ID không hợp lệ.']);
        }

        $table        = TGS_Shop_Database::table('local_doc_tracker_discrepancy');
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $now          = current_time('mysql');

        $extras = '';
        $data   = [$resolution_status, $resolution_note, $resolution_method, $now];
        if ($resolution_status === 'resolved') {
            $extras  = ', resolved_by = %d, resolved_at = %s';
            $data[]  = get_current_user_id();
            $data[]  = $now;
        }
        $data[] = $blog_id;
        $data   = array_merge($data, $ids);

        $sql = $wpdb->prepare(
            "UPDATE `{$table}`
             SET resolution_status = %s, resolution_note = %s, resolution_method = %s, updated_at = %s{$extras}
             WHERE blog_id = %d AND discrepancy_id IN ({$placeholders})",
            ...$data
        );

        $updated = $wpdb->query($sql);

        if ($updated === false) {
            wp_send_json_error(['message' => 'Không thể cập nhật.']);
        }

        wp_send_json_success([
            'message' => 'Cập nhật thành công ' . $updated . ' bản ghi.',
            'updated' => $updated,
        ]);
    }

    // ── Thống kê tồn theo nguồn phần mềm ────────────────────────────────
    public static function inventory_by_source()
    {
        self::check();

        global $wpdb;

        $blog_id    = get_current_blog_id();
        $sku_filter = sanitize_text_field($_POST['sku'] ?? '');
        $date_from  = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to    = sanitize_text_field($_POST['date_to'] ?? '');
        $source     = sanitize_text_field($_POST['source'] ?? '');

        // Bảng cần query
        $t_item   = defined('TGS_TABLE_LOCAL_LEDGER_ITEM') ? TGS_TABLE_LOCAL_LEDGER_ITEM : $wpdb->prefix . 'local_ledger_item';
        $t_ledger = defined('TGS_TABLE_LOCAL_LEDGER')      ? TGS_TABLE_LOCAL_LEDGER      : $wpdb->prefix . 'local_ledger';

        // Xây dựng điều kiện
        $where  = ['i.is_deleted = 0', 'l.is_deleted = 0', 'l.local_ledger_status != 0'];
        $params = [];

        if ($sku_filter) {
            $where[]  = 'i.local_product_sku LIKE %s';
            $params[] = '%' . $wpdb->esc_like($sku_filter) . '%';
        }
        if ($date_from) {
            $where[]  = 'l.created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        if ($date_to) {
            $where[]  = 'l.created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }
        if ($source === 'root') {
            $where[] = "(l.local_ledger_software_source IS NULL OR JSON_CONTAINS(l.local_ledger_software_source, '\"root\"'))";
        } elseif ($source === 'htsoft') {
            $where[] = "JSON_CONTAINS(l.local_ledger_software_source, '\"htsoft\"')";
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                i.local_product_sku,
                MAX(i.local_product_name_id) AS local_product_name_id,
                l.local_ledger_type,
                l.local_ledger_software_source,
                SUM(CASE WHEN l.local_ledger_type IN (1,5,7,9)  THEN COALESCE(i.local_ledger_item_doc_quantity, i.quantity) ELSE 0 END) AS total_doc_import,
                SUM(CASE WHEN l.local_ledger_type IN (2,6,8,10) THEN COALESCE(i.local_ledger_item_doc_quantity, i.quantity) ELSE 0 END) AS total_doc_export,
                SUM(CASE WHEN l.local_ledger_type IN (1,5,7,9)  THEN i.quantity ELSE 0 END) AS total_actual_import,
                SUM(CASE WHEN l.local_ledger_type IN (2,6,8,10) THEN i.quantity ELSE 0 END) AS total_actual_export,
                COUNT(DISTINCT l.local_ledger_id) AS ticket_count
            FROM `{$t_item}` i
            INNER JOIN `{$t_ledger}` l ON i.local_ledger_id = l.local_ledger_id
            {$where_sql}
            GROUP BY i.local_product_sku, l.local_ledger_type, l.local_ledger_software_source
            ORDER BY i.local_product_sku
            LIMIT 500
        ";

        if ($params) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            $rows = $wpdb->get_results($sql);
        }

        wp_send_json_success(['rows' => $rows]);
    }

    // ── Kích hoạt migration thủ công (chỉ admin) ────────────────────────
    public static function run_migrations_manual()
    {
        if (!check_ajax_referer('tgs_doc_tracker_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce không hợp lệ.'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Chỉ admin mới có quyền.'], 403);
        }

        // Kích hoạt migration của tgs_shop_management (bao gồm cả doc_tracker tables)
        if (class_exists('TGS_Shop_Database')) {
            TGS_Shop_Database::activate();
        }

        wp_send_json_success(['message' => 'Đã chạy migrations thành công.']);
    }
}
