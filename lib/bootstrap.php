<?php
/**
 * 公共引导文件：时区、错误处理、通用小函数。
 * 所有入口文件（index.php / api.php / export.php / admin.php）第一步都 require 它。
 */

if (!is_file(__DIR__ . '/config.php')) {
    header('Content-Type: text/html; charset=utf-8');
    exit('缺少配置文件：请把 lib/config.sample.php 复制成 lib/config.php，再按需修改里面的数据库设置。');
}

require_once __DIR__ . '/config.php';

date_default_timezone_set(TIMEZONE);

// 不要把 PHP 原始报错直接吐给访问者，出错时走下面的友好提示
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

/* ============================ 通用小函数 ============================ */

/** HTML 转义（输出到页面时统一用它） */
function rq_h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** 字符串长度，没有 mbstring 也能用 */
function rq_strlen($s)
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/** 字符串截取，没有 mbstring 也能用 */
function rq_substr($s, $start, $len)
{
    return function_exists('mb_substr') ? mb_substr($s, $start, $len, 'UTF-8') : substr($s, $start, $len);
}

/**
 * 校验并规范化 YYYY-MM-DD，非法返回 false
 */
function rq_check_date($s)
{
    $s = trim((string) $s);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return false;
    }
    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return false;
    }
    return $s;
}

/** 2026-08-26 -> 星期三 */
function rq_weekday_cn($day)
{
    $names = ['日', '一', '二', '三', '四', '五', '六'];
    return '星期' . $names[(int) date('w', strtotime($day))];
}

/** 2026-08-26 -> 周三 */
function rq_weekday_short($day)
{
    $names = ['日', '一', '二', '三', '四', '五', '六'];
    return '周' . $names[(int) date('w', strtotime($day))];
}

/** 2026-08-26 -> 2026年8月26日 */
function rq_date_cn($day)
{
    $t = strtotime($day);
    return sprintf('%d年%d月%d日', (int) date('Y', $t), (int) date('n', $t), (int) date('j', $t));
}

/** 2026-08-26 -> 8/26 */
function rq_date_md($day)
{
    $t = strtotime($day);
    return sprintf('%d/%d', (int) date('n', $t), (int) date('j', $t));
}

/** 2026-08-26 12:30:00 -> 12:30（当天）或 8/25 12:30（跨天） */
function rq_time_short($datetime, $today)
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '';
    }
    if (date('Y-m-d', $ts) === $today) {
        return date('H:i', $ts);
    }
    return rq_date_md(date('Y-m-d', $ts)) . ' ' . date('H:i', $ts);
}

/** 输出 JSON 并结束请求 */
function rq_json($data, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 出错时的友好提示页（api.php 里会自动改成 JSON）
 */
function rq_fatal($title, $detail = '', $hint = '')
{
    if (defined('RQ_JSON_MODE') && RQ_JSON_MODE) {
        rq_json([
            'ok'      => false,
            'error'   => $title,
            'detail'  => $detail,
            'hint'    => $hint,
        ], 500);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>出错了 · ' . rq_h(SITE_NAME) . '</title>'
        . '<style>body{margin:0;padding:40px 20px;background:#f5f6f8;color:#1f2937;'
        . 'font-family:-apple-system,"Segoe UI","Microsoft YaHei",sans-serif;line-height:1.7}'
        . '.box{max-width:720px;margin:0 auto;background:#fff;border-radius:14px;padding:28px 30px;'
        . 'box-shadow:0 6px 24px rgba(15,23,42,.08);border-top:5px solid #ef4444}'
        . 'h1{font-size:20px;margin:0 0 14px}pre{background:#f8fafc;border:1px solid #e5e7eb;'
        . 'border-radius:8px;padding:12px;white-space:pre-wrap;word-break:break-all;font-size:13px;color:#334155}'
        . '.hint{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;'
        . 'font-size:14px;color:#92400e;margin-top:14px}</style></head><body><div class="box">'
        . '<h1>' . rq_h($title) . '</h1>';
    if ($detail !== '') {
        echo '<pre>' . rq_h($detail) . '</pre>';
    }
    if ($hint !== '') {
        echo '<div class="hint">' . rq_h($hint) . '</div>';
    }
    echo '<p style="color:#64748b;font-size:13px;margin-top:18px">配置文件：lib/config.php</p>'
        . '</div></body></html>';
    exit;
}

/** 站点副标题（配置为空则不显示） */
function rq_site_sub()
{
    return defined('SITE_SUB') ? trim((string) SITE_SUB) : '';
}
