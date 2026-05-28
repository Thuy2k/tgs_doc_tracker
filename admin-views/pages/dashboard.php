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
$ticket_with_source = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM `{$table_ledger}` WHERE is_deleted=0 AND local_ledger_software_source IS NOT NULL"
);

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
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $r): ?>
                                <?php
                                $disc_meta  = $r->discrepancy_meta ? json_decode($r->discrepancy_meta, true) : [];
                                $ledger_url = $disc_meta['ledger_url'] ?? null;
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($ledger_url): ?>
                                            <a href="<?php echo esc_url($ledger_url); ?>" target="_blank">
                                                <code><?php echo esc_html($r->local_ledger_code); ?></code>
                                                <i class="bx bx-link-external" style="font-size:11px;"></i>
                                            </a>
                                        <?php else: ?>
                                            <code><?php echo esc_html($r->local_ledger_code); ?></code>
                                        <?php endif; ?>
                                    </td>
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
                                    <td>
                                        <button type="button"
                                            class="btn btn-xs btn-outline-secondary py-0 px-1 btnDashViewFiles"
                                            data-ledger-id="<?php echo intval($r->local_ledger_id); ?>"
                                            data-ledger-code="<?php echo esc_attr($r->local_ledger_code); ?>"
                                            title="Xem file chứng từ">
                                            <i class="bx bx-file"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal xem file chứng từ (Dashboard) -->
<div class="modal fade" id="dashFilesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-file me-2"></i>File chứng từ — <span id="dashFilesLedgerCode"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="dashFilesBody" style="max-height:70vh;overflow-y:auto;">
                <div class="text-center py-4 text-muted"><i class="bx bx-loader-circle bx-spin me-1"></i> Đang tải...</div>
            </div>
        </div>
    </div>
</div>

<script>
(function ($) {
    var ajaxUrl = (typeof tgsDocTracker !== 'undefined' && tgsDocTracker.ajaxUrl) ? tgsDocTracker.ajaxUrl : ajaxurl;
    var nonce   = (typeof tgsDocTracker !== 'undefined' && tgsDocTracker.nonce)   ? tgsDocTracker.nonce   : '';

    // ── Xem file chứng từ ────────────────────────────────────────────────
    $(document).on('click', '.btnDashViewFiles', function () {
        var ledgerId   = $(this).data('ledger-id');
        var ledgerCode = $(this).data('ledger-code') || ('Phiếu #' + ledgerId);
        $('#dashFilesLedgerCode').text(ledgerCode);
        $('#dashFilesBody').html('<div class="text-center py-4 text-muted"><i class="bx bx-loader-circle bx-spin me-1"></i> Đang tải...</div>');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('dashFilesModal')).show();
        $.post(ajaxUrl, {
            action:    'tgs_doc_tracker_get_ledger_files',
            nonce:     nonce,
            ledger_id: ledgerId,
        }, function (res) {
            if (!res.success) {
                $('#dashFilesBody').html('<div class="alert alert-danger">' + (res.data?.message || 'Không thể tải file.') + '</div>');
                return;
            }
            var files = res.data.files || [];
            if (!files.length) {
                $('#dashFilesBody').html('<div class="text-center py-4 text-muted">Phiếu này chưa có file chứng từ nào.</div>');
                return;
            }
            var html = '<div class="row g-2">';
            $.each(files, function (i, f) {
                var name = f.original_name || f.file_name || ('File ' + (i + 1));
                var url  = f.url || f.file_url || '';
                var ext  = name.split('.').pop().toLowerCase();
                var isImg = ['jpg','jpeg','png','gif','webp','bmp'].indexOf(ext) >= 0;
                // URL stream qua endpoint server → ép Content-Disposition: attachment
                var dlUrl = ajaxUrl
                          + '?action=tgs_doc_tracker_download_file'
                          + '&_wpnonce=' + encodeURIComponent(nonce)
                          + '&ledger_id=' + encodeURIComponent(ledgerId)
                          + '&idx=' + i;
                html += '<div class="col-6 col-md-4">';
                if (isImg && url) {
                    html += '<a href="' + url + '" target="_blank">'
                          + '<img src="' + url + '" class="img-fluid rounded border" style="max-height:160px;object-fit:cover;width:100%;" alt="' + name + '">'
                          + '</a>'
                          + '<a href="' + dlUrl + '" class="btn btn-sm btn-outline-primary w-100 mt-1"><i class="bx bx-download"></i> Tải</a>';
                } else {
                    html += '<a href="' + dlUrl + '" class="d-flex align-items-center gap-2 p-2 border rounded text-decoration-none">'
                          + '<i class="bx bx-download fs-4 text-primary"></i>'
                          + '<span class="text-truncate small">' + name + '</span>'
                          + '</a>';
                }
                html += '<div class="text-muted small mt-1 text-truncate">' + name + '</div></div>';
            });
            html += '</div>';
            $('#dashFilesBody').html(html);
        }).fail(function () {
            $('#dashFilesBody').html('<div class="alert alert-danger">Lỗi kết nối.</div>');
        });
    });
})(jQuery);


</script>
