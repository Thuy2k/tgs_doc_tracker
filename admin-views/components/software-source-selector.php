<?php
/**
 * Component: Chọn nguồn phần mềm
 *
 * Inject vào trang tạo phiếu thông qua hook tgs_ticket_create_after_modals.
 * Hiển thị như 1 card nằm ngay trước khối "Cài đặt nhanh" (inject bằng JS sau DOM ready).
 *
 * Các lựa chọn:
 *   null / không chọn → mặc định (hệ thống mình)
 *   ["thu_kho"]       → thủ kho gửi  (MẶC ĐỊNH)
 *   ["root","htsoft"] → htsoft và mình
 *   ["htsoft"]        → chỉ HTSoft
 *   ["root"]          → chỉ hệ thống mình
 *   ["chung_tu"]      → chỉ chứng từ
 *
 * @package tgs_doc_tracker
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- TGS Doc Tracker: Software Source Selector Card -->
<div id="tgsDocSoftwareSourceCard" class="card mb-4" style="border-left: 3px solid #696cff;">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bx bx-code-alt text-primary"></i>
        <h5 class="card-title mb-0">Nguồn phần mềm</h5>
        <small class="text-muted ms-2">Phiếu này xuất phát từ hệ thống nào?</small>
    </div>
    <div class="card-body py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold mb-1" for="tgs_ledger_software_source">
                    Nguồn phần mềm
                    <i class="bx bx-info-circle text-muted ms-1"
                       title="Dùng để thống kê riêng phiếu theo nguồn. VD: htsoft và mình = phiếu có dữ liệu từ cả 2 hệ thống."
                       data-bs-toggle="tooltip"></i>
                </label>
                <select class="form-select" id="tgs_ledger_software_source" name="tgs_ledger_software_source">
                    <option value='["thu_kho"]'>Thủ kho gửi</option>
                    <option value='["root","htsoft"]'>HTSoft và hệ thống mình</option>
                    <option value='["root"]' selected>Chỉ hệ thống mình</option>
                    <option value='["htsoft"]'>Chỉ HTSoft</option>
                    <option value='["chung_tu"]'>Chỉ chứng từ</option>
                    <option value="null">Mặc định (hệ thống mình)</option>
                </select>
                <div class="form-text text-muted">
                    Mặc định <em>"Chỉ hệ thống mình"</em>. Chọn <em>"Thủ kho gửi"</em> khi phiếu xuất phát từ thủ kho; chọn <em>"HTSoft và hệ thống mình"</em> khi cần ghi nhận cả 2 nguồn.
                </div>
            </div>

            <!-- Thư viện chứng từ tạm -->
            <div class="col-md-6">
                <label class="form-label fw-semibold mb-1">
                    <i class="bx bx-paperclip text-primary me-1"></i>
                    Thư viện chứng từ
                    <small class="text-muted">(file tạm – sẽ lưu khi tạo phiếu)</small>
                </label>
                <div id="tgsDocTempLibraryWrapper">
                    <div id="tgsDocTempFileList" class="d-flex flex-wrap gap-2 mb-2">
                        <!-- File tạm sẽ được thêm bằng JS -->
                        <span class="text-muted small" id="tgsDocNoFileMsg">Chưa có file chứng từ.</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnTgsDocUploadTemp"
                            title="Tải lên file chứng từ (ảnh, Excel, PDF)">
                        <i class="bx bx-upload me-1"></i>Tải lên chứng từ
                    </button>
                    <input type="file" id="tgsDocTempFileInput"
                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.xls,.xlsx,.csv"
                           multiple style="display:none;">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden input để gửi kèm form phiếu -->
<input type="hidden" name="ledger_software_source" id="tgsHiddenSoftwareSource" value='["thu_kho"]'>
