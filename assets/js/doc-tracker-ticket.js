/**
 * TGS Doc Tracker - Ticket Page JS
 *
 * Xử lý:
 * 1. Inject software source card vào trang tạo phiếu (trước #ticketQuickSettingsCard)
 * 2. Quản lý thư viện file tạm (upload, xóa, hiển thị)
 * 3. Sync software source vào hidden input để gửi kèm form phiếu
 * 4. Fill doc_quantity vào cột "SL chứng từ" khi Excel/AI fill sản phẩm
 */

(function ($) {
    'use strict';

    if (typeof tgsDocTracker === 'undefined') {
        console.warn('[TGS Doc Tracker] tgsDocTracker config not found.');
        return;
    }

    var AJAX_URL    = tgsDocTracker.ajaxUrl;
    var NONCE       = tgsDocTracker.nonce;
    var TICKET_TYPE = tgsDocTracker.ticketType;

    // ── Khởi tạo sau khi DOM sẵn sàng ────────────────────────────────────
    $(document).ready(function () {
        initSoftwareSourceCard();
        initTempFileLibrary();
        initDocQtyColumnFill();
        initDocQtyMismatchWarning();
        initSubmitGuard();
        initFormSubmitHook();
        loadTempFiles();
        injectMismatchConfirmModal();

        // Expose reload function cho các module khác (e.g. ticket-excel-import.js)
        tgsDocTracker.reloadFiles = loadTempFiles;
    });

    // ── 1. Inject software source card ────────────────────────────────────
    function initSoftwareSourceCard() {
        // Card đã được inject qua PHP hook, chỉ cần sync select → hidden input
        $('#tgs_ledger_software_source').on('change', function () {
            var val = $(this).val();
            $('#tgsHiddenSoftwareSource').val(val === 'null' ? '' : val);
        }).trigger('change');

        // Nút mở thư viện
        $('#btnTgsDocUploadTemp').on('click', function () {
            $('#tgsDocTempFileInput').click();
        });

        // Upload file
        $('#tgsDocTempFileInput').on('change', function () {
            uploadFiles(this.files, 'manual');
            this.value = ''; // reset
        });

        // Tooltip Bootstrap (nếu có)
        $('[data-bs-toggle="tooltip"]').tooltip();
    }

    // ── 2. Quản lý thư viện file tạm ─────────────────────────────────────
    function initTempFileLibrary() {
        // Modal library
        $('#btnTgsDocAddFromLibModal').on('click', function () {
            $('#tgsDocLibModalFileInput').click();
        });
        $('#tgsDocLibModalFileInput').on('change', function () {
            uploadFiles(this.files, 'manual');
            this.value = '';
        });

        // Xóa file
        $(document).on('click', '.btnTgsDocDeleteTemp', function () {
            var sessionId = $(this).data('session-id');
            if (!confirm('Xóa file này khỏi thư viện chứng từ?')) return;
            $.post(AJAX_URL, {
                action:     'tgs_doc_tracker_delete_temp',
                nonce:      NONCE,
                session_id: sessionId,
            }, function (res) {
                if (res.success) {
                    loadTempFiles();
                } else {
                    alert('Lỗi: ' + (res.data?.message || 'Không thể xóa'));
                }
            });
        });
    }

    // ── Upload files ─────────────────────────────────────────────────────
    function uploadFiles(fileList, sourceType) {
        if (!fileList || !fileList.length) return;

        Array.from(fileList).forEach(function (file) {
            var fd = new FormData();
            fd.append('action',      'tgs_doc_tracker_upload_temp');
            fd.append('nonce',       NONCE);
            fd.append('ticket_type', TICKET_TYPE);
            fd.append('source_type', sourceType);
            fd.append('file',        file);

            $.ajax({
                url:         AJAX_URL,
                type:        'POST',
                data:        fd,
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res.success) {
                        loadTempFiles();
                    } else {
                        alert('Upload thất bại: ' + (res.data?.message || 'Lỗi không xác định'));
                    }
                },
                error: function () {
                    alert('Upload thất bại: Lỗi kết nối.');
                }
            });
        });
    }

    // ── Load danh sách file tạm ──────────────────────────────────────────
    function loadTempFiles() {
        $.post(AJAX_URL, {
            action:      'tgs_doc_tracker_list_temp',
            nonce:       NONCE,
            ticket_type: TICKET_TYPE,
        }, function (res) {
            if (!res.success) return;
            renderTempFiles(res.data.files || []);
        });
    }

    function renderTempFiles(files) {
        var $inline  = $('#tgsDocTempFileList');
        var $libList = $('#tgsDocLibModalItems');
        var $noMsg   = $('#tgsDocNoFileMsg');
        var $libEmpty = $('#tgsDocLibModalEmpty');

        if (!files.length) {
            $noMsg.show();
            $inline.find('.tgs-doc-file-chip').remove();
            $libList.html('');
            $libEmpty.show();
            return;
        }

        $noMsg.hide();
        $libEmpty.hide();

        // Inline chips
        $inline.find('.tgs-doc-file-chip').remove();
        files.forEach(function (f) {
            var icon = f.file_type === 'excel' ? 'bx-spreadsheet' : (f.file_type === 'pdf' ? 'bxs-file-pdf' : 'bx-image');
            var chip = $('<span class="badge bg-light text-dark border tgs-doc-file-chip d-flex align-items-center gap-1" style="font-size:0.78em;max-width:140px;overflow:hidden;">'
                + '<i class="bx ' + icon + ' text-info"></i>'
                + '<span class="text-truncate">' + escHtml(f.file_name) + '</span>'
                + '<button type="button" class="btn-close btn-close-sm p-0 ms-1 btnTgsDocDeleteTemp" data-session-id="' + f.session_id + '" style="font-size:0.6em;"></button>'
                + '</span>');
            $inline.append(chip);
        });

        // Modal list
        var libHtml = '';
        files.forEach(function (f) {
            var size = formatFileSize(f.file_size);
            libHtml += '<div class="list-group-item d-flex justify-content-between align-items-center">'
                + '<div>'
                + '<span class="fw-semibold">' + escHtml(f.file_name) + '</span>'
                + '<small class="text-muted ms-2">' + escHtml(f.file_type) + ' · ' + size + '</small>'
                + '<span class="badge bg-secondary ms-2">' + escHtml(f.source_type) + '</span>'
                + '</div>'
                + '<button type="button" class="btn btn-sm btn-outline-danger btnTgsDocDeleteTemp" data-session-id="' + f.session_id + '">'
                + '<i class="bx bx-trash"></i>'
                + '</button>'
                + '</div>';
        });
        $libList.html(libHtml);
    }

    // ── 3. Fill doc_quantity vào cột SL chứng từ ─────────────────────────
    function initDocQtyColumnFill() {
        // Lắng nghe event tgs_excel_fill / tgs_ai_fill từ các plugin khác
        $(document).on('tgs_product_row_filled', function (e, data) {
            /**
             * data = { sku, product_name_id, quantity, row_index, table_id }
             * Khi Excel/AI fill dòng sản phẩm → set doc_quantity = quantity
             */
            fillDocQty(data);
        });

        // Fallback: monitor mutation trên bảng để tự detect fill
        observeProductTable('#ticketProductsTableBody');
        observeProductTable('#ticketGiftProductsTableBody');
    }

    function fillDocQty(data) {
        var tableId = data.table_id || 'ticketProductsTableBody';
        var rowIndex = parseInt(data.row_index);
        var qty = parseFloat(data.quantity || 0);
        if (isNaN(qty)) return;

        var $rows = $('#' + tableId + ' tr[data-row-index]');
        var $row  = $rows.filter('[data-row-index="' + rowIndex + '"]');
        if (!$row.length) {
            // Fallback: tìm theo vị trí
            $row = $('#' + tableId + ' tr').eq(rowIndex);
        }

        var $docQtyInput = $row.find('input.tgs-doc-qty-input');
        if ($docQtyInput.length) {
            $docQtyInput.val(qty.toFixed(1));
            // Sau khi fill từ Excel/AI, kiểm tra lệch ngay
            // (lúc này actual_qty cũng vừa được fill cùng lúc
            //  nên thường sẽ bằng nhau → không cảnh báo)
            setTimeout(function () { checkRowMismatch($row); }, 50);
        }
    }

    // ── Observer để auto-thêm ô SL chứng từ khi row được thêm ───────────
    var _observedTables = {};
    function observeProductTable(selector) {
        var el = document.querySelector(selector);
        if (!el || _observedTables[selector]) return;
        _observedTables[selector] = true;

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1 && node.tagName === 'TR') {
                        ensureDocQtyCell(node);
                    }
                });
            });
        });
        observer.observe(el, { childList: true });
    }

    // Đảm bảo mỗi dòng sản phẩm có ô SL chứng từ
    function ensureDocQtyCell(tr) {
        var $tr = $(tr);
        if ($tr.find('.tgs-doc-qty-cell').length) return;

        // Tìm ô "Tồn kho" để insert sau
        var $stockCell = $tr.find('td.tgs-stock-cell, td[data-col="stock"]');
        if (!$stockCell.length) {
            // Fallback: ô thứ 5 (index 4, sau #, SP, HSD, ThaoTác, Tracking, TồnKho)
            $stockCell = $tr.find('td').eq(5);
        }

        var $docCell = $('<td class="tgs-doc-qty-cell text-center">'
            + '<input type="number" class="form-control form-control-sm tgs-doc-qty-input text-center" '
            + 'min="0" step="0.001" placeholder="—" disabled '
            + 'title="Số lượng từ chứng từ (chỉ đọc, fill từ Excel/AI)">'
            + '</td>');

        $stockCell.after($docCell);
    }

    // ── 4. Cảnh báo lệch chứng từ khi sửa số lượng thực tế ─────────────
    function initDocQtyMismatchWarning() {
        // Lắng nghe thay đổi trên cả 2 bảng (sản phẩm chính + hàng tặng)
        $(document).on('input change', '.ticket-quantity-input, .ticket-gift-quantity-input', function () {
            checkRowMismatch($(this).closest('tr'));
        });

        // Khi edit-purchase / edit-sale gọi trigger('tgs_check_doc_qty_mismatch') sau khi fill doc_qty
        $(document).on('tgs_check_doc_qty_mismatch', 'tr', function () {
            checkRowMismatch($(this));
        });

        // Sau khi tất cả dòng hiện tại được load (edit mode), kiểm tra lại toàn bộ
        setTimeout(function () {
            $('#ticketProductsTableBody tr, #ticketGiftProductsTableBody tr').each(function () {
                checkRowMismatch($(this));
            });
        }, 800);
    }

    /**
     * Kiểm tra 1 dòng: nếu doc_qty đã được set và khác actual_qty → highlight đỏ
     * @param {jQuery} $tr
     */
    function checkRowMismatch($tr) {
        if (!$tr.length || $tr.is('#ticketEmptyRow, #ticketGiftEmptyRow')) return;

        var $docInput = $tr.find('.tgs-doc-qty-input');
        var $qtyInput = $tr.find('.ticket-quantity-input, .ticket-gift-quantity-input').first();

        if (!$docInput.length || !$qtyInput.length) return;

        var docVal = $docInput.val();
        if (docVal === '' || docVal === null || docVal === undefined) {
            // Chưa có số lượng chứng từ → không cảnh báo
            clearRowMismatch($tr);
            return;
        }

        var docQty    = parseFloat(docVal);
        var actualQty = parseFloat($qtyInput.val()) || 0;

        if (isNaN(docQty)) {
            clearRowMismatch($tr);
            return;
        }

        var diff = actualQty - docQty;
        var eps  = 0.0001; // sai số float

        if (Math.abs(diff) > eps) {
            setRowMismatch($tr, docQty, actualQty, diff);
        } else {
            clearRowMismatch($tr);
        }
    }

    function setRowMismatch($tr, docQty, actualQty, diff) {
        $tr.addClass('tgs-doc-qty-mismatch');

        // Thêm / cập nhật badge cảnh báo ngay dưới ô số lượng
        var $qtyCell = $tr.find('.ticket-quantity-input, .ticket-gift-quantity-input').first().closest('td');
        var diffText = (diff > 0 ? '+' : '') + diff.toFixed(diff % 1 === 0 ? 0 : 3);
        var msg = '⚠️ Lệch CT: ' + docQty.toFixed(docQty % 1 === 0 ? 0 : 3)
                + ' → TT: ' + actualQty.toFixed(actualQty % 1 === 0 ? 0 : 3)
                + ' (' + diffText + ')';

        var $badge = $qtyCell.find('.tgs-doc-mismatch-badge');
        if (!$badge.length) {
            $badge = $('<div class="tgs-doc-mismatch-badge"></div>');
            $qtyCell.append($badge);
        }
        $badge.text(msg);
    }

    function clearRowMismatch($tr) {
        $tr.removeClass('tgs-doc-qty-mismatch');
        $tr.find('.tgs-doc-mismatch-badge').remove();
    }

    // ── 5. Intercept submit: cảnh báo nếu có dòng lệch ──────────────────
    var _submitConfirmed = false; // flag tránh check 2 lần sau khi user xác nhận

    function initSubmitGuard() {
        // Hành động tạo phiếu chính thường qua click '#btnTicketSubmit' → trigger form submit
        // Ta chặn tại lúc click button, trước khi form submit
        $(document).on('click.tgsDocGuard', '#btnTicketSubmit', function (e) {
            if (_submitConfirmed) {
                _submitConfirmed = false; // reset flag
                return; // cho phép gửi bình thường
            }

            var mismatches = collectMismatches();
            if (!mismatches.length) return; // không lệch → tiếp tục bình thường

            // Có lệch → chặn và hiện modal xác nhận
            e.preventDefault();
            e.stopImmediatePropagation();
            showMismatchConfirmModal(mismatches);
        });
    }

    /**
     * Thu thập tất cả dòng đang lệch trong 2 bảng
     * @returns {Array} mảng các object { productName, sku, docQty, actualQty, diff }
     */
    function collectMismatches() {
        var results = [];
        var $rows = $('#ticketProductsTableBody tr, #ticketGiftProductsTableBody tr')
            .filter('.tgs-doc-qty-mismatch');

        $rows.each(function () {
            var $tr  = $(this);
            var name = $tr.find('td:nth-child(2)').text().trim().split('\n')[0].trim();
            var sku  = $tr.find('[data-sku], .product-sku').text().trim()
                     || $tr.find('td:nth-child(2) small, td:nth-child(2) .text-muted').first().text().trim();
            var docQty    = parseFloat($tr.find('.tgs-doc-qty-input').val()) || 0;
            var actualQty = parseFloat($tr.find('.ticket-quantity-input, .ticket-gift-quantity-input').first().val()) || 0;
            results.push({
                name:      name || '—',
                sku:       sku  || '—',
                docQty:    docQty,
                actualQty: actualQty,
                diff:      actualQty - docQty,
            });
        });

        return results;
    }

    function injectMismatchConfirmModal() {
        if ($('#tgsDocMismatchConfirmModal').length) return;
        var html = [
            '<div class="modal fade" id="tgsDocMismatchConfirmModal" tabindex="-1" data-bs-backdrop="static">',
            '  <div class="modal-dialog modal-lg">',
            '    <div class="modal-content">',
            '      <div class="modal-header bg-danger text-white">',
            '        <h5 class="modal-title"><i class="bx bx-error me-2"></i>Cảnh báo: Số lượng lệch chứng từ</h5>',
            '        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>',
            '      </div>',
            '      <div class="modal-body">',
            '        <p class="text-danger fw-semibold mb-2">',
            '          <i class="bx bx-error-circle me-1"></i>',
            '          Các dòng dưới đang có <strong>số lượng thực tế khác số lượng chứng từ</strong>.',
            '          Kiểm tra lại trước khi tạo phiếu.',
            '        </p>',
            '        <div class="table-responsive">',
            '        <table class="table table-sm table-bordered mb-0">',
            '          <thead class="table-danger">',
            '            <tr>',
            '              <th>Sản phẩm</th>',
            '              <th>SKU</th>',
            '              <th class="text-center">SL chứng từ</th>',
            '              <th class="text-center">SL thực tế</th>',
            '              <th class="text-center">Lệch</th>',
            '            </tr>',
            '          </thead>',
            '          <tbody id="tgsDocMismatchConfirmList"></tbody>',
            '        </table>',
            '        </div>',
            '        <p class="text-muted small mt-3 mb-0">',
            '          <i class="bx bx-info-circle me-1"></i>',
            '          Bấm <strong>"Vẫn tạo phiếu"</strong> nếu bạn chủ động điều chỉnh số lượng khác chứng từ.',
            '          Lệch sẽ được ghi lại để theo dõi sau.',
            '        </p>',
            '      </div>',
            '      <div class="modal-footer">',
            '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">',
            '          <i class="bx bx-x me-1"></i>Quay lại kiểm tra',
            '        </button>',
            '        <button type="button" class="btn btn-danger" id="btnTgsDocConfirmSubmit">',
            '          <i class="bx bx-check me-1"></i>Vẫn tạo phiếu',
            '        </button>',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>',
        ].join('\n');
        $('body').append(html);

        // Xác nhận tiếp tục
        $(document).on('click', '#btnTgsDocConfirmSubmit', function () {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('tgsDocMismatchConfirmModal')).hide();
            _submitConfirmed = true;
            // Trigger lại click của nút submit gốc
            $('#btnTicketSubmit').trigger('click');
        });
    }

    function showMismatchConfirmModal(mismatches) {
        var rows = mismatches.map(function (m) {
            var diffClass = m.diff < 0 ? 'text-danger' : 'text-warning';
            var diffText  = (m.diff > 0 ? '+' : '') + m.diff.toFixed(3).replace(/\.?0+$/, '');
            return '<tr>'
                + '<td class="text-truncate" style="max-width:200px;">' + escHtml(m.name) + '</td>'
                + '<td><code>' + escHtml(m.sku) + '</code></td>'
                + '<td class="text-center fw-semibold">' + m.docQty + '</td>'
                + '<td class="text-center fw-semibold">' + m.actualQty + '</td>'
                + '<td class="text-center fw-bold ' + diffClass + '">' + diffText + '</td>'
                + '</tr>';
        }).join('');

        $('#tgsDocMismatchConfirmList').html(rows);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('tgsDocMismatchConfirmModal')).show();
    }

    // ── 6. Hook form submit để gửi kèm software_source ─────────────────
    function initFormSubmitHook() {
        $(document).on('tgs_before_ticket_submit', function (e, formData) {
            var src = $('#tgsHiddenSoftwareSource').val();
            if (src) {
                formData.ledger_software_source = src;
            }
        });

        // Lấy doc_quantity từ mỗi dòng và gửi kèm
        $(document).on('tgs_collect_product_row_extra', function (e, rowData) {
            var $row = rowData.$row;
            var docQty = parseFloat($row.find('.tgs-doc-qty-input').val());
            if (!isNaN(docQty) && docQty > 0) {
                rowData.extra.doc_quantity = docQty;
            }
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatFileSize(bytes) {
        bytes = parseInt(bytes || 0);
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

})(jQuery);
