<?php
/**
 * 页面公共片段：数据注入 + 填写弹窗。
 * 客户界面（index.php）和后台（admin.php）共用同一套弹窗。
 */

require_once __DIR__ . '/repo.php';

/**
 * 把「某天的六科内容」注入到前端，供弹窗读取。
 *
 * @param string $day       当前操作的是哪一天
 * @param array  $records   rq_day_records() 的结果
 * @param string $emptyText 空白时显示的占位文字
 */
function rq_render_data_script($day, array $records, $emptyText)
{
    $map = [];
    foreach (array_keys(rq_subjects()) as $key) {
        $map[$key] = isset($records[$key]['content']) ? trim((string) $records[$key]['content']) : '';
    }

    $data = [
        'day'       => $day,
        'emptyText' => $emptyText,
        'records'   => $map,
    ];

    echo '<script>window.RQ_DATA = '
        . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ';</script>';
}

/**
 * 填写弹窗（含 toast 容器）
 */
function rq_render_modal()
{
    $subjects = rq_subjects();
    $maxLen   = (int) MAX_CONTENT_LEN;
    ?>
<div class="modal" id="modal" data-maxlen="<?= $maxLen ?>">
    <div class="modal-mask" data-close></div>
    <div class="modal-box">
        <div class="modal-title" id="f-title">填写</div>
        <div class="modal-sub">选科目 → 写内容 → 保存。第二天自动换新的一天，历史记录永久保留。</div>

        <input type="hidden" id="f-day" value="">
        <input type="hidden" id="f-subject" value="">

        <span class="field-label">科目</span>
        <div class="chips">
            <?php foreach ($subjects as $key => $label): ?>
                <button type="button" class="chip" data-subject="<?= rq_h($key) ?>"><?= rq_h($label) ?></button>
            <?php endforeach; ?>
        </div>

        <label class="field-label" for="f-content">今天的内容</label>
        <textarea id="f-content" maxlength="<?= $maxLen ?>"
            placeholder="例如：背必修二 Unit3 单词 + 完成数学卷子第 2 张"></textarea>
        <div class="counter">
            <span>Ctrl / ⌘ + Enter 快速保存</span>
            <span id="f-count">0 / <?= $maxLen ?></span>
        </div>

        <p class="modal-warn" id="f-warn" role="alert" hidden></p>

        <div class="modal-actions">
            <button type="button" class="btn danger" id="f-delete">清空</button>
            <span class="spacer"></span>
            <button type="button" class="btn" data-close>取消</button>
            <button type="button" class="btn primary" id="f-save">保存</button>
        </div>
    </div>
</div>
<div class="toast" id="toast"></div>
    <?php
}

/**
 * 网页 head 里公共的部分
 */
function rq_render_head($title)
{
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <title><?= rq_h($title) ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%232563eb'/%3E%3Ctext x='32' y='46' font-size='36' text-anchor='middle' fill='white' font-family='sans-serif'%3E%E6%97%A5%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="assets/style.css?v=14">
    <?php
}
