/* ==========================================================================
   日清表 · 前端交互（动态科目版）
   · 内容唯一来源：window.RQ_DATA.records[科目]，服务端渲染在页面里
   · 客户界面与后台共用本文件，靠 id 约定定位元素：
       #c-<科目>   内容元素
       #t-<科目>   最后更新时间（只有后台有）
       .card[data-subject]  卡片（只有客户界面有）
   · 全程不刷新页面；不自动刷新
   ========================================================================== */

/* ------------------------- 字号调节（字体 － ＋） -------------------------
   教室大屏 / 投影仪看不清时，老师自己放大缩小整个界面：
   改根元素的 --fs-scale，所有 rem 尺寸跟着变大；点一次记在这台设备上。 */

(function () {
    var root = document.documentElement;
    var host = document.getElementById('fs-switch');
    if (!host) { return; }

    var STEPS = [0.85, 1, 1.15, 1.3, 1.45, 1.6];
    var KEY = 'rq.fs';
    var btns = Array.prototype.slice.call(host.querySelectorAll('[data-fs]'));

    function current() {
        var v = parseFloat(root.style.getPropertyValue('--fs-scale'));
        return (isFinite(v) && v > 0) ? v : 1;
    }

    function nearestIndex(v) {
        var best = 0, diff = Infinity;
        for (var i = 0; i < STEPS.length; i++) {
            var d = Math.abs(STEPS[i] - v);
            if (d < diff) { diff = d; best = i; }
        }
        return best;
    }

    function paint() {
        var i = nearestIndex(current());
        btns.forEach(function (b) {
            var dir = parseInt(b.getAttribute('data-fs'), 10);
            var atLimit = (dir < 0 && i === 0) || (dir > 0 && i === STEPS.length - 1);
            b.classList.toggle('off', atLimit);
            b.setAttribute('aria-disabled', atLimit ? 'true' : 'false');
        });
    }

    function apply(v) {
        if (v === 1) { root.style.removeProperty('--fs-scale'); }
        else { root.style.setProperty('--fs-scale', String(v)); }

        try {
            if (v === 1) { localStorage.removeItem(KEY); }
            else { localStorage.setItem(KEY, String(v)); }
        } catch (err) { /* 隐私模式下写不了也不影响本次 */ }

        paint();
    }

    btns.forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.preventDefault();
            var dir = parseInt(b.getAttribute('data-fs'), 10) > 0 ? 1 : -1;
            var i = nearestIndex(current()) + dir;
            if (i < 0 || i >= STEPS.length) { return; }
            apply(STEPS[i]);
        });
    });

    paint();
})();

/* ------------------------- 布局切换（自动 / 三列 / 单列） -------------------------
   教室大屏有的宽有的窄，自动排版不一定合心意，所以留一个手动开关：
   点一次就记在这台设备的浏览器里，以后打开还是这个布局。 */

(function () {
    var root = document.documentElement;
    var host = document.getElementById('layout-switch');
    if (!host) { return; }

    var btns = Array.prototype.slice.call(host.querySelectorAll('[data-layout]'));
    var KEY = 'rq.layout';

    function paint() {
        var cur = root.getAttribute('data-layout') || 'auto';
        btns.forEach(function (b) {
            var isOn = b.getAttribute('data-layout') === cur;
            b.classList.toggle('on', isOn);
            b.setAttribute('aria-pressed', isOn ? 'true' : 'false');
        });
    }

    btns.forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.preventDefault();
            var v = b.getAttribute('data-layout');
            if (v === 'auto') {
                root.removeAttribute('data-layout');
            } else {
                root.setAttribute('data-layout', v);
            }
            try {
                if (v === 'auto') { localStorage.removeItem(KEY); }
                else { localStorage.setItem(KEY, v); }
            } catch (err) { /* 隐私模式下写不了也不影响本次 */ }
            paint();
            // 布局变了，回到顶部看效果
            try { window.scrollTo(0, 0); } catch (err) { /* ignore */ }
        });
    });

    paint();
})();

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
    var warnEl    = document.getElementById('f-warn');
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

    /* ------------------------- 清空的三秒二次确认 -------------------------
       清空不可恢复，所以第一次点只「上膛」，按钮进入 3 秒倒计时（期间点不动），
       倒计时结束才允许真正确认；中途打字、换科目、关闭弹窗都自动撤销。 */
    var DEL_SECONDS = 3;
    var delTimer = null;
    var delLeft = 0;
    var delArmed = false;

    function delReset() {
        if (delTimer) { clearInterval(delTimer); delTimer = null; }
        delLeft = 0;
        delArmed = false;
        if (delBtn) {
            delBtn.textContent = '清空';
            delBtn.classList.remove('armed');
            delBtn.disabled = false;
        }
        if (warnEl) {
            warnEl.hidden = true;
            warnEl.textContent = '';
        }
    }

    function delArm() {
        if (delTimer) { clearInterval(delTimer); }
        delArmed = false;
        delLeft = DEL_SECONDS;
        if (delBtn) {
            delBtn.textContent = '确认清空（' + delLeft + '）';
            delBtn.classList.remove('armed');
            delBtn.disabled = true;      // 倒计时期间点不动，避免连点误清空
        }
        if (warnEl) {
            warnEl.hidden = false;
            warnEl.textContent = '清空后不可恢复，' + delLeft + ' 秒后可确认。';
        }
        delTimer = setInterval(function () {
            delLeft--;
            if (delLeft > 0) {
                if (delBtn) { delBtn.textContent = '确认清空（' + delLeft + '）'; }
                if (warnEl) {
                    warnEl.textContent = '清空后不可恢复，' + delLeft + ' 秒后可确认。';
                }
                return;
            }
            clearInterval(delTimer);
            delTimer = null;
            delArmed = true;
            if (delBtn) {
                delBtn.textContent = '确认清空';
                delBtn.classList.add('armed');
                delBtn.disabled = false;
            }
            if (warnEl) {
                warnEl.textContent = '再点一次「确认清空」就真的删除了，不可恢复。';
            }
        }, 1000);
    }

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
        delReset();   // 换了科目，之前那次的清空确认作废
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
        delReset();
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
        textarea.addEventListener('input', function () {
            // 动笔了：说明是改内容不是要清空，撤掉清空确认
            if (delArmed || delTimer) { delReset(); }
            updateCount();
        });
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
            if (state.busy) { return; }

            if (delArmed) {
                // 第二下：真的清空
                delReset();
                textarea.value = '';
                updateCount();
                submit();
                return;
            }
            // 第一下：只上膛，3 秒后才能确认
            delArm();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) {
            close();
        }
    });

    refreshSummary();
})();
