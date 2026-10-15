/* ==========================================================================
   日清表 · 前端交互（动态科目版）
   · 内容唯一来源：window.RQ_DATA.records[科目]，服务端渲染在页面里
   · 客户界面与后台共用本文件，靠 id 约定定位元素：
       #c-<科目>   内容元素
       #t-<科目>   最后更新时间（只有后台有）
       .card[data-subject]  卡片（只有客户界面有）
   · 全程不刷新页面；不自动刷新
   ========================================================================== */

(function () {
    'use strict';

    var modal = document.getElementById('modal');
    if (!modal) { return; }

    var box       = modal.querySelector('.modal-box');
    var dayInput  = document.getElementById('f-day');
    var subjInput = document.getElementById('f-subject');
    var textarea  = document.getElementById('f-content');
    var delBtn    = document.getElementById('f-delete');
    var saveBtn   = document.getElementById('f-save');
    var titleEl   = document.getElementById('f-title');
    var countEl   = document.getElementById('f-count');
    var toastEl   = document.getElementById('toast');
    var chips     = Array.prototype.slice.call(modal.querySelectorAll('.chip'));
    var maxLen    = parseInt(modal.getAttribute('data-maxlen') || '2000', 10);

    // 服务端注入的数据
    var boot = window.RQ_DATA || {};
    var emptyText = boot.emptyText || '—';

    // 本地缓存：day -> { 科目: 内容 }，以及未保存的草稿 day|科目 -> 内容
    var store  = {};
    var drafts = {};
    if (boot.day) { store[boot.day] = boot.records || {}; }

    var state = { day: '', subject: '', original: '', busy: false };
    var toastTimer = null;

    /* ------------------------- 小工具 ------------------------- */

    function toast(msg, isError) {
        if (!toastEl) { return; }
        toastEl.textContent = msg;
        toastEl.className = 'toast on' + (isError ? ' err' : '');
        if (toastTimer) { clearTimeout(toastTimer); }
        toastTimer = setTimeout(function () {
            toastEl.className = 'toast' + (isError ? ' err' : '');
        }, 2200);
    }

    function subjectLabel(key) {
        for (var i = 0; i < chips.length; i++) {
            if (chips[i].getAttribute('data-subject') === key) {
                return chips[i].textContent.trim();
            }
        }
        return key;
    }

    function subjectKeys() {
        return chips.map(function (c) { return c.getAttribute('data-subject'); });
    }

    function dayMap(day) {
        return store[day] || {};
    }

    function contentOf(day, subject) {
        var v = dayMap(day)[subject];
        return typeof v === 'string' ? v : '';
    }

    function draftKey(day, subject) { return day + '|' + subject; }

    function firstEmptySubject(day) {
        var keys = subjectKeys();
        for (var i = 0; i < keys.length; i++) {
            if (contentOf(day, keys[i]) === '') { return keys[i]; }
        }
        return keys.length ? keys[0] : '';
    }

    /* ------------------------- 弹窗 ------------------------- */

    function selectSubject(key) {
        // 换科目之前，先把当前输入存成草稿，别让老师白打一遍
        if (state.subject && state.day) {
            drafts[draftKey(state.day, state.subject)] = textarea.value;
        }
        state.subject = key;
        if (subjInput) { subjInput.value = key; }
        chips.forEach(function (c) {
            c.classList.toggle('on', c.getAttribute('data-subject') === key);
        });
        if (box) { box.setAttribute('data-subject', key); }
        if (titleEl) { titleEl.textContent = '填写' + subjectLabel(key); }

        var draft = drafts[draftKey(state.day, key)];
        state.original = contentOf(state.day, key);
        textarea.value = (typeof draft === 'string') ? draft : state.original;
        if (delBtn) { delBtn.style.display = state.original ? '' : 'none'; }
        updateCount();
    }

    function updateCount() {
        if (!countEl) { return; }
        var n = textarea.value.length;
        countEl.textContent = n + ' / ' + maxLen;
        countEl.style.color = n > maxLen ? '#b91c1c' : '';
    }

    function showModal() {
        modal.classList.add('open');
        document.documentElement.style.overflow = 'hidden';
        setTimeout(function () {
            textarea.focus();
            try { textarea.setSelectionRange(textarea.value.length, textarea.value.length); } catch (e) {}
        }, 30);
    }

    function open(day, subject) {
        day = day || boot.day || '';

        // 没有这天的数据就先问服务端要（后台切到别的日期时会用到）
        if (!store[day]) {
            fetch('api.php?action=day&day=' + encodeURIComponent(day), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.ok) { store[day] = res.records || {}; }
                })
                .catch(function () {})
                .then(function () {
                    store[day] = store[day] || {};
                    begin(day, subject);
                });
            return;
        }
        begin(day, subject);
    }

    function begin(day, subject) {
        state.day = day;
        state.subject = '';
        if (dayInput) { dayInput.value = day; }
        selectSubject(subject || firstEmptySubject(day));
        showModal();
    }

    function close() {
        // 关掉时保留草稿，重新打开还在
        if (state.subject && state.day) {
            drafts[draftKey(state.day, state.subject)] = textarea.value;
        }
        modal.classList.remove('open');
        document.documentElement.style.overflow = '';
    }

    /* ------------------------- 保存后更新页面 ------------------------- */

    function setContentEl(key, content) {
        var el = document.getElementById('c-' + key);
        if (!el) { return; }
        if (content === '') {
            el.classList.add('is-empty');
            el.textContent = el.getAttribute('data-empty') || emptyText;
        } else {
            el.classList.remove('is-empty');
            el.textContent = content;
        }
    }

    function setTimeEl(key, updatedAt) {
        var el = document.getElementById('t-' + key);
        if (!el) { return; }
        if (!updatedAt) { el.textContent = '—'; return; }
        // 后端给的是 YYYY-MM-DD HH:MM:SS
        el.textContent = updatedAt.length >= 16 ? updatedAt.substring(11, 16) : updatedAt;
    }

    function setCardState(key, content, updatedAt) {
        var card = document.querySelector('.card[data-subject="' + key + '"]');
        if (!card) { return; }
        var st = card.querySelector('.state');
        var t  = (updatedAt && updatedAt.length >= 16) ? updatedAt.substring(11, 16) : '';
        card.classList.toggle('filled', content !== '');
        if (st) {
            st.textContent = content !== '' ? ('已填 ' + t) : '未填写';
            st.className = 'state' + (content !== '' ? ' ok' : '');
        }
    }

    function refreshSummary() {
        var summary = document.getElementById('fill-summary');
        if (!summary) { return; }
        var keys = subjectKeys();
        var map  = dayMap(boot.day);
        var filled = 0;
        keys.forEach(function (k) {
            var v = map[k];
            if (typeof v === 'string' && v !== '') { filled++; }
        });
        summary.textContent = '已填 ' + filled + '/' + keys.length + ' 科';
        summary.className = 'pill' + (filled === keys.length && keys.length > 0 ? ' ok' : '');
    }

    function applyResult(day, subject, content, updatedAt) {
        if (!store[day]) { store[day] = {}; }
        store[day][subject] = content;
        delete drafts[draftKey(day, subject)];

        setContentEl(subject, content);
        setTimeEl(subject, content === '' ? '' : updatedAt);
        setCardState(subject, content, updatedAt);

        if (day === boot.day) { refreshSummary(); }
    }

    /* ------------------------- 提交 ------------------------- */

    function submit() {
        if (state.busy) { return; }
        if (!state.day || !state.subject) { toast('请先选择科目', true); return; }
        if (textarea.value.length > maxLen) { toast('内容太长了', true); return; }

        state.busy = true;
        if (saveBtn) { saveBtn.disabled = true; }

        var body = new URLSearchParams();
        body.append('action', 'save');
        body.append('day', state.day);
        body.append('subject', state.subject);
        body.append('content', textarea.value);

        var savedDay = state.day;
        var savedSubject = state.subject;

        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.ok) {
                    throw new Error((res && res.error) ? res.error : '保存失败');
                }
                applyResult(savedDay, savedSubject, res.content, res.updated_at);
                close();
                toast(res.content === '' ? '已清空' : '已保存');
            })
            .catch(function (err) {
                toast(err && err.message ? err.message : '网络错误，保存失败', true);
            })
            .then(function () {
                state.busy = false;
                if (saveBtn) { saveBtn.disabled = false; }
            });
    }

    /* ------------------------- 事件绑定 ------------------------- */

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) { return; }

        var trigger = t.closest('[data-edit]');
        if (trigger) {
            // 整张卡片都是填写入口：但如果老师正在选文字（想复制内容），就别弹窗
            var sel = '';
            try { sel = String(window.getSelection ? window.getSelection() : ''); } catch (err) { sel = ''; }
            if (sel.length > 1) { return; }

            e.preventDefault();
            open(trigger.getAttribute('data-day'), trigger.getAttribute('data-subject'));
            return;
        }
        if (t.closest('[data-close]')) {
            e.preventDefault();
            close();
        }
    });

    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            selectSubject(c.getAttribute('data-subject'));
        });
    });

    if (textarea) {
        textarea.addEventListener('input', updateCount);
        textarea.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });
    }

    if (saveBtn) { saveBtn.addEventListener('click', submit); }

    if (delBtn) {
        delBtn.addEventListener('click', function () {
            textarea.value = '';
            updateCount();
            submit();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) {
            close();
        }
    });

    refreshSummary();
})();
