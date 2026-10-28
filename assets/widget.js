/* NodeThemes AI Chat - website chat bubble. No dependencies. */
(function () {
	'use strict';
	var C = window.ntacChat;
	if (!C || document.getElementById('ntac-root')) { return; }
	var T = C.i18n || {};

	/* ---------- storage (may be blocked: private mode, previews) ---------- */
	function sget(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
	function sset(k, v) { try { window.localStorage.setItem(k, v); } catch (e) {} }
	function cookie(k, v) {
		if (v === undefined) {
			var m = document.cookie.match(new RegExp('(?:^|; )' + k + '=([^;]*)'));
			return m ? decodeURIComponent(m[1]) : null;
		}
		document.cookie = k + '=' + encodeURIComponent(v) + ';path=/;max-age=31536000;SameSite=Lax';
	}
	function newToken() {
		var a = new Uint8Array(16), s = '';
		if (window.crypto && window.crypto.getRandomValues) { window.crypto.getRandomValues(a); }
		else { for (var j = 0; j < 16; j++) { a[j] = Math.floor(Math.random() * 256); } }
		for (var i = 0; i < a.length; i++) { s += ('0' + a[i].toString(16)).slice(-2); }
		return s;
	}
	var token = sget('ntac_token') || cookie('ntac_t');
	if (!token || !/^[a-f0-9]{32,64}$/.test(token)) { token = newToken(); }
	sset('ntac_token', token); cookie('ntac_t', token);
	var started = sget('ntac_started') === '1' || cookie('ntac_s') === '1';

	/* ---------- DOM ---------- */
	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text) { e.textContent = text; } return e; }
	var root = el('div'); root.id = 'ntac-root'; root.className = 'ntac-' + (C.position === 'left' ? 'left' : 'right');
	var btn = el('button', 'ntac-launch'); btn.type = 'button'; btn.setAttribute('aria-label', T.open); btn.setAttribute('aria-expanded', 'false');
	btn.innerHTML = '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M12 3C6.5 3 2 6.9 2 11.6c0 2.4 1.2 4.6 3.1 6.1L4.4 21l3.7-1.9c1.2.4 2.5.6 3.9.6 5.5 0 10-3.9 10-8.6S17.5 3 12 3z"/></svg>';
	var badge = el('span', 'ntac-badge'); badge.hidden = true; btn.appendChild(badge);
	var panel = el('div', 'ntac-panel'); panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', C.title); panel.hidden = true;
	var head = el('div', 'ntac-head');
	var ht = el('div', 'ntac-ht'); ht.setAttribute('dir', 'auto'); ht.appendChild(el('strong', '', C.title)); if (C.sub) { ht.appendChild(el('span', '', C.sub)); }
	var x = el('button', 'ntac-x'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.innerHTML = '&times;';
	head.appendChild(ht); head.appendChild(x);
	var list = el('div', 'ntac-list'); list.setAttribute('role', 'log'); list.setAttribute('aria-live', 'polite'); list.setAttribute('aria-label', T.messages); list.tabIndex = 0;
	if (C.notice) { var nt = el('p', 'ntac-notice', C.notice); nt.setAttribute('dir', 'auto'); list.appendChild(nt); }
	var typing = el('div', 'ntac-msg ntac-bot ntac-typing'); typing.hidden = true;
	typing.innerHTML = '<span></span><span></span><span></span>'; typing.setAttribute('aria-label', T.typing);
	list.appendChild(typing);
	var form = el('form', 'ntac-form');
	var input = el('textarea', 'ntac-in'); input.rows = 1; input.setAttribute('dir', 'auto'); input.placeholder = T.placeholder; input.setAttribute('aria-label', T.placeholder); input.maxLength = 1500;
	var send = el('button', 'ntac-send'); send.type = 'submit'; send.setAttribute('aria-label', T.send);
	send.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.4 10l12.6 2-12.6 2z"/></svg>';
	form.appendChild(input); form.appendChild(send);
	var err = el('div', 'ntac-err'); err.hidden = true;

	var contact = null, cname = null, cmail = null;
	if (C.contact && !started) {
		contact = el('form', 'ntac-contact');
		cname = el('input'); cname.type = 'text'; cname.placeholder = T.yourName; cname.setAttribute('aria-label', T.yourName); cname.maxLength = 80;
		cmail = el('input'); cmail.type = 'email'; cmail.placeholder = T.yourEmail; cmail.setAttribute('aria-label', T.yourEmail); cmail.maxLength = 120;
		var go = el('button', 'ntac-go', T.start); go.type = 'submit';
		contact.appendChild(cname); contact.appendChild(cmail); contact.appendChild(go);
	}

	panel.appendChild(head); panel.appendChild(list); panel.appendChild(err);
	if (contact) { panel.appendChild(contact); form.hidden = true; }
	panel.appendChild(form);
	root.appendChild(panel); root.appendChild(btn);
	document.body.appendChild(root);

	/* ---------- messages ---------- */
	var seen = {}, lastId = 0, waiting = false, unread = 0, open = false, timer = null, welcomed = false;

	function linkify(node, text) {
		var re = /(https?:\/\/[^\s<>"']+[^\s<>"'.,;:!?)\]])/g, i = 0, m;
		while ((m = re.exec(text)) !== null) {
			if (m.index > i) { node.appendChild(document.createTextNode(text.slice(i, m.index))); }
			var a = el('a', '', m[1]); a.href = m[1]; a.target = '_blank'; a.rel = 'noopener nofollow';
			node.appendChild(a); i = m.index + m[1].length;
		}
		if (i < text.length) { node.appendChild(document.createTextNode(text.slice(i))); }
	}
	function add(role, text, id) {
		if (id && seen[id]) { return; }
		if (id) { seen[id] = 1; if (id > lastId) { lastId = id; } }
		var m = el('div', 'ntac-msg ntac-' + role); m.setAttribute('dir', 'auto');
		if (role === 'agent') { m.appendChild(el('small', 'ntac-who', T.agent)); }
		var b = el('div', 'ntac-txt'); linkify(b, text); m.appendChild(b);
		list.insertBefore(m, typing);
		list.scrollTop = list.scrollHeight;
		if (role !== 'me' && !open) { unread++; badge.textContent = unread > 9 ? '9+' : String(unread); badge.hidden = false; }
		return m;
	}
	function welcome() { if (!welcomed && C.welcome) { welcomed = true; add('bot', C.welcome); unread = 0; badge.hidden = true; } }

	function api(path, opts) {
		return window.fetch(C.api + path, opts).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { j.ok = false; } return j; });
		});
	}
	function poll() {
		clearTimeout(timer);
		if (!started) { return; }
		if (document.hidden) { schedule(); return; }
		api('poll?token=' + token + '&after=' + lastId, { method: 'GET', credentials: 'omit', cache: 'no-store' }).then(function (j) {
			if (j && j.ok) {
				var botAnswered = false;
				(j.messages || []).forEach(function (m) { if (!seen[m.id] && m.role !== 'me') { botAnswered = true; } add(m.role, m.text, m.id); });
				if (botAnswered) { waiting = false; }
				typing.hidden = !(j.typing || waiting);
				list.scrollTop = list.scrollHeight;
			}
		}).catch(function () {}).then(schedule);
	}
	function schedule() {
		clearTimeout(timer);
		var ms = open ? (waiting ? 2500 : 6000) : 30000;
		timer = setTimeout(poll, ms);
	}

	function submit(text) {
		text = (text || '').trim();
		if (!text) { return; }
		err.hidden = true;
		var mine = add('me', text);
		input.value = ''; input.style.height = '';
		var body = { token: token, text: text, page: window.location.href };
		if (cname) { body.name = cname.value; body.email = cmail.value; }
		send.disabled = true;
		api('send', { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(function (j) {
			send.disabled = false;
			if (!j || !j.ok) { mine.classList.add('ntac-failed'); err.textContent = (j && j.message) || T.error; err.hidden = false; input.value = text; return; }
			seen[j.id] = 1; if (j.id > lastId) { lastId = j.id; }
			started = true; sset('ntac_started', '1'); cookie('ntac_s', '1');
			waiting = true; typing.hidden = false; list.scrollTop = list.scrollHeight;
			setTimeout(poll, 1200);
		}).catch(function () { send.disabled = false; mine.classList.add('ntac-failed'); err.textContent = T.error; err.hidden = false; input.value = text; });
	}

	/* ---------- open / close ---------- */
	function setOpen(v) {
		open = v; panel.hidden = !v; root.classList.toggle('ntac-open', v); btn.setAttribute('aria-expanded', v ? 'true' : 'false');
		if (v) {
			welcome(); unread = 0; badge.hidden = true;
			(contact && !started ? cname : input).focus();
			poll();
		} else { btn.focus(); schedule(); }
		sset('ntac_open', v ? '1' : '0');
	}
	btn.addEventListener('click', function () { setOpen(!open); });
	x.addEventListener('click', function () { setOpen(false); });
	root.addEventListener('keydown', function (e) { if (e.key === 'Escape' && open) { e.preventDefault(); setOpen(false); } });
	form.addEventListener('submit', function (e) { e.preventDefault(); submit(input.value); });
	input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); submit(input.value); } });
	input.addEventListener('input', function () { input.style.height = ''; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; });
	if (contact) {
		contact.addEventListener('submit', function (e) { e.preventDefault(); contact.hidden = true; form.hidden = false; input.focus(); });
	}
	document.addEventListener('visibilitychange', function () { if (!document.hidden && started) { poll(); } });

	if (started) { poll(); if (sget('ntac_open') === '1' && window.innerWidth > 600) { setOpen(true); } }
})();
