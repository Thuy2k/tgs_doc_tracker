<?php
/**
 * Page: Tổng quan chứng từ (Doc Tracker Dashboard)
 *
 * @package tgs_doc_tracker
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$blog_id        = get_current_blog_id();
$table_disc     = TGS_Shop_Database::table('local_doc_tracker_discrepancy');
$table_ledger   = defined('TGS_TABLE_LOCAL_LEDGER') ? TGS_TABLE_LOCAL_LEDGER : $wpdb->prefix . 'local_ledger';

// Thống kê nhanh
$total_disc   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table_disc}` WHERE blog_id=%d AND is_deleted=0", $blog_id));
$pending_disc = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table_disc}` WHERE blog_id=%d AND is_deleted=0 AND resolution_status='pending'", $blog_id));
$resolved_disc = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table_disc}` WHERE blog_id=%d AND is_deleted=0 AND resolution_status='resolved'", $blog_id));
$ticket_with_source = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM `{$table_ledger}` WHERE is_deleted=0 AND local_ledger_software_source IS NOT NULL",
    $blog_id
));

$base_url = 'admin.php?page=tgs-shop-management&view=';
?>

<div class="container-fluid py-3">
    <h4 class="mb-3"><i class="bx bx-file-find me-2"></i>Tổng quan Chứng từ</h4>

    <!-- Cards thống kê nhanh -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-primary"><?php echo number_format($total_disc); ?></div>
                    <div class="text-muted small mt-1">Tổng lệch chứng từ</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-danger"><?php echo number_format($pending_disc); ?></div>
                    <div class="text-muted small mt-1">Chờ xử lý</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-success"><?php echo number_format($resolved_disc); ?></div>
                    <div class="text-muted small mt-1">Đã xử lý</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-info"><?php echo number_format($ticket_with_source); ?></div>
                    <div class="text-muted small mt-1">Phiếu có gắn nguồn</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nút nhanh -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bx bx-error-alt bx-lg text-danger d-block mb-2"></i>
                    <h6>Báo cáo lệch chứng từ</h6>
                    <p class="text-muted small">Xem danh sách phiếu có lệch SKU, số lượng, giá</p>
                    <a href="<?php echo admin_url($base_url . 'doc-tracker-discrepancy'); ?>"
                       class="btn btn-sm btn-danger">Xem báo cáo</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bx bx-stats bx-lg text-info d-block mb-2"></i>
                    <h6>Tồn kho theo nguồn</h6>
                    <p class="text-muted small">Thống kê số lượng nhập/xuất từng SKU theo nguồn phần mềm</p>
                    <a href="<?php echo admin_url($base_url . 'doc-tracker-inventory'); ?>"
                       class="btn btn-sm btn-info">Xem thống kê</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bx bx-wrench bx-lg text-warning d-block mb-2"></i>
                    <h6>Chạy Migrations</h6>
                    <p class="text-muted small">Kích hoạt thủ công để tạo cột mới vào database</p>
                    <button type="button" class="btn btn-sm btn-warning" id="btnRunMigrationsManual">
                        <i class="bx bx-play me-1"></i>Chạy Migration
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lệch gần đây -->
    <?php
    $recent = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table_disc}` WHERE blog_id=%d AND is_deleted=0 AND resolution_status='pending'
         ORDER BY created_at DESC LIMIT 10",
        $blog_id
    ));
    ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bx bx-list-ul me-1"></i>Lệch chưa xử lý gần đây</h6>
            <a href="<?php echo admin_url($base_url . 'doc-tracker-discrepancy'); ?>"
               class="btn btn-sm btn-outline-danger">Xem tất cả</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recent)): ?>
                <div class="text-center py-4 text-muted"><i class="bx bx-check-circle bx-lg text-success d-block mb-1"></i>Không có lệch nào chưa xử lý!</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã phiếu</th>
                                <th>SKU</th>
                                <th>Loại lệch</th>
                                <th>SL chứng từ</th>
                                <th>SL thực tế</th>
                                <th>Lệch</th>
                                <th>Ngày</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td><code><?php echo esc_html($r->local_ledger_code); ?></code></td>
                                    <td><strong><?php echo esc_html($r->local_product_sku); ?></strong></td>
                                    <td>
                                        <?php
                                        $type_labels = [
                                            'qty'     => '<span class="badge bg-warning">Lệch SL</span>',
                                            'sku'     => '<span class="badge bg-danger">SKU thiếu</span>',
                                            'price'   => '<span class="badge bg-info">Lệch giá</span>',
                                            'extra'   => '<span class="badge bg-secondary">Thừa dòng</span>',
                                            'missing' => '<span class="badge bg-dark">Thiếu dòng</span>',
                                        ];
                                        echo $type_labels[$r->discrepancy_type] ?? esc_html($r->discrepancy_type);
                                        ?>
                                    </td>
                                    <td><?php echo is_null($r->doc_quantity) ? '—' : number_format($r->doc_quantity, 1); ?></td>
                                    <td><?php echo is_null($r->actual_quantity) ? '—' : number_format($r->actual_quantity, 1); ?></td>
                                    <td class="<?php echo ($r->quantity_diff < 0) ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo is_null($r->quantity_diff) ? '—' : (($r->quantity_diff > 0 ? '+' : '') . number_format($r->quantity_diff, 1)); ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($r->created_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('btnRunMigrationsManual')?.addEventListener('click', function () {
    if (!confirm('Chạy migration sẽ thêm các cột mới vào database. Tiếp tục?')) return;
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang chạy...';
    const btn = this;
    jQuery.post(tgsDocTracker?.ajaxUrl || ajaxurl, {
        action: 'tgs_doc_tracker_run_migrations',
        nonce: tgsDocTracker?.nonce || ''
    }, function (res) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bx bx-play me-1"></i>Chạy Migration';
        if (res.success) {
            alert('✅ ' + (res.data?.message || 'Migrations đã chạy thành công.'));
        } else {
            alert('❌ ' + (res.data?.message || 'Có lỗi xảy ra.'));
        }
    });
});
</script>
