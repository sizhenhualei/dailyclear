<?php
/**
 * ============================================================
 *  日清表 · 后台
 *  按日期查看每天填了什么，并能导出任意日期范围的 xls。
 * ============================================================
 */

require_once __DIR__ . '/lib/repo.php';
require_once __DIR__ . '/lib/partials.php';

$today = rq_today();

// 当前查看的日期
$day = rq_check_date(isset($_GET['day']) ? $_GET['day'] : '');
if (!$day) {
    $day = $today;
}

// 当前查看的月份（日历用）
$month = (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) ? $_GET['month'] : substr($day, 0, 7);
list($mFirst, $mLast) = rq_month_bounds($month);

$counts  = rq_day_counts($mFirst, $mLast);
$records = rq_day_records($day);

/* ---------- 主页卡片顺序：临时调整（只对今天生效，第二天自动恢复） ---------- */
if (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' && isset($_POST['order_action'])) {
    $act = (string) $_POST['order_action'];

    if ($act === 'save') {
        $done = false;

        // ① 页面里的 JS 会把「换了好几次之后」的最终顺序放在 order 里
        if (isset($_POST['order']) && trim((string) $_POST['order']) !== '') {
            $keys = [];
            foreach (explode(',', (string) $_POST['order']) as $k) {
                $k = trim($k);
                if (isset(SUBJECTS[$k]) && !in_array($k, $keys, true)) {
                    $keys[] = $k;
                }
            }
            if ($keys) {
                rq_save_subject_order($keys);
                $done = true;
            }
        }

        // ② 万一浏览器没跑 JS：按「和某某调换」逐条换过去
        if (!$done && isset($_POST['swap']) && is_array($_POST['swap'])) {
            $n = 0;
            foreach (rq_subject_order() as $k) {
                if (!isset($_POST['swap'][$k])) {
                    continue;
                }
                $other = (string) $_POST['swap'][$k];
                if ($other !== '' && $other !== $k && rq_swap_subject($k, $other)) {
                    $n++;
                }
            }
            $done = ($n > 0);
        }

        $msg = $done ? '顺序已保存：只对今天生效，明天打开自动恢复默认' : '顺序没有变化';
    } elseif ($act === 'reset') {
        rq_reset_subject_order();
        $msg = '已恢复默认顺序（语文 数学 英语 物理 化学 生物）';
    } else {
        $msg = '没有做任何修改';
    }

    // 改完跳回去，避免刷新页面时重复提交
    header('Location: admin.php?day=' . urlencode($day) . '&month=' . urlencode($month) . '&msg=' . urlencode($msg));
    exit;
}
$orderMsg = isset($_GET['msg']) ? trim((string) $_GET['msg']) : '';
$subjects = rq_subjects();

list($weekStart, $weekEnd) = rq_week_of($day);

$filled = 0;
foreach (array_keys(rq_subjects()) as $key) {
    if (isset($records[$key]) && trim((string) $records[$key]['content']) !== '') {
        $filled++;
    }
}
$total = count(rq_subjects());

$monthDays  = count($counts);
$monthCount = array_sum($counts);
$allCount   = rq_count_range('1970-01-01', '2999-12-31');

// 日历格子
$firstTs     = strtotime($mFirst . ' 12:00:00');
$offset      = (int) date('N', $firstTs) - 1;          // 周一 = 0
$daysInMonth = (int) date('t', $firstTs);
$prevMonth   = date('Y-m', strtotime($mFirst . ' -1 month'));
$nextMonth   = date('Y-m', strtotime($mFirst . '+1 month'));
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <?php rq_render_head('后台 · ' . SITE_NAME); ?>
</head>
<body>

<header class="topbar">
    <div class="topbar-in">
        <a class="brand" href="index.php"><span class="dotmark"></span><?= rq_h(SITE_NAME) ?>
            <?php if (rq_site_sub() !== ''): ?><span class="sub"><?= rq_h(rq_site_sub()) ?></span><?php endif; ?>
        </a>
        <div class="today-line"><b>后台记录</b><span class="pill">共 <?= (int) $allCount ?> 条</span></div>
        <div class="topbar-actions">
            <a class="btn" href="index.php">返回填写页</a>
            <a class="btn" href="export.php?start=<?= rq_h($weekStart) ?>&amp;end=<?= rq_h($weekEnd) ?>">导出本周 xls</a>
        </div>
    </div>
</header>

<div class="admin-wrap">
    <div class="admin-cols">

        <!-- ============ 左：日历 ============ -->
        <section class="panel">
            <h2>
                <a class="btn mini" href="admin.php?month=<?= rq_h($prevMonth) ?>&amp;day=<?= rq_h($prevMonth . '-01') ?>">← 上月</a>
                <?= (int) substr($month, 0, 4) ?> 年 <?= (int) substr($month, 5, 2) ?> 月
                <a class="btn mini" href="admin.php?month=<?= rq_h($nextMonth) ?>&amp;day=<?= rq_h($nextMonth . '-01') ?>">下月 →</a>
                <span class="grow">本月填了 <?= (int) $monthDays ?> 天 / <?= (int) $monthCount ?> 条</span>
            </h2>

            <div class="cal">
                <?php foreach (['一', '二', '三', '四', '五', '六', '日'] as $dow): ?>
                    <div class="dow"><?= $dow ?></div>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < $offset; $i++): ?>
                    <div class="day blank"></div>
                <?php endfor; ?>

                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                    $cur  = sprintf('%s-%02d', $month, $d);
                    $n    = isset($counts[$cur]) ? $counts[$cur] : 0;
                    $cls  = 'day';
                    if ($n > 0)          { $cls .= ' has'; }
                    if ($cur === $today) { $cls .= ' today'; }
                    if ($cur === $day)   { $cls .= ' sel'; }
                    ?>
                    <a class="<?= $cls ?>" href="admin.php?day=<?= rq_h($cur) ?>&amp;month=<?= rq_h($month) ?>">
                        <span class="n"><?= $d ?></span>
                        <span class="badge"><?= $n > 0 ? $n . ' 科' : '—' ?></span>
                    </a>
                <?php endfor; ?>
            </div>

            <div class="legend">
                <span>蓝底 = 当天有记录</span>
                <span>方框 = 今天</span>
                <span>深色 = 正在查看</span>
            </div>

            <h2 style="margin-top:1.3rem">跳到某一天</h2>
            <form class="filters" method="get" action="admin.php">
                <input type="date" name="day" value="<?= rq_h($day) ?>">
                <button class="btn primary" type="submit">查看</button>
                <a class="btn" href="admin.php">回到今天</a>
            </form>
        </section>

        <!-- ============ 右：当天记录 + 导出 ============ -->
        <div>
            <section class="panel" style="margin-bottom:1rem">
                <h2>
                    <?= rq_h(rq_date_cn($day)) ?> · <?= rq_h(rq_weekday_cn($day)) ?>
                    <span class="grow">已填 <?= (int) $filled ?>/<?= (int) $total ?> 科</span>
                </h2>

                <table class="rec">
                    <thead>
                        <tr>
                            <th>科目</th>
                            <th>内容</th>
                            <th>最后更新</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subjects as $key => $label):
                        $row     = isset($records[$key]) ? $records[$key] : null;
                        $content = $row ? trim((string) $row['content']) : '';
                        $isEmpty = ($content === '');
                        ?>
                        <tr data-subject="<?= rq_h($key) ?>">
                            <td class="subj"><?= rq_h($label) ?></td>
                            <td>
                                <p class="content<?= $isEmpty ? ' is-empty' : '' ?>" id="c-<?= rq_h($key) ?>"
                                   data-raw-target data-empty="未填写"><?= $isEmpty ? '未填写' : rq_h($content) ?></p>
                            </td>
                            <td class="time" id="t-<?= rq_h($key) ?>">
                                <?= (!$isEmpty && $row) ? rq_h(rq_time_short($row['updated_at'], $today)) : '—' ?>
                            </td>
                            <td class="act">
                                <button type="button" class="btn mini" data-edit
                                        data-day="<?= rq_h($day) ?>" data-subject="<?= rq_h($key) ?>">修改</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <!-- ============ 主页卡片顺序（临时，只对今天生效） ============ -->
            <section class="panel" style="margin-bottom:1rem">
                <h2>
                    主页卡片顺序
                    <span class="grow">只对今天生效</span>
                </h2>

                <p class="panel-hint">
                    想让哪一科排到前面，就在它那一行选「和 某某 调换」——可以连着换好几处，
                    换完点最下面的 <b>保存顺序</b> 才生效。<br>
                    顺序 <b>只对今天生效，明天打开自动恢复默认</b>；想现在就恢复，点「恢复默认顺序」。
                </p>

                <?php if ($orderMsg !== ''): ?>
                    <p class="panel-hint msg-ok"><?= rq_h($orderMsg) ?></p>
                <?php endif; ?>

                <?php if (rq_subject_order_is_custom()): ?>
                    <p class="panel-hint msg-ok">
                        现在主页用的就是今天临时调过的顺序（<?= rq_h(rq_date_md(rq_subject_order_date())) ?> 调）。
                    </p>
                <?php endif; ?>

                <form id="order-form" method="post" action="admin.php">
                    <input type="hidden" name="order_action" value="save">
                    <input type="hidden" name="order" id="order-value" value="<?= rq_h(implode(',', array_keys($subjects))) ?>">

                    <div class="order-list" id="order-list">
                        <?php $oi = 0; ?>
                        <?php foreach ($subjects as $key => $label): ?>
                            <div class="order-item" data-subject="<?= rq_h($key) ?>">
                                <span class="ord"><?= $oi + 1 ?></span>
                                <span class="tag"><?= rq_h($label) ?></span>
                                <select class="order-swap" name="swap[<?= rq_h($key) ?>]">
                                    <option value="">— 不动 —</option>
                                    <?php foreach ($subjects as $k2 => $l2): ?>
                                        <?php if ($k2 === $key) { continue; } ?>
                                        <option value="<?= rq_h($k2) ?>">和 <?= rq_h($l2) ?> 调换</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php $oi++; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="order-actions">
                        <button class="btn primary" type="submit" id="order-save">保存顺序</button>
                        <span class="order-tip" id="order-tip">换好之后点保存</span>
                    </div>
                </form>

                <form method="post" action="admin.php" style="margin-top:.8rem">
                    <input type="hidden" name="order_action" value="reset">
                    <button class="btn mini" type="submit">恢复默认顺序</button>
                </form>
            </section>

            <section class="panel">
                <h2>导出 Excel（.xls）</h2>

                <p style="margin:0 0 .9rem;color:#4b5563;font-size:.9rem">
                    「周表」= 行是科目、列是日期、格子里是内容，适合打印贴在墙上；
                    「明细」= 一条记录一行，适合自己再统计。
                </p>

                <div class="filters" style="margin-bottom:1rem">
                    <a class="btn primary" href="export.php?start=<?= rq_h($weekStart) ?>&amp;end=<?= rq_h($weekEnd) ?>">
                        导出本周（<?= rq_h(rq_date_md($weekStart)) ?> ~ <?= rq_h(rq_date_md($weekEnd)) ?>）周表
                    </a>
                    <a class="btn" href="export.php?start=<?= rq_h($day) ?>&amp;end=<?= rq_h($day) ?>">导出这一天</a>
                    <a class="btn" href="export.php?start=<?= rq_h($day) ?>&amp;end=<?= rq_h($day) ?>&amp;type=detail">导出这一天（明细）</a>
                </div>

                <form class="filters" method="get" action="export.php">
                    <span style="font-size:.9rem;color:#4b5563">任意日期范围：</span>
                    <input type="date" name="start" value="<?= rq_h($mFirst) ?>">
                    <span>~</span>
                    <input type="date" name="end" value="<?= rq_h($mLast) ?>">
                    <button class="btn primary" type="submit" name="type" value="grid">导出周表</button>
                    <button class="btn" type="submit" name="type" value="detail">导出明细</button>
                </form>

                <div class="stat-row" style="margin-top:1.1rem">
                    <span>本月填写 <b><?= (int) $monthDays ?></b> 天</span>
                    <span>本月记录 <b><?= (int) $monthCount ?></b> 条</span>
                    <span>累计记录 <b><?= (int) $allCount ?></b> 条</span>
                    <span>今天 <b><?= rq_h($today) ?></b></span>
                </div>
            </section>
        </div>
    </div>
</div>

<?php rq_render_modal(); ?>
<?php rq_render_data_script($day, $records, '未填写'); ?>
<script src="assets/app.js?v=7"></script>
<script src="assets/order.js?v=1"></script>
</body>
</html>
