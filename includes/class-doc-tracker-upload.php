<?php
/**
 * TGS Doc Tracker - Upload Handler
 *
 * Xử lý upload file tạm (ảnh, excel) vào thư viện chứng từ:
 *   - Upload tạm: wp-content/uploads/tgs-doc-tracker/tmp/{blog_id}/{session_key}/
 *   - Sau khi tạo phiếu thành công: commit → wp-content/uploads/tgs-doc-tracker/{blog_id}/{YYYY/MM/DD}/
 *
 * @package tgs_doc_tracker
 */

if (!defined('ABSPATH')) {
    exit;
}

class TGS_Doc_Tracker_Upload
{
    // ── Allowed mime types ───────────────────────────────────────────────
    private static $allowed_mimes = [
        'image/jpeg'                                                  => 'jpg',
        'image/png'                                                   => 'png',
        'image/gif'                                                   => 'gif',
        'image/webp'                                                  => 'webp',
        'application/pdf'                                             => 'pdf',
        'application/vnd.ms-excel'                                    => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/csv'                                                    => 'csv',
    ];

    // ── Lấy thư mục tmp ──────────────────────────────────────────────────
    private static function get_tmp_dir($blog_id, $session_key)
    {
        $upload_dir = wp_upload_dir();
        $base       = $upload_dir['basedir'];
        $dir        = trailingslashit($base) . TGS_DOC_TRACKER_UPLOAD_SUBDIR . '/tmp/' . intval($blog_id) . '/' . sanitize_key($session_key);
        wp_mkdir_p($dir);
        return trailingslashit($dir);
    }

    // ── Lấy thư mục chính thức (sau khi commit) ─────────────────────────
    private static function get_commit_dir($blog_id, $date = null)
    {
        $upload_dir = wp_upload_dir();
        $base       = $upload_dir['basedir'];
        if (!$date) {
            $date = current_time('Y/m/d');
        }
        $dir = trailingslashit($base) . TGS_DOC_TRACKER_UPLOAD_SUBDIR . '/' . intval($blog_id) . '/' . $date;
        wp_mkdir_p($dir);
        return trailingslashit($dir);
    }

    // ── URL của thư mục chính thức ───────────────────────────────────────
    private static function get_commit_url($blog_id, $date = null)
    {
        $upload_dir = wp_upload_dir();
        $base_url   = $upload_dir['baseurl'];
        if (!$date) {
            $date = current_time('Y/m/d');
        }
        return trailingslashit($base_url) . TGS_DOC_TRACKER_UPLOAD_SUBDIR . '/' . intval($blog_id) . '/' . $date . '/';
    }

    // ── Upload file tạm ──────────────────────────────────────────────────
    /**
     * @param array  $file        $_FILES entry
     * @param string $session_key Khóa phiên (user_id + '_' + ticket_type)
     * @param string $source_type 'excel_import' | 'ai_recognition' | 'manual'
     * @return array|WP_Error ['session_id', 'file_name', 'file_url', 'file_type', 'file_size']
     */
    public static function upload_temp($file, $session_key, $source_type = 'manual')
    {
        global $wpdb;

        $blog_id  = get_current_blog_id();
        $user_id  = get_current_user_id();

        // Validate mime
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mime     = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::$allowed_mimes[$mime])) {
            return new WP_Error('invalid_mime', 'Loại file không được phép: ' . esc_html($mime));
        }

        // Kiểm tra kích thước (max 20MB)
        if ($file['size'] > 20 * 1024 * 1024) {
            return new WP_Error('file_too_large', 'File quá lớn (tối đa 20MB).');
        }

        $ext       = self::$allowed_mimes[$mime];
        $file_type = in_array($ext, ['xls', 'xlsx', 'csv']) ? 'excel' : (in_array($ext, ['pdf']) ? 'pdf' : 'image');
        $safe_name = sanitize_file_name($file['name']);
        $unique    = uniqid('doc_', true) . '_' . $safe_name;
        $tmp_dir   = self::get_tmp_dir($blog_id, $session_key);
        $dest      = $tmp_dir . $unique;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return new WP_Error('upload_failed', 'Không thể lưu file tạm.');
        }

        // Ghi vào bảng session
        $table = TGS_Shop_Database::table('local_doc_tracker_session');
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'blog_id'     => $blog_id,
            'session_key' => $session_key,
            'file_name'   => $safe_name,
            'file_path'   => $dest,
            'file_url'    => '',   // URL sẽ cập nhật sau
            'file_type'   => $file_type,
            'file_size'   => $file['size'],
            'source_type' => $source_type,
            'committed'   => 0,
            'user_id'     => $user_id,
            'created_at'  => $now,
            'expires_at'  => date('Y-m-d H:i:s', strtotime('+24 hours', strtotime($now))),
        ]);

        return [
            'session_id' => $wpdb->insert_id,
            'file_name'  => $safe_name,
            'file_type'  => $file_type,
            'file_size'  => $file['size'],
        ];
    }

    // ── Xóa file tạm ────────────────────────────────────────────────────
    public static function delete_temp($session_id)
    {
        global $wpdb;

        $blog_id = get_current_blog_id();
        $table   = TGS_Shop_Database::table('local_doc_tracker_session');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE session_id = %d AND blog_id = %d AND committed = 0",
            $session_id,
            $blog_id
        ));

        if (!$row) {
            return new WP_Error('not_found', 'File không tồn tại hoặc đã commit.');
        }

        // Xóa file vật lý
        if (file_exists($row->file_path)) {
            @unlink($row->file_path);
        }

        $wpdb->delete($table, ['session_id' => $session_id, 'blog_id' => $blog_id]);

        return true;
    }

    // ── Lấy danh sách file tạm theo session_key ──────────────────────────
    public static function list_temp($session_key)
    {
        global $wpdb;

        $blog_id = get_current_blog_id();
        $table   = TGS_Shop_Database::table('local_doc_tracker_session');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT session_id, file_name, file_type, file_size, source_type, created_at
             FROM `{$table}`
             WHERE session_key = %s AND blog_id = %d AND committed = 0
             ORDER BY created_at DESC",
            $session_key,
            $blog_id
        ));
    }

    // ── Commit files tạm khi tạo phiếu thành công ───────────────────────
    /**
     * Copy files từ tmp → thư mục chính thức theo {blog_id}/{YYYY/MM/DD}/
     * Lưu đường dẫn vào local_ledger.local_ledger_advance_meta['doc_files']
     *
     * @param int    $ledger_id   ID phiếu vừa tạo
     * @param string $ticket_type Loại phiếu
     */
    public static function commit_temp_files($ledger_id, $ticket_type)
    {
        global $wpdb;

        if (!$ledger_id) {
            return;
        }

        $blog_id     = get_current_blog_id();
        $user_id     = get_current_user_id();
        $session_key = $user_id . '_' . $ticket_type;
        $table_sess  = TGS_Shop_Database::table('local_doc_tracker_session');

        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table_sess}`
             WHERE session_key = %s AND blog_id = %d AND committed = 0",
            $session_key,
            $blog_id
        ));

        if (empty($files)) {
            return;
        }

        $date       = current_time('Y/m/d');
        $commit_dir = self::get_commit_dir($blog_id, $date);
        $commit_url = self::get_commit_url($blog_id, $date);
        $doc_files  = [];

        foreach ($files as $f) {
            if (!file_exists($f->file_path)) {
                continue;
            }
            $dest_name = basename($f->file_path);
            $dest_path = $commit_dir . $dest_name;
            @rename($f->file_path, $dest_path);

            $file_url = $commit_url . $dest_name;
            $doc_files[] = [
                'file_name'   => $f->file_name,
                'file_url'    => $file_url,
                'file_type'   => $f->file_type,
                'file_size'   => (int) $f->file_size,
                'source_type' => $f->source_type,
                'uploaded_at' => $f->created_at,
            ];

            // Đánh dấu đã commit
            $wpdb->update($table_sess, [
                'committed'           => 1,
                'committed_ledger_id' => $ledger_id,
                'file_url'            => $file_url,
            ], ['session_id' => $f->session_id]);
        }

        if (empty($doc_files)) {
            return;
        }

        // Lưu đường dẫn vào local_ledger.local_ledger_advance_meta['doc_files']
        $table_ledger  = defined('TGS_TABLE_LOCAL_LEDGER')
            ? TGS_TABLE_LOCAL_LEDGER
            : $wpdb->prefix . 'local_ledger';

        $existing_meta = $wpdb->get_var($wpdb->prepare(
            "SELECT local_ledger_advance_meta FROM `{$table_ledger}` WHERE local_ledger_id = %d",
            $ledger_id
        ));

        $meta = $existing_meta ? json_decode($existing_meta, true) : [];
        if (!is_array($meta)) {
            $meta = [];
        }
        $meta['doc_files'] = array_merge($meta['doc_files'] ?? [], $doc_files);

        $wpdb->update($table_ledger, [
            'local_ledger_advance_meta' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
        ], ['local_ledger_id' => $ledger_id]);
    }

    // ── Dọn file tạm đã hết hạn ──────────────────────────────────────────
    public static function cleanup_expired()
    {
        global $wpdb;

        $table = TGS_Shop_Database::table('local_doc_tracker_session');
        $now   = current_time('mysql');

        $expired = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE committed = 0 AND expires_at < %s",
            $now
        ));

        foreach ($expired as $f) {
            if (file_exists($f->file_path)) {
                @unlink($f->file_path);
            }
        }

        $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$table}` WHERE committed = 0 AND expires_at < %s",
            $now
        ));
    }
}
