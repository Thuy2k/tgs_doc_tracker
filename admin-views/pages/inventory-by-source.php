<?php
/**
 * Page: Tồn kho theo nguồn phần mềm
 *
 * Thống kê nhập/xuất từng SKU theo nguồn phần mềm (root, htsoft, ...).
 * Dựa vào cột local_ledger_software_source và local_ledger_item_doc_quantity.
 *
 * @package tgs_doc_tracker
 */

if (!defined('ABSPATH')) {
    exit;
}

$ajax_url = admin_url('admin-ajax.php');
$nonce    = wp_create_nonce('tgs_doc_tracker_nonce');
?>

<div class="container-fluid py-3">
    <h4 class="mb-3"><i class="bx bx-stats me-2 text-info"></i>Tồn kho theo nguồn phần mềm</h4>

    <div class="alert alert-info py-2 px-3 mb-3">
        <i class="bx bx-info-circle me-1"></i>
        Thống kê dựa trên <strong>Số lượng chứng từ (SL CT)</strong> — số lượng đọc từ file Excel/AI nhận diện.
        Cột <strong>SL thực tế</strong> là số lượng đã nhập vào phiếu (có thể sửa tay).
        Lệch = SL CT − SL thực tế.
    </div>

    <!-- Bộ lọc -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Mã hàng (SKU)</label>
                    <input type="text" class="form-control form-control-sm" id="filterInvSku" placeholder="Tất cả SKU">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Nguồn phần mềm</label>
                    <select class="form-select form-select-sm" id="filterInvSource">
                        <option value="">Tất cả nguồn</option>
                        <option value="root">Hệ thống mình (root)</option>
                        <option value="htsoft">HTSoft</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Từ ngày</label>
                    <input type="date" class="form-control form-control-sm" id="filterInvDateFrom">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Đến ngày</label>
                    <input type="date" class="form-control form-control-sm" id="filterInvDateTo">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-info w-100" id="btnInvSearch">
                        <i class="bx bx-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng kết quả -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="inventoryBySourceTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>SKU</th>
                            <th>Nguồn</th>
                            <th>Loại phiếu</th>
                            <th>SL CT nhập</th>
                            <th>SL CT xuất</th>
                            <th>Tồn CT</th>
                            <th>SL TT nhập</th>
                            <th>SL TT xuất</th>
                            <th>Tồn TT</th>
                            <th>Lệch tồn</th>
                            <th>Số phiếu</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryBySourceBody">
                        <tr><td colspan="12" class="text-center py-4 text-muted">Nhấn tìm kiếm để tải dữ liệu.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function ($) {
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce   = '<?php echo esc_js($nonce); ?>';

    // Mapping loại phiếu → nhãn
    var ledgerTypeLabels = {
        1: 'Nhập hàng', 2: 'Xuất hàng', 3: 'Hoàn NCC', 4: 'Hoàn KH',
        5: 'Mua nội bộ', 6: 'Bán nội bộ', 7: 'Nhập kho', 8: 'Xuất kho',
    };

    function sourceLabel(src) {
        if (!src) return '<span class="badge bg-secondary">root</span>';
        try {
            var arr = JSON.parse(src);
            return arr.map(function(s){
                if (s === 'root')    return '<span class="badge bg-primary">root</span>';
                if (s === 'htsoft')  return '<span class="badge bg-warning text-dark">htsoft</span>';
                if (s === 'thu_kho') return '<span class="badge bg-info text-dark">thủ kho</span>';
                return '<span class="badge bg-secondary">' + s + '</span>';
            }).join(' ');
        } catch(e) { return '<span class="badge bg-secondary">' + src + '</span>'; }
    }

    function loadData() {
        var $tbody = $('#inventoryBySourceBody');
        $tbody.html('<tr><td colspan="12" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Đang tải...</td></tr>');

        $.post(ajaxUrl, {
            action:    'tgs_doc_tracker_inventory_by_source',
            nonce:     nonce,
            sku:       $('#filterInvSku').val(),
            source:    $('#filterInvSource').val(),
            date_from: $('#filterInvDateFrom').val(),
            date_to:   $('#filterInvDateTo').val(),
        }, function (res) {
            if (!res.success || !res.data?.rows?.length) {
                $tbody.html('<tr><td colspan="12" class="text-center py-4 text-muted">Không có dữ liệu.</td></tr>');
                return;
            }
            var html = '';
            var rowNum = 1;
            $.each(res.data.rows, function (i, r) {
                var docImport  = parseFloat(r.total_doc_import  || 0);
                var docExport  = parseFloat(r.total_doc_export  || 0);
                var actImport  = parseFloat(r.total_actual_import || 0);
                var actExport  = parseFloat(r.total_actual_export || 0);
                var docBalance = docImport - docExport;
                var actBalance = actImport - actExport;
                var diff       = docBalance - actBalance;
                var diffClass  = diff < 0 ? 'text-danger fw-bold' : (diff > 0 ? 'text-warning fw-bold' : 'text-success');

                html += '<tr>';
                html += '<td>' + (rowNum++) + '</td>';
                html += '<td><strong>' + (r.local_product_sku || '—') + '</strong></td>';
                html += '<td>' + sourceLabel(r.local_ledger_software_source) + '</td>';
                html += '<td>' + (ledgerTypeLabels[r.local_ledger_type] || 'Loại ' + r.local_ledger_type) + '</td>';
                html += '<td class="text-success">' + docImport.toFixed(1) + '</td>';
                html += '<td class="text-danger">'  + docExport.toFixed(1) + '</td>';
                html += '<td class="fw-bold">'      + docBalance.toFixed(1) + '</td>';
                html += '<td class="text-success">' + actImport.toFixed(1) + '</td>';
                html += '<td class="text-danger">'  + actExport.toFixed(1) + '</td>';
                html += '<td class="fw-bold">'      + actBalance.toFixed(1) + '</td>';
                html += '<td class="' + diffClass + '">' + (diff > 0 ? '+' : '') + diff.toFixed(1) + '</td>';
                html += '<td>' + (r.ticket_count || 0) + '</td>';
                html += '</tr>';
            });
            $tbody.html(html);
        });
    }

    $('#btnInvSearch').on('click', loadData);
})(jQuery);
</script>
