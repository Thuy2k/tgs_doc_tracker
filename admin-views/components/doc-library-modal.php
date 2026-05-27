<?php
/**
 * Component: Modal Thư viện chứng từ
 *
 * Modal xem/xóa file tạm trong thư viện chứng từ.
 * File tạm được tích lũy từ Excel import và AI nhận diện.
 *
 * @package tgs_doc_tracker
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- TGS Doc Tracker: Doc Library Modal -->
<div class="modal fade" id="tgsDocLibraryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-folder-open me-2 text-primary"></i>
                    Thư viện chứng từ phiếu này
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 px-3 mb-3">
                    <i class="bx bx-info-circle me-1"></i>
                    Các file này được lưu tạm. Khi <strong>tạo phiếu thành công</strong>, hệ thống tự động lưu file vào thư mục phiếu và đính kèm vào metadata phiếu.
                </div>

                <!-- Nút tải lên thêm -->
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Danh sách file chứng từ</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnTgsDocAddFromLibModal">
                            <i class="bx bx-upload me-1"></i>Tải lên thêm
                        </button>
                        <input type="file" id="tgsDocLibModalFileInput"
                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.xls,.xlsx,.csv"
                               multiple style="display:none;">
                    </div>
                </div>

                <!-- Danh sách file -->
                <div id="tgsDocLibModalFileList">
                    <div class="text-center text-muted py-4" id="tgsDocLibModalEmpty">
                        <i class="bx bx-folder bx-lg d-block mb-2"></i>
                        Chưa có file chứng từ nào.
                    </div>
                    <div class="list-group" id="tgsDocLibModalItems"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
