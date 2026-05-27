<?php
/**
 * Plugin Name: TGS Doc Tracker - Truy vết chứng từ
 * Plugin URI: https://bizgpt.vn/
 * Description: Quản lý chứng từ nhập/xuất (Excel, ảnh), theo dõi lệch số lượng/SKU giữa chứng từ và thực tế nhập phiếu. Hook vào TGS Shop Management.
 * Version: 1.0.0
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
define('TGS_DOC_TRACKER_VERSION', '1.0.0');

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
        $routes['doc-tracker-dashboard']   = TGS_DOC_TRACKER_DIR . 'admin-views/pages/dashboard.php';
        $routes['doc-tracker-discrepancy'] = TGS_DOC_TRACKER_DIR . 'admin-views/pages/discrepancy-report.php';
        $routes['doc-tracker-inventory']   = TGS_DOC_TRACKER_DIR . 'admin-views/pages/inventory-by-source.php';
        return $routes;
    }

    // ── Nav menu ─────────────────────────────────────────────────────────
    public function render_nav_menu($current_view)
    {
        $base = 'admin.php?page=tgs-shop-management&view=';
        $views = ['doc-tracker-dashboard', 'doc-tracker-discrepancy', 'doc-tracker-inventory'];
        $active = in_array($current_view, $views) ? 'active' : '';
        ?>
        <li class="nav-item dropdown <?php echo $active; ?>">
            <a class="nav-link dropdown-toggle <?php echo $active; ?>" href="#" data-bs-toggle="dropdown">
                <i class="bx bx-file-find me-1"></i>Chứng từ
            </a>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item <?php echo $current_view === 'doc-tracker-dashboard' ? 'active' : ''; ?>"
                       href="<?php echo admin_url($base . 'doc-tracker-dashboard'); ?>">
                        <i class="bx bx-home-alt me-1"></i>Tổng quan chứng từ
                    </a>
                </li>
                <li>
                    <a class="dropdown-item <?php echo $current_view === 'doc-tracker-discrepancy' ? 'active' : ''; ?>"
                       href="<?php echo admin_url($base . 'doc-tracker-discrepancy'); ?>">
                        <i class="bx bx-error-alt me-1"></i>Báo cáo lệch chứng từ
                    </a>
                </li>
                <li>
                    <a class="dropdown-item <?php echo $current_view === 'doc-tracker-inventory' ? 'active' : ''; ?>"
                       href="<?php echo admin_url($base . 'doc-tracker-inventory'); ?>">
                        <i class="bx bx-stats me-1"></i>Tồn kho theo nguồn
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
        wp_enqueue_style(
            'tgs-doc-tracker',
            TGS_DOC_TRACKER_URL . 'assets/css/doc-tracker.css',
            [],
            TGS_DOC_TRACKER_VERSION
        );
        wp_enqueue_script(
            'tgs-doc-tracker',
            TGS_DOC_TRACKER_URL . 'assets/js/doc-tracker-ticket.js',
            ['jquery'],
            TGS_DOC_TRACKER_VERSION,
            true
        );
        wp_localize_script('tgs-doc-tracker', 'tgsDocTracker', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('tgs_doc_tracker_nonce'),
            'blogId'     => get_current_blog_id(),
            'ticketType' => $ticket_type,
        ]);
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
    }

    // ── Khi tạo phiếu thành công → commit files tạm ──────────────────────
    public function on_ticket_created($ledger_id, $ticket_type, $extra_data = [])
    {
        TGS_Doc_Tracker_Upload::commit_temp_files($ledger_id, $ticket_type);
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
