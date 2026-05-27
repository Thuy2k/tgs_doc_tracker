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
        // Lọc theo nguồn: dùng LIKE thay vì JSON_CONTAINS để bắt cả dữ liệu cũ
        // bị double-encode (vd ["[\"htsoft\"]"]) lẫn dữ liệu chuẩn (["htsoft"]).
        // Ưu tiên item-level source (vì phiếu chuyển kho có 2 ledger, source nằm ở ledger cha
        // còn items nằm ở ledger con — item-level source được copy từ cha sang chính xác).
        // Fallback sang ledger-level cho phiếu đơn ledger không copy source xuống item.
        $src_expr = "COALESCE(i.local_ledger_item_software_source, l.local_ledger_software_source)";
        if ($source === 'root') {
            $where[]  = "($src_expr IS NULL OR $src_expr LIKE %s)";
            $params[] = '%root%';
        } elseif ($source === 'htsoft') {
            $where[]  = "$src_expr LIKE %s";
            $params[] = '%htsoft%';
        } elseif ($source === 'thu_kho') {
            $where[]  = "$src_expr LIKE %s";
            $params[] = '%thu_kho%';
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                i.local_product_sku,
                MAX(i.local_product_name_id) AS local_product_name_id,
                l.local_ledger_type,
                {$src_expr} AS local_ledger_software_source,
                SUM(CASE WHEN l.local_ledger_type IN (1,5,7,9)  THEN COALESCE(i.local_ledger_item_doc_quantity, i.quantity) ELSE 0 END) AS total_doc_import,
                SUM(CASE WHEN l.local_ledger_type IN (2,6,8,10) THEN COALESCE(i.local_ledger_item_doc_quantity, i.quantity) ELSE 0 END) AS total_doc_export,
                SUM(CASE WHEN l.local_ledger_type IN (1,5,7,9)  THEN i.quantity ELSE 0 END) AS total_actual_import,
                SUM(CASE WHEN l.local_ledger_type IN (2,6,8,10) THEN i.quantity ELSE 0 END) AS total_actual_export,
                COUNT(DISTINCT l.local_ledger_id) AS ticket_count
            FROM `{$t_item}` i
            INNER JOIN `{$t_ledger}` l ON i.local_ledger_id = l.local_ledger_id
            {$where_sql}
            GROUP BY i.local_product_sku, l.local_ledger_type, {$src_expr}
            ORDER BY i.local_product_sku
            LIMIT 2000
        ";

        if ($params) {
            $raw_rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            $raw_rows = $wpdb->get_results($sql);
        }

        // ── Tách JSON software_source thành từng nguồn riêng biệt ──
        // 1 phiếu lưu ["root","htsoft"] → phải hiển thị 2 dòng (1 root + 1 htsoft)
        // NULL/'[]' → mặc định coi như nguồn "root"
        $buckets = []; // key: sku|type|src
        foreach ($raw_rows as $r) {
            $src_raw = $r->local_ledger_software_source;
            $src_list = [];
            if (empty($src_raw) || $src_raw === 'null') {
                $src_list = ['root'];
            } else {
                // Decode tối đa 3 lần để bắt cả data cũ bị double/triple-encode
                // (vd: '["[\\\"htsoft\\\"]"]' → decode 1 lần ra ['[\"htsoft\"]'] → decode tiếp ra ['htsoft'])
                $decoded = $src_raw;
                for ($i = 0; $i < 3; $i++) {
                    if (is_string($decoded)) {
                        $tmp = json_decode($decoded, true);
                        if ($tmp === null) break;
                        $decoded = $tmp;
                    } elseif (is_array($decoded)) {
                        // Nếu mảng chỉ có 1 phần tử là JSON string → decode tiếp
                        if (count($decoded) === 1 && is_string($decoded[0]) && $decoded[0] !== '' && $decoded[0][0] === '[') {
                            $tmp = json_decode($decoded[0], true);
                            if (is_array($tmp)) { $decoded = $tmp; continue; }
                        }
                        break;
                    } else {
                        break;
                    }
                }
                if (is_array($decoded)) {
                    $src_list = array_values(array_filter(array_map(function ($v) {
                        return is_string($v) ? trim($v) : '';
                    }, $decoded), 'strlen'));
                } elseif (is_string($decoded) && $decoded !== '') {
                    $src_list = [$decoded];
                }
                if (empty($src_list)) {
                    $src_list = ['root'];
                }
            }

            foreach ($src_list as $single_src) {
                // Nếu user lọc 1 nguồn cụ thể → bỏ các nguồn khác trong cùng phiếu
                if ($source !== '' && $source !== $single_src) {
                    continue;
                }
                $key = $r->local_product_sku . '|' . $r->local_ledger_type . '|' . $single_src;
                if (!isset($buckets[$key])) {
                    $buckets[$key] = [
                        'local_product_sku'            => $r->local_product_sku,
                        'local_product_name_id'        => $r->local_product_name_id,
                        'local_ledger_type'            => $r->local_ledger_type,
                        'local_ledger_software_source' => $single_src,
                        'total_doc_import'             => 0,
                        'total_doc_export'             => 0,
                        'total_actual_import'          => 0,
                        'total_actual_export'          => 0,
                        'ticket_count'                 => 0,
                    ];
                }
                $buckets[$key]['total_doc_import']    += (float) $r->total_doc_import;
                $buckets[$key]['total_doc_export']    += (float) $r->total_doc_export;
                $buckets[$key]['total_actual_import'] += (float) $r->total_actual_import;
                $buckets[$key]['total_actual_export'] += (float) $r->total_actual_export;
                $buckets[$key]['ticket_count']        += (int) $r->ticket_count;
            }
        }

        // Sort: theo SKU rồi nguồn rồi loại
        $rows = array_values($buckets);
        usort($rows, function ($a, $b) {
            $c = strcmp($a['local_product_sku'], $b['local_product_sku']);
            if ($c !== 0) return $c;
            $c = strcmp($a['local_ledger_software_source'], $b['local_ledger_software_source']);
            if ($c !== 0) return $c;
            return ((int) $a['local_ledger_type']) - ((int) $b['local_ledger_type']);
        });

        wp_send_json_success(['rows' => $rows]);
    }

    // ── Lấy file chứng từ đã commit của một phiếu ───────────────────────
    public static function get_ledger_files()
    {
        self::check();

        global $wpdb;

        $blog_id   = get_current_blog_id();
        $ledger_id = intval($_POST['ledger_id'] ?? 0);

        if ($ledger_id <= 0) {
            wp_send_json_error(['message' => 'ledger_id không hợp lệ.']);
        }

        $ledger_table = $wpdb->prefix . 'local_ledger';

        $meta_json = $wpdb->get_var($wpdb->prepare(
            "SELECT local_ledger_advance_meta FROM `{$ledger_table}` WHERE local_ledger_id = %d LIMIT 1",
            $ledger_id
        ));

        $meta      = $meta_json ? json_decode($meta_json, true) : [];
        $doc_files = $meta['doc_files'] ?? [];

        wp_send_json_success(['files' => $doc_files]);
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
