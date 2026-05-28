<?php
/**
 * Page: Báo cáo lệch chứng từ
 *
 * Hiển thị danh sách lệch giữa chứng từ và thực tế nhập phiếu.
 * Cho phép lọc theo SKU, loại lệch, trạng thái xử lý, ngày.
 * Cho phép cập nhật trạng thái xử lý trực tiếp.
 *
 * @package tgs_doc_tracker
 */

if (!defined('ABSPATH')) {
    exit;
}

$blog_id  = get_current_blog_id();
$nonce    = wp_create_nonce('tgs_doc_tracker_nonce');
$ajax_url = admin_url('admin-ajax.php');
?>

<div class="container-fluid py-3">
    <h4 class="mb-3"><i class="bx bx-error-alt me-2 text-danger"></i>Báo cáo lệch chứng từ</h4>

    <!-- Bộ lọc -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Mã hàng (SKU)</label>
                    <input type="text" class="form-control form-control-sm" id="filterDiscSku" placeholder="Nhập SKU...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Loại lệch</label>
                    <select class="form-select form-select-sm" id="filterDiscType">
                        <option value="">Tất cả</option>
                        <option value="qty">Lệch số lượng</option>
                        <option value="sku">SKU không tìm thấy</option>
                        <option value="price">Lệch đơn giá</option>
                        <option value="extra">Thừa dòng</option>
                        <option value="missing">Thiếu dòng</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Trạng thái</label>
                    <select class="form-select form-select-sm" id="filterDiscStatus">
                        <option value="">Tất cả</option>
                        <option value="pending" selected>Chưa xử lý</option>
                        <option value="in_progress">Đang xử lý</option>
                        <option value="resolved">Đã xử lý</option>
                        <option value="ignored">Bỏ qua</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Từ ngày</label>
                    <input type="date" class="form-control form-control-sm" id="filterDiscDateFrom">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Đến ngày</label>
                    <input type="date" class="form-control form-control-sm" id="filterDiscDateTo">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-primary w-100" id="btnDiscSearch">
                        <i class="bx bx-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng kết quả -->
    <div class="card">
        <!-- Thanh bulk action (hiện khi có chọn checkbox) -->
        <div id="discBulkBar" class="d-none px-3 py-2 border-bottom bg-light d-flex flex-wrap align-items-center gap-2">
            <span class="badge bg-primary" id="discBulkCount">0 đã chọn</span>
            <select class="form-select form-select-sm" id="discBulkStatus" style="width:160px;">
                <option value="">— Chọn trạng thái —</option>
                <option value="pending">Chưa xử lý</option>
                <option value="in_progress">Đang xử lý</option>
                <option value="resolved">Đã xử lý xong</option>
                <option value="ignored">Bỏ qua</option>
            </select>
            <input type="text" class="form-control form-control-sm" id="discBulkMethod"
                   placeholder="Cách xử lý..." style="width:200px;">
            <input type="text" class="form-control form-control-sm" id="discBulkNote"
                   placeholder="Ghi chú / Nguyên nhân..." style="width:220px;">
            <button type="button" class="btn btn-sm btn-success" id="btnDiscBulkSave">
                <i class="bx bx-check me-1"></i>Áp dụng hàng loạt
            </button>
            <button type="button" class="btn btn-sm btn-link text-muted ms-auto" id="btnDiscBulkClear">
                Bỏ chọn tất cả
            </button>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="discrepancyTable">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:36px;">
                                <input type="checkbox" id="discCheckAll" title="Chọn tất cả">
                            </th>
                            <th>#</th>
                            <th>Mã phiếu</th>
                            <th>SKU</th>
                            <th>Tên sản phẩm</th>
                            <th>Loại lệch</th>
                            <th>SL chứng từ</th>
                            <th>SL thực tế</th>
                            <th>Lệch SL</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="discrepancyTableBody">
                        <tr><td colspan="12" class="text-center py-4 text-muted">Nhấn tìm kiếm để tải dữ liệu.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Pagination -->
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small" id="discPaginationInfo">—</span>
            <div class="d-flex gap-2" id="discPaginationBtns"></div>
        </div>
    </div>
</div>

<!-- Modal cập nhật trạng thái lệch (đơn lẻ) -->
<div class="modal fade" id="discUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-edit me-2"></i>Cập nhật xử lý lệch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="discUpdateId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Trạng thái xử lý</label>
                    <select class="form-select" id="discUpdateStatus">
                        <option value="pending">Chưa xử lý</option>
                        <option value="in_progress">Đang xử lý</option>
                        <option value="resolved">Đã xử lý xong</option>
                        <option value="ignored">Bỏ qua</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Cách xử lý / Hướng giải quyết</label>
                    <input type="text" class="form-control" id="discUpdateMethod"
                           placeholder="VD: Nhà cung cấp xác nhận giao thiếu, đã tạo phiếu điều chỉnh...">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Ghi chú / Nguyên nhân</label>
                    <textarea class="form-control" id="discUpdateNote" rows="3"
                              placeholder="VD: Hàng thực tế ít hơn chứng từ do nhà xe giao thiếu 2 thùng..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="btnDiscUpdateSave">
                    <i class="bx bx-save me-1"></i>Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal xem file chứng từ của phiếu -->
<div class="modal fade" id="discFilesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-file me-2"></i>File chứng từ — <span id="discFilesLedgerCode"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="discFilesBody" style="max-height:70vh;overflow-y:auto;">
                <div class="text-center py-4 text-muted"><i class="bx bx-loader-circle bx-spin me-1"></i> Đang tải...</div>
            </div>
        </div>
    </div>
</div>

<script>
(function ($) {
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce   = '<?php echo esc_js($nonce); ?>';
    var currentPage  = 0;
    var pageLength   = 20;
    var totalRecords = 0;

    var statusLabels = {
        pending:     '<span class="badge bg-danger">Chưa xử lý</span>',
        in_progress: '<span class="badge bg-warning text-dark">Đang xử lý</span>',
        resolved:    '<span class="badge bg-success">Đã xử lý</span>',
        ignored:     '<span class="badge bg-secondary">Bỏ qua</span>',
    };
    var typeLabels = {
        qty:     '<span class="badge bg-warning text-dark">Lệch SL</span>',
        sku:     '<span class="badge bg-danger">SKU thiếu</span>',
        price:   '<span class="badge bg-info text-dark">Lệch giá</span>',
        extra:   '<span class="badge bg-secondary">Thừa dòng</span>',
        missing: '<span class="badge bg-dark">Thiếu dòng</span>',
    };

    // ── Tải dữ liệu ──────────────────────────────────────────────────────
    function loadData(page) {
        page = page || 0;
        currentPage = page;
        var $tbody = $('#discrepancyTableBody');
        $tbody.html('<tr><td colspan="12" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Đang tải...</td></tr>');
        clearBulkSelection();

        $.post(ajaxUrl, {
            action:    'tgs_doc_tracker_discrepancy_list',
            nonce:     nonce,
            sku:       $('#filterDiscSku').val(),
            type:      $('#filterDiscType').val(),
            status:    $('#filterDiscStatus').val(),
            date_from: $('#filterDiscDateFrom').val(),
            date_to:   $('#filterDiscDateTo').val(),
            draw:      page + 1,
            start:     page * pageLength,
            length:    pageLength,
        }, function (res) {
            if (!res.success) {
                $tbody.html('<tr><td colspan="12" class="text-danger text-center py-3">' + (res.data?.message || 'Lỗi tải dữ liệu') + '</td></tr>');
                return;
            }
            var data = res.data;
            totalRecords = data.recordsTotal;
            var html = '';
            $.each(data.data, function (i, r) {
                var diff      = parseFloat(r.quantity_diff);
                var diffClass = diff < 0 ? 'text-danger' : (diff > 0 ? 'text-success' : '');
                var diffText  = isNaN(diff) ? '—' : (diff > 0 ? '+' : '') + diff.toFixed(1);
                html += '<tr data-id="' + r.discrepancy_id + '">';
                html += '<td class="text-center"><input type="checkbox" class="disc-row-check" value="' + r.discrepancy_id + '"></td>';
                html += '<td>' + (page * pageLength + i + 1) + '</td>';
                // Mã phiếu: clickable link nếu có URL trong discrepancy_meta
                var meta = {};
                try { meta = JSON.parse(r.discrepancy_meta || '{}'); } catch(e) {}
                var ledgerUrl = meta.ledger_url || null;
                if (ledgerUrl) {
                    html += '<td><a href="' + ledgerUrl + '" target="_blank"><code>' + (r.local_ledger_code || '—') + '</code> <i class="bx bx-link-external" style="font-size:11px;"></i></a></td>';
                } else {
                    html += '<td><code>' + (r.local_ledger_code || '—') + '</code></td>';
                }
                html += '<td><strong>' + (r.local_product_sku || '—') + '</strong></td>';
                html += '<td class="text-truncate" style="max-width:180px;">' + (r.local_product_name_text || '—') + '</td>';
                html += '<td>' + (typeLabels[r.discrepancy_type] || r.discrepancy_type) + '</td>';
                html += '<td>' + (r.doc_quantity    !== null ? parseFloat(r.doc_quantity).toFixed(1)    : '—') + '</td>';
                html += '<td>' + (r.actual_quantity !== null ? parseFloat(r.actual_quantity).toFixed(1) : '—') + '</td>';
                html += '<td class="' + diffClass + '">' + diffText + '</td>';
                html += '<td>' + (statusLabels[r.resolution_status] || r.resolution_status) + '</td>';
                html += '<td>' + (r.created_at ? r.created_at.substr(0,10) : '—') + '</td>';
                html += '<td>'
                    + '<button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 btnDiscEdit me-1"'
                    + ' data-id="' + r.discrepancy_id + '"'
                    + ' data-status="' + r.resolution_status + '"'
                    + ' data-note="' + encodeURIComponent(r.resolution_note || '') + '"'
                    + ' data-method="' + encodeURIComponent(r.resolution_method || '') + '">'
                    + '<i class="bx bx-edit-alt"></i></button>'
                    + '<button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 btnViewFiles"'
                    + ' data-ledger-id="' + r.local_ledger_id + '"'
                    + ' data-ledger-code="' + (r.local_ledger_code || '') + '"'
                    + ' title="Xem file chứng từ">'
                    + '<i class="bx bx-file"></i></button>'
                    + '</td>';
                html += '</tr>';
            });
            if (!html) {
                html = '<tr><td colspan="12" class="text-center py-4 text-muted">Không có dữ liệu.</td></tr>';
            }
            $tbody.html(html);

            // Pagination info
            var from = totalRecords ? page * pageLength + 1 : 0;
            var to   = Math.min((page + 1) * pageLength, totalRecords);
            $('#discPaginationInfo').text('Hiển thị ' + from + '–' + to + ' / ' + totalRecords + ' bản ghi');

            // Pagination buttons
            var totalPages = Math.ceil(totalRecords / pageLength);
            var btns = '';
            if (page > 0)
                btns += '<button type="button" class="btn btn-sm btn-outline-secondary" id="btnDiscPrev">← Trước</button>';
            for (var p = Math.max(0, page-2); p <= Math.min(totalPages-1, page+2); p++) {
                btns += '<button type="button" class="btn btn-sm ' + (p === page ? 'btn-primary' : 'btn-outline-secondary') + ' btnDiscPage" data-page="' + p + '">' + (p+1) + '</button>';
            }
            if (page < totalPages - 1)
                btns += '<button type="button" class="btn btn-sm btn-outline-secondary" id="btnDiscNext">Sau →</button>';
            $('#discPaginationBtns').html(btns);
        });
    }

    // ── Pagination ────────────────────────────────────────────────────────
    $('#btnDiscSearch').on('click', function () { loadData(0); });
    $(document).on('click', '#btnDiscPrev',  function () { loadData(currentPage - 1); });
    $(document).on('click', '#btnDiscNext',  function () { loadData(currentPage + 1); });
    $(document).on('click', '.btnDiscPage',  function () { loadData(parseInt($(this).data('page'))); });

    // Enter trong bộ lọc
    $('#filterDiscSku, #filterDiscDateFrom, #filterDiscDateTo').on('keydown', function(e){
        if (e.key === 'Enter') loadData(0);
    });

    // ── Checkbox logic ────────────────────────────────────────────────────
    function getSelectedIds() {
        return $('.disc-row-check:checked').map(function(){ return parseInt($(this).val()); }).get();
    }

    function updateBulkBar() {
        var ids = getSelectedIds();
        if (ids.length > 0) {
            $('#discBulkCount').text(ids.length + ' đã chọn');
            $('#discBulkBar').removeClass('d-none').addClass('d-flex');
        } else {
            $('#discBulkBar').addClass('d-none').removeClass('d-flex');
        }
        $('#discCheckAll').prop('indeterminate',
            ids.length > 0 && ids.length < $('.disc-row-check').length);
        $('#discCheckAll').prop('checked',
            ids.length > 0 && ids.length === $('.disc-row-check').length);
    }

    function clearBulkSelection() {
        $('.disc-row-check').prop('checked', false);
        $('#discCheckAll').prop('checked', false).prop('indeterminate', false);
        updateBulkBar();
    }

    $(document).on('change', '.disc-row-check', updateBulkBar);

    $('#discCheckAll').on('change', function () {
        $('.disc-row-check').prop('checked', this.checked);
        updateBulkBar();
    });

    $('#btnDiscBulkClear').on('click', clearBulkSelection);

    // ── Bulk update ───────────────────────────────────────────────────────
    $('#btnDiscBulkSave').on('click', function () {
        var ids    = getSelectedIds();
        var status = $('#discBulkStatus').val();

        if (!ids.length) { alert('Chưa chọn bản ghi nào.'); return; }
        if (!status)     { alert('Vui lòng chọn trạng thái xử lý.'); return; }

        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, {
            action:            'tgs_doc_tracker_bulk_update_discrepancy',
            nonce:             nonce,
            discrepancy_ids:   ids,
            resolution_status: status,
            resolution_note:   $('#discBulkNote').val(),
            resolution_method: $('#discBulkMethod').val(),
        }, function (res) {
            $btn.prop('disabled', false);
            alert(res.success ? '✅ ' + res.data.message : '❌ ' + (res.data?.message || 'Lỗi'));
            if (res.success) {
                $('#discBulkStatus').val('');
                $('#discBulkMethod').val('');
                $('#discBulkNote').val('');
                loadData(currentPage);
            }
        });
    });

    // ── Modal chỉnh sửa đơn lẻ ───────────────────────────────────────────
    function bsModal(id) {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
    }

    $(document).on('click', '.btnDiscEdit', function () {
        var $btn = $(this);
        $('#discUpdateId').val($btn.data('id'));
        $('#discUpdateStatus').val($btn.data('status'));
        $('#discUpdateNote').val(decodeURIComponent($btn.data('note')));
        $('#discUpdateMethod').val(decodeURIComponent($btn.data('method')));
        bsModal('discUpdateModal').show();
    });

    $('#btnDiscUpdateSave').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, {
            action:             'tgs_doc_tracker_update_discrepancy',
            nonce:              nonce,
            discrepancy_id:     $('#discUpdateId').val(),
            resolution_status:  $('#discUpdateStatus').val(),
            resolution_note:    $('#discUpdateNote').val(),
            resolution_method:  $('#discUpdateMethod').val(),
        }, function (res) {
            $btn.prop('disabled', false);
            bsModal('discUpdateModal').hide();
            alert(res.success ? '✅ ' + res.data.message : '❌ ' + (res.data?.message || 'Lỗi'));
            if (res.success) loadData(currentPage);
        });
    });

    // ── Xem file chứng từ của phiếu ────────────────────────────────────
    $(document).on('click', '.btnViewFiles', function () {
        var ledgerId   = $(this).data('ledger-id');
        var ledgerCode = $(this).data('ledger-code') || ('Phiếu #' + ledgerId);
        $('#discFilesLedgerCode').text(ledgerCode);
        $('#discFilesBody').html('<div class="text-center py-4 text-muted"><i class="bx bx-loader-circle bx-spin me-1"></i> Đang tải...</div>');
        bsModal('discFilesModal').show();
        $.post(ajaxUrl, {
            action:    'tgs_doc_tracker_get_ledger_files',
            nonce:     nonce,
            ledger_id: ledgerId,
        }, function (res) {
            if (!res.success) {
                $('#discFilesBody').html('<div class="alert alert-danger">' + (res.data?.message || 'Không thể tải file.') + '</div>');
                return;
            }
            var files = res.data.files || [];
            if (!files.length) {
                $('#discFilesBody').html('<div class="text-center py-4 text-muted">Phiếu này chưa có file chứng từ nào.</div>');
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
            $('#discFilesBody').html(html);
        }).fail(function () {
            $('#discFilesBody').html('<div class="alert alert-danger">Lỗi kết nối.</div>');
        });
    });

    // Tải lần đầu
    loadData(0);
})(jQuery);
</script>
