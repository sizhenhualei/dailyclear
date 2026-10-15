<?php
/**
 * 数据访问层：日期计算 + 记录的读写。
 * 表结构：riqing_records(record_date, subject, content, updated_at)，一天一科一行。
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

/* ============================ 日期计算 ============================ */

/**
 * 当前时刻（受 TIMEZONE 与 DAY_ROLLOVER_HOUR 影响）
 */
function rq_now()
{
    return date('Y-m-d H:i:s');
}

/**
 * 「今天」是哪一天。
 * DAY_ROLLOVER_HOUR = 4 时，凌晨 3 点填写仍然算作前一天。
 */
function rq_today()
{
    $hour = (int) DAY_ROLLOVER_HOUR;
    $ts   = time();
    if ($hour > 0) {
        $ts -= $hour * 3600;
    }
    return date('Y-m-d', $ts);
}

/** 日期加减天数 */
function rq_shift_day($day, $days)
{
    $ts = strtotime($day . ' 12:00:00');
    return date('Y-m-d', $ts + ((int) $days) * 86400);
}

/**
 * 取某一天所在的一周（周一 ~ 周日，中国习惯）
 *
 * @return array [周一日期, 周日日期]
 */
function rq_week_of($day)
{
    $ts   = strtotime($day . ' 12:00:00');
    $dow  = (int) date('N', $ts);           // 1=周一 ... 7=周日
    $mon  = date('Y-m-d', $ts - ($dow - 1) * 86400);
    $sun  = date('Y-m-d', $ts + (7 - $dow) * 86400);
    return [$mon, $sun];
}

/**
 * 取某个月的第一天和最后一天
 *
 * @return array [首日, 末日]
 */
function rq_month_bounds($month)
{
    $ts = strtotime($month . '-01 12:00:00');
    if (!$ts) {
        $ts = strtotime(date('Y-m') . '-01 12:00:00');
    }
    return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
}

/**
 * 生成 [起始日, 结束日] 之间的所有日期
 *
 * @return string[]
 */
function rq_date_range($start, $end, $maxDays = 400)
{
    $out = [];
    $cur = $start;
    $guard = 0;
    while ($cur <= $end && $guard < $maxDays) {
        $out[] = $cur;
        $cur = rq_shift_day($cur, 1);
        $guard++;
    }
    return $out;
}

/** 科目中文名 */
function rq_subject_name($key)
{
    $subjects = SUBJECTS;
    return isset($subjects[$key]) ? $subjects[$key] : $key;
}

/** 科目 key 是否合法 */
function rq_subject_valid($key)
{
    $subjects = SUBJECTS;
    return isset($subjects[$key]);
}

/* ============================ 站点设置（键值对） ============================ */

/** 设置项缓存（一次请求内只查一次库） */
function &rq_settings_cache()
{
    static $cache = [];
    return $cache;
}

/**
 * 读一个设置项；没有则返回 $default
 */
function rq_setting_get($key, $default = '')
{
    $cache = &rq_settings_cache();
    if (!array_key_exists($key, $cache)) {
        $stmt = rq_db()->prepare('SELECT v FROM riqing_settings WHERE k = ?');
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        $cache[$key] = ($v === false || $v === null) ? null : (string) $v;
    }
    return $cache[$key] === null ? $default : $cache[$key];
}

/**
 * 写一个设置项（$value 传空 = 删除该设置，恢复默认）
 */
function rq_setting_set($key, $value)
{
    $db    = rq_db();
    $cache = &rq_settings_cache();

    $stmt = $db->prepare('DELETE FROM riqing_settings WHERE k = ?');
    $stmt->execute([$key]);
    unset($cache[$key]);

    if ($value === null || $value === '') {
        $cache[$key] = null;
        return;
    }

    $stmt = $db->prepare('INSERT INTO riqing_settings (k, v) VALUES (?, ?)');
    $stmt->execute([$key, (string) $value]);
    $cache[$key] = (string) $value;
}

/* ============================ 科目顺序（临时，只对当天生效） ============================ */

/**
 * 科目 key 的顺序（数组）。
 * 默认 = lib/config.php 里 SUBJECTS 的顺序；
 * 后台可以在当天临时调整，**第二天自动恢复默认**（靠 subject_order_date 判断）。
 *
 * @return string[]
 */
function rq_subject_order()
{
    $keys  = array_keys(SUBJECTS);
    $saved = rq_setting_get('subject_order', '');
    $day   = rq_setting_get('subject_order_date', '');

    // 没调过、或者不是今天调的 → 用配置里的默认顺序
    if ($saved === '' || $saved === null || $day !== rq_today()) {
        return $keys;
    }

    $out = [];
    foreach (explode(',', $saved) as $k) {
        $k = trim($k);
        if (isset(SUBJECTS[$k]) && !in_array($k, $out, true)) {
            $out[] = $k;
        }
    }
    // 配置里后来新增的科目补在最后，配置里删掉的科目自动忽略
    foreach ($keys as $k) {
        if (!in_array($k, $out, true)) {
            $out[] = $k;
        }
    }
    return $out ? $out : $keys;
}

/** 今天是否临时调过顺序 */
function rq_subject_order_is_custom()
{
    return rq_setting_get('subject_order', '') !== ''
        && rq_setting_get('subject_order_date', '') === rq_today();
}

/** 临时顺序是哪一天调的（没调过返回 ''） */
function rq_subject_order_date()
{
    return (string) rq_setting_get('subject_order_date', '');
}

/**
 * 按当前顺序返回 [科目 key => 中文名]
 */
function rq_subjects()
{
    $out = [];
    foreach (rq_subject_order() as $k) {
        $out[$k] = SUBJECTS[$k];
    }
    return $out;
}

/**
 * 保存科目顺序（只接受合法的 key，缺的补在后面）。记下是哪天调的，第二天自动失效。
 *
 * @return string[] 保存后的顺序
 */
function rq_save_subject_order(array $keys)
{
    $clean = [];
    foreach ($keys as $k) {
        if (isset(SUBJECTS[$k]) && !in_array($k, $clean, true)) {
            $clean[] = $k;
        }
    }
    foreach (array_keys(SUBJECTS) as $k) {
        if (!in_array($k, $clean, true)) {
            $clean[] = $k;
        }
    }
    rq_setting_set('subject_order', implode(',', $clean));
    rq_setting_set('subject_order_date', rq_today());
    return $clean;
}

/**
 * 把 $a 和 $b 两个科目的位置对调
 *
 * @return bool 是否真的换了
 */
function rq_swap_subject($a, $b)
{
    $order = rq_subject_order();
    $i     = array_search($a, $order, true);
    $j     = array_search($b, $order, true);
    if ($i === false || $j === false || $i === $j) {
        return false;
    }
    $order[$i] = $b;
    $order[$j] = $a;
    rq_save_subject_order($order);
    return true;
}

/** 恢复默认顺序（= lib/config.php 里的顺序，同时清掉「哪天调的」记录） */
function rq_reset_subject_order()
{
    rq_setting_set('subject_order', '');
    rq_setting_set('subject_order_date', '');
}

/* ============================ 读写记录 ============================ */

/**
 * 取某一天的全部记录
 *
 * @return array [subject => ['content' => string, 'updated_at' => string]]
 */
function rq_day_records($day)
{
    $stmt = rq_db()->prepare('SELECT subject, content, updated_at FROM riqing_records WHERE record_date = ?');
    $stmt->execute([$day]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['subject']] = [
            'content'    => (string) $row['content'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
    return $out;
}

/**
 * 保存某天某科的内容。内容为空字符串 = 删除这一条。
 *
 * @return string 规范化后的内容
 */
function rq_save_record($day, $subject, $content)
{
    if (!rq_subject_valid($subject)) {
        throw new InvalidArgumentException('科目不正确');
    }
    if (!rq_check_date($day)) {
        throw new InvalidArgumentException('日期不正确');
    }

    $content = str_replace("\r\n", "\n", (string) $content);
    $content = trim($content);
    if (rq_strlen($content) > MAX_CONTENT_LEN) {
        $content = rq_substr($content, 0, MAX_CONTENT_LEN);
    }

    $db = rq_db();

    // 清空 = 删除该条记录
    if ($content === '') {
        $stmt = $db->prepare('DELETE FROM riqing_records WHERE record_date = ? AND subject = ?');
        $stmt->execute([$day, $subject]);
        return '';
    }

    $stmt = $db->prepare('SELECT id FROM riqing_records WHERE record_date = ? AND subject = ?');
    $stmt->execute([$day, $subject]);
    $id = $stmt->fetchColumn();

    if ($id) {
        $stmt = $db->prepare('UPDATE riqing_records SET content = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$content, rq_now(), $id]);
    } else {
        $stmt = $db->prepare('INSERT INTO riqing_records (record_date, subject, content, updated_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$day, $subject, $content, rq_now()]);
    }
    return $content;
}

/**
 * 取一个日期区间的记录
 *
 * @return array [日期][科目] => ['content' => string, 'updated_at' => string]
 */
function rq_range_records($start, $end)
{
    $stmt = rq_db()->prepare(
        'SELECT record_date, subject, content, updated_at FROM riqing_records
         WHERE record_date >= ? AND record_date <= ?
         ORDER BY record_date ASC, id ASC'
    );
    $stmt->execute([$start, $end]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['record_date']][$row['subject']] = [
            'content'    => (string) $row['content'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
    return $out;
}

/**
 * 取一个日期区间内，每天填了几科（用于日历上的标记）
 *
 * @return array [日期 => 科数]
 */
function rq_day_counts($start, $end)
{
    $stmt = rq_db()->prepare(
        'SELECT record_date, COUNT(*) AS n FROM riqing_records
         WHERE record_date >= ? AND record_date <= ?
         GROUP BY record_date'
    );
    $stmt->execute([$start, $end]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['record_date']] = (int) $row['n'];
    }
    return $out;
}

/**
 * 统计一个区间内的总条数
 */
function rq_count_range($start, $end)
{
    $stmt = rq_db()->prepare('SELECT COUNT(*) FROM riqing_records WHERE record_date >= ? AND record_date <= ?');
    $stmt->execute([$start, $end]);
    return (int) $stmt->fetchColumn();
}
