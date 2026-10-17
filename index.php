<?php
/**
 * ============================================================
 *  日清表 · 客户界面（老师填写 / 学生大屏看）
 *  语数外物化生六个区域，卡片之间没有间隙，第一屏尽量铺满。
 *  填写 / 导出按钮放在页面最下方（往下滑才看到），不占常驻空间。
 *  每天自动切换到新的一天，历史记录永久保留。
 * ============================================================
 */

require_once __DIR__ . '/lib/repo.php';
require_once __DIR__ . '/lib/partials.php';

$today   = rq_today();
$records = rq_day_records($today);

$filled = 0;
foreach (array_keys(rq_subjects()) as $key) {
    if (isset($records[$key]) && trim((string) $records[$key]['content']) !== '') {
        $filled++;
    }
}
$total = count(rq_subjects());
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <?php rq_render_head(SITE_NAME . ' · ' . rq_date_cn($today)); ?>
</head>
<body>

<!-- 先套用上次选的布局与字号，避免刷新时闪一下 -->
<script>(function(){try{var m=localStorage.getItem('rq.layout');if(m==='3'||m==='1'){document.documentElement.setAttribute('data-layout',m);}var f=parseFloat(localStorage.getItem('rq.fs'));if(f&&f>0.5&&f<2.5){document.documentElement.style.setProperty('--fs-scale',String(f));}}catch(e){}})();</script>

<header class="topbar">
    <div class="topbar-in">
        <!-- 字号调节：字体 － ＋（教室大屏看不清时放大，记在这台设备的浏览器里） -->
        <div class="fs-switch" id="fs-switch" role="group" aria-label="字号">
            <span class="fs-label">字体</span>
            <button type="button" data-fs="-1" title="缩小字体" aria-label="缩小字体">－</button>
            <button type="button" data-fs="1" title="放大字体" aria-label="放大字体">＋</button>
        </div>

        <div class="today-block">
            <div class="today-date"><?= rq_h(rq_date_cn($today)) ?></div>
            <div class="today-meta">
                <span class="wk"><?= rq_h(rq_weekday_cn($today)) ?></span>
                <span class="pill<?= $filled === $total ? ' ok' : '' ?>" id="fill-summary">已填 <?= $filled ?>/<?= $total ?> 科</span>
            </div>
        </div>

        <!-- 布局切换：自动 / 强制三列 / 强制单列（选一次，记在这台设备的浏览器里） -->
        <div class="layout-switch" id="layout-switch" role="group" aria-label="显示布局">
            <button type="button" data-layout="auto" title="按屏幕自动排版">自动</button>
            <button type="button" data-layout="3" title="强制三列（六科三行两列铺满一屏）">三列</button>
            <button type="button" data-layout="1" title="强制单列（一科一行，从前往后滑）">单列</button>
        </div>
    </div>
</header>

<main class="wrap">
    <section class="grid" id="grid">
        <?php foreach (rq_subjects() as $key => $label):
            $row     = isset($records[$key]) ? $records[$key] : null;
            $content = $row ? trim((string) $row['content']) : '';
            $isEmpty = ($content === '');
            $timeTxt = (!$isEmpty && $row) ? rq_time_short($row['updated_at'], $today) : '';
            ?>
            <article class="card<?= $isEmpty ? '' : ' filled' ?>" data-subject="<?= rq_h($key) ?>"
                     data-edit data-day="<?= rq_h($today) ?>" title="点一下填写 / 修改<?= rq_h($label) ?>">
                <div class="card-head">
                    <span class="tag"><?= rq_h($label) ?></span>
                    <span class="state<?= $isEmpty ? '' : ' ok' ?>"><?= $isEmpty ? '未填写' : '已填 ' . rq_h($timeTxt) ?></span>
                </div>

                <div class="card-body">
                    <p class="content<?= $isEmpty ? ' is-empty' : '' ?>" id="c-<?= rq_h($key) ?>" data-raw-target
                       data-empty="今天还没有填写"><?= $isEmpty ? '今天还没有填写' : rq_h($content) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="page-actions">
        <button type="button" class="btn primary big" data-edit data-day="<?= rq_h($today) ?>">我要填写</button>
        <a class="btn" href="export.php" title="导出本周（周一 ~ 周日）的日清表">导出本周 xls</a>
    </div>

    <p class="footnote site-foot">Powered By 华蕾 &amp; DSH</p>
</main>

<?php rq_render_modal(); ?>
<?php rq_render_data_script($today, $records, '今天还没有填写'); ?>
<script src="assets/app.js?v=7"></script>
</body>
</html>
