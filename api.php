<?php
/**
 * 接口：读取某天记录 / 保存某天某科内容。
 * 返回 JSON。前端用 fetch 调用，不刷新页面。
 *
 *   GET  api.php?action=day&day=2026-08-26
 *   POST api.php   action=save & day=... & subject=... & content=...
 */

define('RQ_JSON_MODE', true);

require_once __DIR__ . '/lib/repo.php';

/**
 * 取参数：优先 JSON body，其次 POST，最后 GET
 */
function rq_input($key, $default = '')
{
    static $json = null;
    static $loaded = false;

    if (!$loaded) {
        $loaded = true;
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }
    }

    if (is_array($json) && array_key_exists($key, $json)) {
        return $json[$key];
    }
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

try {
    $action = (string) rq_input('action', 'day');

    switch ($action) {

        /* ---------------- 读取某一天 ---------------- */
        case 'day':
            $day = rq_check_date(rq_input('day', rq_today()));
            if (!$day) {
                rq_json(['ok' => false, 'error' => '日期不正确'], 400);
            }

            $records = rq_day_records($day);
            $out     = [];
            $filled  = 0;
            foreach (rq_subjects() as $key => $label) {
                $content = isset($records[$key]) ? trim((string) $records[$key]['content']) : '';
                if ($content !== '') {
                    $filled++;
                }
                $out[$key] = [
                    'name'       => $label,
                    'content'    => $content,
                    'updated_at' => isset($records[$key]) ? $records[$key]['updated_at'] : '',
                ];
            }

            rq_json([
                'ok'         => true,
                'day'        => $day,
                'weekday'    => rq_weekday_cn($day),
                'filled'     => $filled,
                'total'      => count(rq_subjects()),
                'records'    => $out,
            ]);
            break;

        /* ---------------- 保存 ---------------- */
        case 'save':
            if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
                rq_json(['ok' => false, 'error' => '保存请用 POST'], 405);
            }

            $day     = rq_check_date(rq_input('day', ''));
            $subject = trim((string) rq_input('subject', ''));
            $content = (string) rq_input('content', '');

            if (!$day) {
                rq_json(['ok' => false, 'error' => '日期不正确'], 400);
            }
            if (!rq_subject_valid($subject)) {
                rq_json(['ok' => false, 'error' => '科目不正确'], 400);
            }

            $saved   = rq_save_record($day, $subject, $content);
            $records = rq_day_records($day);

            $filled = 0;
            foreach (array_keys(rq_subjects()) as $key) {
                if (isset($records[$key]) && trim((string) $records[$key]['content']) !== '') {
                    $filled++;
                }
            }

            rq_json([
                'ok'         => true,
                'day'        => $day,
                'subject'    => $subject,
                'content'    => $saved,
                'updated_at' => isset($records[$subject]) ? $records[$subject]['updated_at'] : '',
                'filled'     => $filled,
                'total'      => count(rq_subjects()),
            ]);
            break;

        default:
            rq_json(['ok' => false, 'error' => '未知操作'], 400);
    }
} catch (InvalidArgumentException $e) {
    rq_json(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    rq_json(['ok' => false, 'error' => '服务器错误：' . $e->getMessage()], 500);
}
