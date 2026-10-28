/* NodeThemes AI Chat - admin: inbox and settings helpers. */
(function () {
	'use strict';
	var A = window.ntacAdmin;
	if (!A) { return; }
	var T = A.i18n;

	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined && text !== null) { e.textContent = text; } return e; }

	/* ---------- confirm buttons ---------- */
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-confirm]') : null;
		if (b && !window.confirm(b.getAttribute('data-confirm'))) { e.preventDefault(); }
	});

	/* ---------- settings: provider cards, test, models ---------- */
	document.querySelectorAll('.ntac-prov input[type=radio]').forEach(function (r) {
		r.addEventListener('change', function () {
			document.querySelectorAll('.ntac-prov').forEach(function (p) { p.classList.toggle('on', p.getAttribute('data-prov') === r.value); });
		});
	});
	function ajax(action, data) {
		var f = new FormData();
		f.append('action', action); f.append('nonce', A.ajaxNonce);
		Object.keys(data).forEach(function (k) { f.append(k, data[k]); });
		return fetch(A.ajax, { method: 'POST', credentials: 'same-origin', body: f }).then(function (r) { return r.json(); });
	}
	document.querySelectorAll('.ntac-prov').forEach(function (card) {
		var prov = card.getAttribute('data-prov');
		var key = card.querySelector('.ntac-key'), model = card.querySelector('.ntac-model'), out = card.querySelector('.ntac-test-out');
		var test = card.querySelector('.ntac-test'), load = card.querySelector('.ntac-load-models');
		function say(ok, msg) { out.className = 'ntac-test-out ' + (ok ? 'ok' : 'err'); out.textContent = msg; }
		if (test) {
			test.addEventListener('click', function () {
				test.disabled = true; out.className = 'ntac-test-out'; out.textContent = T.testing;
				ajax('ntac_test_ai', { provider: prov, key: key ? key.value : '', model: model.value }).then(function (j) {
					test.disabled = false; say(j.success, (j.data && j.data.message) || 'Error');
				}).catch(function () { test.disabled = false; say(false, 'Error'); });
			});
		}
		if (load) {
			load.addEventListener('click', function () {
				load.disabled = true; out.className = 'ntac-test-out'; out.textContent = T.loading;
				ajax('ntac_list_models', { provider: prov, key: key ? key.value : '' }).then(function (j) {
					load.disabled = false;
					if (!j.success) { say(false, (j.data && j.data.message) || 'Error'); return; }
					var dl = document.getElementById('ntac-models-' + prov); dl.innerHTML = '';
					j.data.models.forEach(function (m) { var o = el('option'); o.value = m; dl.appendChild(o); });
					say(true, j.data.models.length + ' models');
				}).catch(function () { load.disabled = false; say(false, 'Error'); });
			});
		}
	});

	/* ---------- inbox ---------- */
	var box = document.getElementById('ntac-inbox');
	if (!box) { return; }

	function api(path, method, body) {
		var o = { method: method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': A.nonce } };
		if (body) { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(body); }
		return fetch(A.rest + path, o).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { throw new Error(j.message || ('HTTP ' + r.status)); } return j; });
		});
	}

	var state = { filter: 'all', search: '', current: Number(box.getAttribute('data-open')) || 0, lastId: 0, conv: null };
	box.innerHTML = '';
	var left = el('div', 'ntac-in-left'), right = el('div', 'ntac-in-right');
	var filters = el('div', 'ntac-in-filters');
	[['all', T.all], ['needs', T.filterNeed], ['unread', T.unread]].forEach(function (f) {
		var b = el('button', 'ntac-chipbtn' + (f[0] === 'all' ? ' on' : ''), f[1]); b.type = 'button'; b.setAttribute('data-f', f[0]);
		b.addEventListener('click', function () {
			state.filter = f[0];
			filters.querySelectorAll('.ntac-chipbtn').forEach(function (x) { x.classList.toggle('on', x === b); });
			loadList();
		});
		filters.appendChild(b);
	});
	var search = el('input', 'ntac-in-search'); search.type = 'search'; search.placeholder = T.search; search.setAttribute('aria-label', T.search);
	var st; search.addEventListener('input', function () { clearTimeout(st); st = setTimeout(function () { state.search = search.value; loadList(); }, 350); });
	var list = el('ul', 'ntac-in-list'); list.setAttribute('role', 'list');
	left.appendChild(filters); left.appendChild(search); left.appendChild(list);
	right.appendChild(el('p', 'ntac-in-pick', T.pick));
	box.appendChild(left); box.appendChild(right);

	function loadList() {
		var q = 'conversations?filter=' + encodeURIComponent(state.filter) + '&search=' + encodeURIComponent(state.search);
		return api(q).then(function (j) {
			list.innerHTML = '';
			if (!j.conversations.length) { list.appendChild(el('li', 'ntac-in-empty', T.empty)); }
			j.conversations.forEach(function (c) {
				var li = el('li', 'ntac-in-item' + (c.id === state.current ? ' on' : '') + (c.unread ? ' unread' : ''));
				var b = el('button'); b.type = 'button';
				var top = el('span', 'ntac-in-top');
				top.appendChild(el('strong', '', c.name));
				top.appendChild(el('span', 'ntac-in-ago', c.ago));
				var mid = el('span', 'ntac-in-mid');
				mid.appendChild(el('span', 'ntac-ch ntac-ch-' + c.channel, c.channelLabel));
				if (c.needs) { mid.appendChild(el('span', 'ntac-flag', T.needs)); }
				if (!c.ai) { mid.appendChild(el('span', 'ntac-flag off', 'AI off')); }
				if (c.unread) { mid.appendChild(el('span', 'ntac-unread', String(c.unread))); }
				var pv = el('span', 'ntac-in-pv', c.preview); pv.setAttribute('dir', 'auto');
				b.appendChild(top); b.appendChild(mid); b.appendChild(pv);
				b.addEventListener('click', function () { openConv(c.id); });
				li.appendChild(b); list.appendChild(li);
			});
		}).catch(function (e) { list.innerHTML = ''; list.appendChild(el('li', 'ntac-in-empty', e.message)); });
	}

	var thread, msgsEl, headEl, form, ta, sendBtn, errEl;
	function buildThread() {
		right.innerHTML = '';
		headEl = el('div', 'ntac-th-head');
		msgsEl = el('div', 'ntac-th-msgs'); msgsEl.setAttribute('role', 'log'); msgsEl.setAttribute('aria-live', 'polite');
		errEl = el('div', 'ntac-flash err'); errEl.hidden = true;
		form = el('form', 'ntac-th-form');
		ta = el('textarea'); ta.rows = 3; ta.placeholder = T.reply; ta.setAttribute('aria-label', T.reply); ta.setAttribute('dir', 'auto');
		sendBtn = el('button', 'ntac-btn primary', T.send); sendBtn.type = 'submit';
		form.appendChild(ta); form.appendChild(sendBtn);
		right.appendChild(headEl); right.appendChild(msgsEl); right.appendChild(errEl); right.appendChild(form);
		form.addEventListener('submit', function (e) { e.preventDefault(); reply(); });
		ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); reply(); } });
	}
	function renderHead(c) {
		headEl.innerHTML = '';
		var back = el('button', 'ntac-btn sm ntac-back', '← ' + T.back); back.type = 'button';
		back.addEventListener('click', function () { box.classList.remove('show-thread'); });
		var info = el('div', 'ntac-th-info');
		info.appendChild(el('strong', '', c.name));
		var sub = el('span', 'ntac-help', c.channelLabel + (c.contact ? ' · ' + c.contact : '')); info.appendChild(sub);
		var acts = el('div', 'ntac-th-acts');
		var ai = el('button', 'ntac-btn sm ' + (c.ai ? 'ok' : 'off'), c.ai ? T.aiOn : T.aiOff); ai.type = 'button';
		ai.addEventListener('click', function () { api('conversation/' + c.id + '/ai', 'POST', { on: c.ai ? 0 : 1 }).then(function (j) { state.conv = j.conversation; renderHead(j.conversation); loadList(); }).catch(showErr); });
		acts.appendChild(ai);
		if (c.needs) {
			var done = el('button', 'ntac-btn sm', T.done); done.type = 'button';
			done.addEventListener('click', function () { api('conversation/' + c.id + '/done', 'POST', {}).then(function (j) { state.conv = j.conversation; renderHead(j.conversation); loadList(); }).catch(showErr); });
			acts.appendChild(done);
		}
		var del = el('button', 'ntac-btn sm danger', T.del); del.type = 'button';
		del.addEventListener('click', function () {
			if (!window.confirm(T.delAsk)) { return; }
			api('conversation/' + c.id, 'DELETE').then(function () { state.current = 0; right.innerHTML = ''; right.appendChild(el('p', 'ntac-in-pick', T.pick)); box.classList.remove('show-thread'); loadList(); }).catch(showErr);
		});
		acts.appendChild(del);
		headEl.appendChild(back); headEl.appendChild(info); headEl.appendChild(acts);
		if (c.paused) {
			var d = new Date(c.paused * 1000);
			headEl.appendChild(el('p', 'ntac-help ntac-paused', T.aiPaused + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })));
		}
		form.hidden = !c.canSend;
		if (!c.canSend) { headEl.appendChild(el('p', 'ntac-flash warn', T.noChannel)); }
	}
	function addMsg(m) {
		var row = el('div', 'ntac-m ntac-m-' + m.role + (m.status === 'failed' ? ' failed' : ''));
		var who = { customer: T.customer, ai: T.ai, admin: T.admin, system: T.system }[m.role] || m.role;
		var meta = el('div', 'ntac-m-meta', who + ' · ' + m.time + (m.via === 'app' ? ' · ' + T.viaApp : (m.via === 'telegram' ? ' · ' + T.viaTg : '')));
		var body = el('div', 'ntac-m-body', m.text); body.setAttribute('dir', 'auto');
		row.appendChild(meta); row.appendChild(body);
		if (m.page && m.page !== state.lastPage) { state.lastPage = m.page; var p = el('a', 'ntac-m-page', T.page + ': ' + m.page.replace(/^https?:\/\/[^/]+/, '')); p.href = m.page; p.target = '_blank'; p.rel = 'noopener'; row.appendChild(p); }
		if (m.status === 'failed') { row.appendChild(el('div', 'ntac-m-err', T.failed + (m.error ? ': ' + m.error : ''))); }
		if (m.role === 'admin' && m.status !== 'failed') {
			var lb = el('button', 'ntac-linkbtn', A.pro ? T.learn : T.learnPro); lb.type = 'button';
			lb.addEventListener('click', function () {
				if (!A.pro) { window.open(A.proUrl, '_blank', 'noopener'); return; }
				lb.disabled = true;
				api('conversation/' + state.current + '/learn', 'POST', { msg: m.id }).then(function (j) { lb.textContent = j.message; }).catch(function (e) { lb.disabled = false; showErr(e); });
			});
			row.appendChild(lb);
		}
		msgsEl.appendChild(row);
	}
	function showErr(e) { errEl.textContent = e.message || String(e); errEl.hidden = false; }
	function loadThread(scroll) {
		if (!state.current) { return Promise.resolve(); }
		var id = state.current;
		return api('conversation/' + id + '?after=' + state.lastId).then(function (j) {
			if (id !== state.current) { return; }
			state.conv = j.conversation; renderHead(j.conversation);
			var near = msgsEl.scrollHeight - msgsEl.scrollTop - msgsEl.clientHeight < 80;
			j.messages.forEach(function (m) { if (m.id > state.lastId) { addMsg(m); state.lastId = m.id; } });
			if (scroll || near) { msgsEl.scrollTop = msgsEl.scrollHeight; }
		});
	}
	function openConv(id) {
		state.current = id; state.lastId = 0; state.lastPage = '';
		buildThread(); box.classList.add('show-thread');
		list.querySelectorAll('.ntac-in-item').forEach(function (li) { li.classList.remove('on'); });
		loadThread(true).then(loadList).catch(showErr);
		if (window.history && window.history.replaceState) {
			var u = new URL(window.location.href); u.searchParams.set('conv', id); window.history.replaceState(null, '', u.toString());
		}
		ta.focus();
	}
	function reply() {
		var text = ta.value.trim();
		if (!text) { return; }
		sendBtn.disabled = true; errEl.hidden = true;
		api('conversation/' + state.current + '/reply', 'POST', { text: text }).then(function () {
			ta.value = ''; sendBtn.disabled = false; return loadThread(true);
		}).catch(function (e) { sendBtn.disabled = false; showErr(e); loadThread(true); });
	}

	loadList();
	if (state.current) { openConv(state.current); }
	setInterval(function () {
		if (document.hidden) { return; }
		loadList();
		if (state.current) { loadThread(false).catch(function () {}); }
	}, 5000);
})();
