<?php
/**
 * 导出 Excel（.xls）。
 *
 *   不带参数            -> 导出本周（周一 ~ 周日）
 *   ?week=2026-08-26   -> 导出那一天所在的一周
 *   ?start=&end=       -> 导出任意日期范围
 *   &type=grid         -> 科目 × 日期 的表格（默认）
 *   &type=detail       -> 一条记录一行
 *
 * 文件格式是 Excel 2003 XML，Excel / WPS / Numbers 都能直接打开，
 * 不需要服务器安装任何 PHP 扩展。
 */

require_once __DIR__ . '/lib/repo.php';
require_once __DIR__ . '/lib/xls.php';

$today  = rq_today();
$type   = (isset($_GET['type']) && $_GET['type'] === 'detail') ? 'detail' : 'grid';
$start  = rq_check_date(isset($_GET['start']) ? $_GET['start'] : '');
$end    = rq_check_date(isset($_GET['end']) ? $_GET['end'] : '');
$weekOf = rq_check_date(isset($_GET['week']) ? $_GET['week'] : '');

// 没有指定范围就按「本周」算
if (!$start || !$end) {
    list($start, $end) = rq_week_of($weekOf ? $weekOf : $today);
}

// 起始/结束写反了也不报错，自动交换
if ($end < $start) {
    $tmp   = $start;
    $start = $end;
    $end   = $tmp;
}

// 周表最多 62 列（两个多月），超了就截断并在表里注明
$limitNote = '';
$spanDays  = count(rq_date_range($start, $end, 4000));
if ($type === 'grid' && $spanDays > 62) {
    $end       = rq_shift_day($start, 61);
    $limitNote = '（日期范围超过 62 天，周表只显示前 62 天，完整数据请用「明细」导出）';
}

$records = rq_range_records($start, $end);

$sheets = [];
if ($type === 'detail') {
    $sheets[] = rq_xls_detail_sheet($start, $end, $records);
} else {
    $sheets[] = rq_xls_week_sheet($start, $end, $records, SITE_NAME, $limitNote);
    $sheets[] = rq_xls_detail_sheet($start, $end, $records);
}

$fileBase = SITE_NAME . '_' . $start . '_' . $end;
rq_xls_send($fileBase, $sheets);
