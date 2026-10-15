/* ============================================================
 *  后台「主页卡片顺序」：选「和 某某 调换」立刻换位，
 *  可以连着换好几处，最后点「保存顺序」才真正生效。
 *  （没有 JS 也能用：下拉框会以 swap[key] 提交，由 PHP 逐条调换）
 * ============================================================ */
(function () {
    var form = document.getElementById('order-form');
    if (!form) { return; }

    var list   = document.getElementById('order-list');
    var hidden = document.getElementById('order-value');
    var tip    = document.getElementById('order-tip');
    if (!list || !hidden) { return; }

    var initial = hidden.value;

    function rows() {
        return Array.prototype.slice.call(list.querySelectorAll('.order-item'));
    }

    function keyOf(row) {
        return row.getAttribute('data-subject');
    }

    function sync() {
        var current = [];
        rows().forEach(function (row, i) {
            var n = row.querySelector('.ord');
            if (n) { n.textContent = i + 1; }
            var sel = row.querySelector('select');
            if (sel) { sel.value = ''; }
            current.push(keyOf(row));
        });

        hidden.value = current.join(',');

        if (tip) {
            var changed = (hidden.value !== initial);
            tip.textContent = changed
                ? '换好了，点「保存顺序」生效（只对今天有效）'
                : '换好之后点保存';
            tip.className = 'order-tip' + (changed ? ' dirty' : '');
        }
    }

    // 选「和某某调换」→ 两行直接换位置，可以连着换
    list.addEventListener('change', function (e) {
        var sel = e.target;
        if (!sel || sel.tagName !== 'SELECT') { return; }

        var other = sel.value;
        if (!other) { return; }

        var row = sel.closest ? sel.closest('.order-item') : null;
        if (!row) { return; }

        var target = null;
        rows().forEach(function (r) {
            if (keyOf(r) === other) { target = r; }
        });
        if (!target || target === row) { return; }

        // 借助一个临时节点把两行互换
        var marker = document.createElement('div');
        row.parentNode.insertBefore(marker, row);
        target.parentNode.insertBefore(row, target);
        marker.parentNode.insertBefore(target, marker);
        marker.parentNode.removeChild(marker);

        sync();
    });

    sync();
})();
