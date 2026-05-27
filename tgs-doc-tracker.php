<?php
/**
 * Plugin Name: TGS Doc Tracker - Truy vết chứng từ
 * Plugin URI: https://bizgpt.vn/
 * Description: Quản lý chứng từ nhập/xuất (Excel, ảnh), theo dõi lệch số lượng/SKU giữa chứng từ và thực tế nhập phiếu. Hook vào TGS Shop Management.
 * Version: 1.0.2
 * Author: BIZGPT_AI
 * Author URI: https://bizgpt.vn/
 * License: GPL v2 or later
 * Text Domain: tgs-doc-tracker
 * Requires Plugins: tgs_shop_management
 */

if (!defined('ABSPATH')) {
    exit;
}

// ── Constants ──────────────────────────────────────────────────────────────
define('TGS_DOC_TRACKER_DIR',     plugin_dir_path(__FILE__));
define('TGS_DOC_TRACKER_URL',     plugin_dir_url(__FILE__));
define('TGS_DOC_TRACKER_VERSION', '1.0.4');

// Upload folder name (dưới wp-content/uploads/tgs-doc-tracker/{blog_id}/{YYYY/MM/DD}/)
define('TGS_DOC_TRACKER_UPLOAD_SUBDIR', 'tgs-doc-tracker');

/**
 * Main plugin class - Singleton
 */
class TGS_Doc_Tracker
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_init', [$this, 'check_dependency']);
        $this->load_includes();

        // Đăng ký routes trong TGS Shop Management
        add_filter('tgs_shop_dashboard_routes',   [$this, 'register_routes']);
        // Hook menu vào nav (dùng hook tgs_shop_advanced_menu hoặc một custom hook)
        add_action('tgs_shop_advanced_menu',       [$this, 'render_nav_menu'], 10, 1);

        // Inject UI vào trang tạo phiếu
        add_action('tgs_ticket_create_after_modals',  [$this, 'inject_ticket_modals'], 20, 1);
        add_action('tgs_ticket_create_scripts',        [$this, 'inject_ticket_scripts'], 20, 1);

        // AJAX handlers
        $this->register_ajax();

        // Khi lưu phiếu thành công → commit file tạm → lưu đường dẫn vào advance_meta
        add_action('tgs_ticket_created',           [$this, 'on_ticket_created'], 10, 3);
    }

    // ── Dependency check ─────────────────────────────────────────────────
    public function check_dependency()
    {
        if (!defined('TGS_SHOP_PLUGIN_DIR')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>TGS Doc Tracker</strong> yêu cầu plugin <strong>TGS Shop Management</strong> được kích hoạt.';
                echo '</p></div>';
            });
        }
    }

    // ── Load includes ────────────────────────────────────────────────────
    private function load_includes()
    {

        require_once TGS_DOC_TRACKER_DIR . 'includes/class-doc-tracker-upload.php';
        require_once TGS_DOC_TRACKER_DIR . 'includes/class-doc-tracker-ajax.php';
    }

    // ── Routes ───────────────────────────────────────────────────────────
    public function register_routes($routes)
    {
        $routes['doc-tracker-dashboard']   = ['Tổng quan Chứng từ',      TGS_DOC_TRACKER_DIR . 'admin-views/pages/dashboard.php'];
        $routes['doc-tracker-discrepancy'] = ['Báo cáo lệch Chứng từ',   TGS_DOC_TRACKER_DIR . 'admin-views/pages/discrepancy-report.php'];
        $routes['doc-tracker-inventory']   = ['Tồn kho theo nguồn',       TGS_DOC_TRACKER_DIR . 'admin-views/pages/inventory-by-source.php'];
        return $routes;
    }

    // ── Nav menu ─────────────────────────────────────────────────────────
    public function render_nav_menu($current_view)
    {
        $views = ['doc-tracker-dashboard', 'doc-tracker-discrepancy', 'doc-tracker-inventory'];
        $is_active = in_array($current_view, $views);
        $open = $is_active ? ' active open' : '';
        $url = function ($v) {
            return admin_url('admin.php?page=tgs-shop-management&view=' . $v);
        };
        ?>
        <li class="menu-item<?php echo $open; ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-file-find"></i>
                <div>Chứng từ</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item<?php echo $current_view === 'doc-tracker-dashboard' ? ' active' : ''; ?>">
                    <a href="<?php echo esc_url($url('doc-tracker-dashboard')); ?>" class="menu-link">
                        <div>Tổng quan chứng từ</div>
                    </a>
                </li>
                <li class="menu-item<?php echo $current_view === 'doc-tracker-discrepancy' ? ' active' : ''; ?>">
                    <a href="<?php echo esc_url($url('doc-tracker-discrepancy')); ?>" class="menu-link">
                        <div>Báo cáo lệch chứng từ</div>
                    </a>
                </li>
                <li class="menu-item<?php echo $current_view === 'doc-tracker-inventory' ? ' active' : ''; ?>">
                    <a href="<?php echo esc_url($url('doc-tracker-inventory')); ?>" class="menu-link">
                        <div>Tồn kho theo nguồn</div>
                    </a>
                </li>
            </ul>
        </li>
        <?php
    }

    // ── Inject modal + software source selector vào ticket create ────────
    public function inject_ticket_modals($ticket_type)
    {
        // Các loại phiếu được hỗ trợ
        $supported = ['purchase', 'sale', 'return', 'damage', 'internal_export', 'internal_import'];
        if (!in_array($ticket_type, $supported)) {
            return;
        }
        require_once TGS_DOC_TRACKER_DIR . 'admin-views/components/software-source-selector.php';
        require_once TGS_DOC_TRACKER_DIR . 'admin-views/components/doc-library-modal.php';
    }

    // ── Inject JS/CSS vào ticket create ─────────────────────────────────
    public function inject_ticket_scripts($ticket_type)
    {
        $supported = ['purchase', 'sale', 'return', 'damage', 'internal_export', 'internal_import'];
        if (!in_array($ticket_type, $supported)) {
            return;
        }
        // Dùng <link>/<script> trực tiếp vì main-layout.php không gọi wp_footer()
        // nên wp_enqueue_script/style sẽ không được in ra DOM.
        $css_url = esc_url(TGS_DOC_TRACKER_URL . 'assets/css/doc-tracker.css?v=' . TGS_DOC_TRACKER_VERSION);
        $js_url  = esc_url(TGS_DOC_TRACKER_URL . 'assets/js/doc-tracker-ticket.js?v=' . TGS_DOC_TRACKER_VERSION);
        $config  = [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('tgs_doc_tracker_nonce'),
            'blogId'     => get_current_blog_id(),
            'ticketType' => $ticket_type,
        ];
        echo '<link rel="stylesheet" href="' . $css_url . '">' . "\n";
        echo '<script>var tgsDocTracker = ' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
        echo '<script src="' . $js_url . '"></script>' . "\n";
    }

    // ── AJAX ─────────────────────────────────────────────────────────────
    private function register_ajax()
    {
        // Upload file tạm vào thư viện chứng từ
        add_action('wp_ajax_tgs_doc_tracker_upload_temp',    ['TGS_Doc_Tracker_Ajax', 'upload_temp']);
        // Xóa file tạm khỏi thư viện
        add_action('wp_ajax_tgs_doc_tracker_delete_temp',    ['TGS_Doc_Tracker_Ajax', 'delete_temp']);
        // Lấy danh sách file tạm
        add_action('wp_ajax_tgs_doc_tracker_list_temp',      ['TGS_Doc_Tracker_Ajax', 'list_temp']);
        // Lấy danh sách phiếu lệch (discrepancy report)
        add_action('wp_ajax_tgs_doc_tracker_discrepancy_list', ['TGS_Doc_Tracker_Ajax', 'discrepancy_list']);
        // Cập nhật trạng thái xử lý lệch
        add_action('wp_ajax_tgs_doc_tracker_update_discrepancy',      ['TGS_Doc_Tracker_Ajax', 'update_discrepancy']);
        add_action('wp_ajax_tgs_doc_tracker_bulk_update_discrepancy',  ['TGS_Doc_Tracker_Ajax', 'bulk_update_discrepancy']);
        // Thống kê tồn theo nguồn (htsoft, root, ...)
        add_action('wp_ajax_tgs_doc_tracker_inventory_by_source', ['TGS_Doc_Tracker_Ajax', 'inventory_by_source']);
        // Kích hoạt chạy migrations thủ công (admin only)
        add_action('wp_ajax_tgs_doc_tracker_run_migrations', ['TGS_Doc_Tracker_Ajax', 'run_migrations_manual']);
        // Lấy danh sách file chứng từ của một phiếu
        add_action('wp_ajax_tgs_doc_tracker_get_ledger_files', ['TGS_Doc_Tracker_Ajax', 'get_ledger_files']);
    }

    // ── Khi tạo phiếu thành công → commit files tạm + phát hiện lệch ──────
    public function on_ticket_created($ledger_id, $ticket_type, $extra_data = [])
    {
        try {
            $this->_process_ticket_created($ledger_id, $ticket_type, $extra_data);
        } catch (\Throwable $e) {
            // Log lỗi nhưng KHÔNG throw để không ảnh hưởng luồng tạo phiếu chính
            error_log('[TGS_Doc_Tracker] on_ticket_created lỗi: ' . $e->getMessage() . ' | ledger_id=' . $ledger_id);
        }
    }

    private function _process_ticket_created($ledger_id, $ticket_type, $extra_data = [])
    {
        global $wpdb;

        // 1. Commit file chứng từ tạm thành vĩnh viễn
        TGS_Doc_Tracker_Upload::commit_temp_files($ledger_id, $ticket_type);

        // ── Common context ─────────────────────────────────────────────
        $items = (isset($extra_data['items']) && is_array($extra_data['items'])) ? $extra_data['items'] : [];

        // Với phiếu nhập hàng, các dòng sản phẩm nằm ở phiếu con (auto-import).
        // Truyền child_ledger_id từ hook để truy vấn đúng phiếu.
        $query_ledger_id = intval($extra_data['child_ledger_id'] ?? $ledger_id);

        $blog_id      = get_current_blog_id();
        $ticket_code  = sanitize_text_field($extra_data['ticket_code'] ?? '');
        $user_id      = intval($extra_data['user_id'] ?? get_current_user_id());
        $now          = current_time('mysql');
        $disc_table   = $wpdb->prefix . 'local_doc_tracker_discrepancy';

        // Xây URL xem phiếu theo loại phiếu — có filter để các plugin khác mở rộng
        $type_to_view = apply_filters('tgs_doc_tracker_ticket_detail_view_map', [
            'purchase'               => 'ticket-purchase-detail',
            'sale'                   => 'ticket-sale-detail',
            'return'                 => 'ticket-return-detail',
            'damage'                 => 'ticket-damage-detail',
            'internal_export'        => 'ticket-transfer-export-detail',
            'internal_purchase'      => 'ticket-transfer-import-detail',
            'internal_return'        => 'ticket-internal-return-detail',
            'internal_return_receive'=> 'ticket-internal-return-receive-detail',
        ]);
        $detail_view = $type_to_view[$ticket_type] ?? null;
        $ledger_url  = $detail_view
            ? admin_url('admin.php?page=tgs-shop-management&view=' . $detail_view . '&id=' . $ledger_id)
            : null;

        // Nguồn phần mềm của phiếu (root/htsoft/thu_kho/...) — đính kèm vào meta để báo cáo lọc theo nguồn
        $software_source = $extra_data['software_source'] ?? null;

        // Context dùng chung cho các log function (đóng gói để dễ thêm field sau này)
        $ctx = [
            'ledger_id'        => $ledger_id,
            'query_ledger_id'  => $query_ledger_id,
            'ticket_type'      => $ticket_type,
            'blog_id'          => $blog_id,
            'ticket_code'      => $ticket_code,
            'user_id'          => $user_id,
            'now'              => $now,
            'disc_table'       => $disc_table,
            'ledger_url'       => $ledger_url,
            'software_source'  => $software_source,
        ];

        // 2. Phát hiện lệch số lượng giữa chứng từ và thực tế
        $this->_log_qty_discrepancies($items, $ctx);

        // 3. Phát hiện dòng SKU bị xóa khỏi danh sách (có trong chứng từ nhưng không có trong phiếu)
        $this->_log_missing_doc_rows($extra_data, $ctx);
    }

    /**
     * Phát hiện và ghi log lệch số lượng (doc vs actual) cho các SKU còn trong phiếu.
     *
     * @param array $items Mảng items đã lưu (mỗi item có product_id, doc_quantity).
     * @param array $ctx   Context: ledger_id, query_ledger_id, ticket_type, blog_id, ticket_code,
     *                     user_id, now, disc_table, ledger_url, software_source.
     */
    private function _log_qty_discrepancies($items, $ctx)
    {
        global $wpdb;

        if (empty($items) || !is_array($items)) {
            return;
        }

        // Xây bảng product_id => doc_quantity (chỉ các item có doc_quantity > 0)
        $doc_qty_map = [];
        foreach ($items as $item) {
            $product_id = intval($item['product_id'] ?? 0);
            $doc_qty    = isset($item['doc_quantity']) ? floatval($item['doc_quantity']) : null;
            if ($product_id > 0 && $doc_qty !== null && $doc_qty > 0) {
                // Cộng dồn nếu cùng product_id (batch allocation chia ra nhiều dòng)
                $doc_qty_map[$product_id] = ($doc_qty_map[$product_id] ?? 0) + $doc_qty;
            }
        }

        if (empty($doc_qty_map)) {
            return; // Không có SL chứng từ → bỏ qua
        }

        // Lấy SL thực tế đã lưu vào local_ledger_item, gộp theo product_id
        $item_table    = $wpdb->prefix . 'local_ledger_item';
        $product_table = $wpdb->prefix . 'local_product_name';
        $product_ids   = array_keys($doc_qty_map);

        // Xây IN-clause an toàn (integer IDs, không cần prepare escape)
        $in_ids  = implode(',', array_map('intval', $product_ids));
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT li.local_product_name_id AS product_id,
                    SUM(li.quantity)          AS actual_qty,
                    p.local_product_sku       AS sku,
                    p.local_product_name      AS product_name
             FROM `{$item_table}` li
             LEFT JOIN `{$product_table}` p ON p.local_product_name_id = li.local_product_name_id
             WHERE li.local_ledger_id = %d
               AND li.local_product_name_id IN ({$in_ids})
               AND (li.is_deleted = 0 OR li.is_deleted IS NULL)
             GROUP BY li.local_product_name_id",
            $ctx['query_ledger_id']
        ));
        // phpcs:enable

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $product_id  = intval($row->product_id);
            $actual_qty  = floatval($row->actual_qty);
            $doc_qty     = floatval($doc_qty_map[$product_id]);
            $qty_diff    = $doc_qty - $actual_qty;

            if (abs($qty_diff) < 0.001) {
                continue; // Không lệch → bỏ qua
            }

            $this->_insert_discrepancy($ctx, [
                'sku'              => $row->sku ?? '',
                'product_name'     => $row->product_name ?? '',
                'discrepancy_type' => 'qty',
                'doc_quantity'     => $doc_qty,
                'actual_quantity'  => $actual_qty,
                'quantity_diff'    => $qty_diff,
                'meta_extra'       => [],
            ]);
        }
    }

    /**
     * So sánh snapshot dòng SP đã fill từ chứng từ (gửi từ UI) với items thực tế đã lưu.
     * Nếu có SKU trong snapshot nhưng KHÔNG còn dòng trong phiếu → log discrepancy 'missing'.
     * (Chấp nhận user thêm dòng mới ngoài chứng từ; chỉ cảnh báo khi MẤT DÒNG.)
     *
     * @param array $extra_data Payload hook, có key 'doc_imported_items'.
     * @param array $ctx        Context chung (xem _log_qty_discrepancies).
     */
    /**
     * So sánh snapshot dòng SP đã fill từ chứng từ (gửi từ UI) với items thực tế đã lưu.
     * - SKU có trong snapshot nhưng KHÔNG còn dòng trong phiếu → log 'missing' (Thiếu dòng).
     * - SKU có dòng trong phiếu nhưng KHÔNG có trong snapshot → log 'extra' (Thừa dòng).
     * (Chỉ chạy khi user đã import chứng từ — snapshot không rỗng — để tránh log toàn bộ row
     * khi user tạo phiếu thủ công không qua chứng từ.)
     *
     * @param array $extra_data Payload hook, có key 'doc_imported_items'.
     * @param array $ctx        Context chung (xem _log_qty_discrepancies).
     */
    private function _log_missing_doc_rows($extra_data, $ctx)
    {
        global $wpdb;

        $doc_imported = $extra_data['doc_imported_items'] ?? [];
        if (empty($doc_imported) || !is_array($doc_imported)) {
            return;
        }

        // Gom snapshot theo SKU: { sku => { name, doc_qty, sources[] } }
        $snapshot = [];
        foreach ($doc_imported as $row) {
            if (!is_array($row)) continue;
            $sku = isset($row['sku']) ? trim((string)$row['sku']) : '';
            if ($sku === '') continue;
            $qty    = isset($row['doc_quantity']) ? floatval($row['doc_quantity']) : 0;
            $name   = isset($row['product_name']) ? sanitize_text_field((string)$row['product_name']) : $sku;
            $src    = isset($row['source']) ? sanitize_key((string)$row['source']) : '';
            if (!isset($snapshot[$sku])) {
                $snapshot[$sku] = ['name' => $name, 'doc_qty' => 0, 'sources' => []];
            }
            $snapshot[$sku]['doc_qty'] += $qty;
            if ($src !== '' && !in_array($src, $snapshot[$sku]['sources'], true)) {
                $snapshot[$sku]['sources'][] = $src;
            }
        }
        if (empty($snapshot)) {
            return;
        }

        // Lấy danh sách SKU + tên + SL thực tế đã lưu vào phiếu (gộp nếu SKU lặp ở nhiều dòng/lô)
        $item_table    = $wpdb->prefix . 'local_ledger_item';
        $product_table = $wpdb->prefix . 'local_product_name';
        $rows_actual = $wpdb->get_results($wpdb->prepare(
            "SELECT p.local_product_sku   AS sku,
                    p.local_product_name  AS product_name,
                    SUM(li.quantity)      AS actual_qty
             FROM `{$item_table}` li
             LEFT JOIN `{$product_table}` p ON p.local_product_name_id = li.local_product_name_id
             WHERE li.local_ledger_id = %d
               AND (li.is_deleted = 0 OR li.is_deleted IS NULL)
             GROUP BY p.local_product_sku, p.local_product_name",
            $ctx['query_ledger_id']
        ));
        $present_map = []; // sku => { name, qty }
        foreach ($rows_actual as $r) {
            $s = trim((string)($r->sku ?? ''));
            if ($s === '') continue;
            $present_map[$s] = [
                'name' => (string)($r->product_name ?? ''),
                'qty'  => floatval($r->actual_qty),
            ];
        }

        // 1) SKU trong snapshot mà KHÔNG còn trong phiếu → 'missing' (đã xóa dòng chứng từ)
        foreach ($snapshot as $sku => $info) {
            if (isset($present_map[$sku])) {
                continue;
            }
            $doc_qty = floatval($info['doc_qty']);
            $this->_insert_discrepancy($ctx, [
                'sku'              => $sku,
                'product_name'     => $info['name'],
                'discrepancy_type' => 'missing',
                'doc_quantity'     => $doc_qty,
                'actual_quantity'  => 0,
                'quantity_diff'    => -$doc_qty,
                'meta_extra'       => [
                    'reason'         => 'sku_deleted_from_ticket',
                    'note'           => 'SKU có trong chứng từ đã import nhưng dòng đã bị xóa khỏi phiếu trước khi tạo',
                    'import_sources' => $info['sources'], // excel / ai / image / ...
                ],
            ]);
        }

        // 2) SKU có dòng trong phiếu mà KHÔNG có trong snapshot → 'extra' (user thêm dòng mới)
        foreach ($present_map as $sku => $info) {
            if (isset($snapshot[$sku])) {
                continue;
            }
            $actual_qty = floatval($info['qty']);
            if ($actual_qty <= 0) continue;
            $this->_insert_discrepancy($ctx, [
                'sku'              => $sku,
                'product_name'     => $info['name'] ?: $sku,
                'discrepancy_type' => 'extra',
                'doc_quantity'     => 0,
                'actual_quantity'  => $actual_qty,
                'quantity_diff'    => $actual_qty,
                'meta_extra'       => [
                    'reason' => 'sku_added_outside_doc',
                    'note'   => 'SKU được user thêm mới vào phiếu, không có trong chứng từ đã import',
                ],
            ]);
        }
    }

    /**
     * Helper chung: insert 1 dòng discrepancy với context + payload.
     * Tất cả meta (ticket_type, ledger_url, software_source) được merge sẵn → các hàm log
     * chỉ cần truyền thêm `meta_extra` riêng theo loại lệch.
     *
     * @param array $ctx     Context chung (ledger_id, blog_id, ticket_code, ...).
     * @param array $payload sku, product_name, discrepancy_type, doc_quantity, actual_quantity,
     *                       quantity_diff, meta_extra (array).
     */
    private function _insert_discrepancy($ctx, $payload)
    {
        global $wpdb;

        $meta = array_merge([
            'ticket_type'     => $ctx['ticket_type'],
            'ledger_url'      => $ctx['ledger_url'],
            'software_source' => $ctx['software_source'],
        ], (array)($payload['meta_extra'] ?? []));

        $wpdb->insert($ctx['disc_table'], [
            'blog_id'                 => $ctx['blog_id'],
            'local_ledger_id'         => $ctx['ledger_id'],
            'local_ledger_code'       => $ctx['ticket_code'],
            'local_product_sku'       => $payload['sku'],
            'local_product_name_text' => $payload['product_name'],
            'discrepancy_type'        => $payload['discrepancy_type'],
            'doc_quantity'            => $payload['doc_quantity'],
            'actual_quantity'         => $payload['actual_quantity'],
            'quantity_diff'           => $payload['quantity_diff'],
            'resolution_status'       => 'pending',
            'discrepancy_meta'        => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
            'user_id'                 => $ctx['user_id'],
            'is_deleted'              => 0,
            'created_at'              => $ctx['now'],
            'updated_at'              => $ctx['now'],
        ]);
    }
}

// ── Activation ─────────────────────────────────────────────────────────────
register_activation_hook(__FILE__, function () {
    // Bảng doc_tracker giờ quản lý tập trung trong TGS_Shop_Database
    if (class_exists('TGS_Shop_Database')) {
        TGS_Shop_Database::activate();
    }
});

// ── Boot ────────────────────────────────────────────────────────────────────
add_action('plugins_loaded', function () {
    TGS_Doc_Tracker::instance();
});
