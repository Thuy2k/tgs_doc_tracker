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
<?php
/*
 * ─── KHỐI "NGUỒN PHẦN MỀM" ĐÃ BỎ KHỎI GIAO DIỆN ────────────────────────────
 *
 * Trước đây file này dựng một card lớn ở CUỐI trang tạo phiếu, gồm ô chọn
 * nguồn phần mềm + thư viện chứng từ. Hai vấn đề:
 *
 *   1. Ô chọn nguồn gần như không ai đổi — luôn để mặc định.
 *   2. Thư viện chứng từ nằm tận cuối trang nên gần như không ai thấy.
 *
 * Nay: thư viện chứng từ chuyển lên thanh hành động đầu phiếu (nút "Chứng từ"
 * trong tgs_shop_management/admin-views/pages/ticket/create/components/
 * ticket_doc_actions.php), giống hệt màn phiếu nhập kho — thống nhất mọi loại
 * phiếu.
 *
 * Trường ledger_software_source VẪN gửi lên (input ẩn bên dưới) vì backend còn
 * dùng để thống kê phiếu theo nguồn; bỏ hẳn sẽ làm mất dữ liệu thống kê.
 *
 * doc-tracker-ticket.js bám theo ID chứ không theo vị trí, nên chuyển UI lên
 * đầu phiếu không phải sửa JS.
 */
?>
<!-- Hidden input để gửi kèm form phiếu -->
<input type="hidden" name="ledger_software_source" id="tgsHiddenSoftwareSource" value='["thu_kho"]'>
