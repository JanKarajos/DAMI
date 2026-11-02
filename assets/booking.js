(function () {
  'use strict';
  const B = window.BOOKING;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const MONTHS = ['január', 'február', 'marec', 'apríl', 'máj', 'jún', 'júl', 'august', 'september', 'október', 'november', 'december'];
  const DOW = ['Po', 'Ut', 'St', 'Št', 'Pi', 'So', 'Ne'];
  const DAYS_LONG = ['nedeľa', 'pondelok', 'utorok', 'streda', 'štvrtok', 'piatok', 'sobota'];

  const state = { therapy: null, therapist: null, month: null, days: {}, date: null, slot: null };

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const pad = (n) => String(n).padStart(2, '0');
  const ym = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1);
  const ymd = (d) => ym(d) + '-' + pad(d.getDate());
  const fmtDate = (s) => { const d = new Date(s + 'T12:00:00'); return DAYS_LONG[d.getDay()] + ' ' + d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear(); };

  function go(step) {
    $$('.step-panel').forEach((p) => p.classList.toggle('hidden', +p.dataset.panel !== step));
    $$('.steps li').forEach((li) => {
      const n = +li.dataset.step;
      li.classList.toggle('active', n === step);
      li.classList.toggle('done', n < step);
    });
    $('#booking').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  $$('[data-back]').forEach((b) => b.addEventListener('click', () => go(+b.dataset.back)));

  /* ---------- Krok 1: terapie ---------- */
  function renderTherapies() {
    const list = $('#therapy-list');
    if (!B.therapies.length) { list.innerHTML = '<p class="muted">Momentálne nie sú dostupné žiadne terapie.</p>'; return; }
    list.innerHTML = B.therapies.map((t) => `
      <button type="button" class="choice" data-id="${t.id}">
        <strong>${esc(t.name)}</strong>
        <span class="small muted">⏱ ${t.duration} min${t.price ? ' · ' + esc(t.price) : ''} · ${t.therapists.length} ${t.therapists.length === 1 ? 'terapeut' : (t.therapists.length >= 2 && t.therapists.length <= 4 ? 'terapeuti' : 'terapeutov')}</span>
        <span class="small">${esc(t.description.slice(0, 180))}${t.description.length > 180 ? '…' : ''}</span>
      </button>`).join('');
    $$('.choice', list).forEach((el) => el.addEventListener('click', () => selectTherapy(+el.dataset.id)));
  }

  function selectTherapy(id) {
    state.therapy = B.therapies.find((t) => t.id === id);
    if (!state.therapy) return;
    const list = $('#therapist-list');
    const people = state.therapy.therapists.map((tid) => B.therapists[tid]);
    $('#therapist-intro').textContent = 'Terapiu „' + state.therapy.name + '“ vykonávajú:';
    list.innerHTML = people.length ? people.map((p) => `
      <button type="button" class="choice choice-person" data-id="${p.id}">
        ${p.photo ? `<span class="avatar has-photo"><img src="${esc(p.photo)}" alt=""></span>` : `<span class="avatar">${esc(p.initials)}</span>`}
        <span><strong>${esc(p.name)}</strong><br>
        <span class="small muted">${esc(p.position)}</span><br>
        <span class="small">${esc(p.description.slice(0, 110))}${p.description.length > 110 ? '…' : ''}</span></span>
      </button>`).join('') : '<p class="muted">Pre túto terapiu zatiaľ nie je priradený žiadny terapeut. Kontaktujte nás prosím e-mailom.</p>';
    $$('.choice', list).forEach((el) => el.addEventListener('click', () => selectTherapist(+el.dataset.id)));
    go(2);
    if (people.length === 1) { /* rovno vybrať jediného */ selectTherapist(people[0].id); }
  }

  /* ---------- Krok 3: kalendár ---------- */
  function summaryHtml(withTime) {
    let h = `<div>Terapia: <strong>${esc(state.therapy.name)}</strong> (${state.therapy.duration} min)</div>
             <div>Terapeut: <strong>${esc(state.therapist.name)}</strong></div>`;
    if (withTime && state.slot) h += `<div>Termín: <strong>${fmtDate(state.date)}, ${state.slot.start} – ${state.slot.end}</strong></div>`;
    return h;
  }

  async function selectTherapist(id) {
    state.therapist = B.therapists[id];
    state.date = null; state.slot = null;
    $('#summary-3').innerHTML = summaryHtml(false);
    resetTimes();
    go(3);
    const now = new Date();
    state.month = new Date(now.getFullYear(), now.getMonth(), 1);
    const res = await loadMonth();
    // ak je aktuálny mesiac prázdny, skočíme na prvý mesiac s voľným termínom
    if (res && res.firstAvailable && !Object.keys(state.days).length) {
      const f = new Date(res.firstAvailable + 'T12:00:00');
      state.month = new Date(f.getFullYear(), f.getMonth(), 1);
      await loadMonth();
    }
  }

  async function loadMonth() {
    $('#cal-title').textContent = MONTHS[state.month.getMonth()] + ' ' + state.month.getFullYear();
    $('#cal-grid').innerHTML = '<p class="loading" style="grid-column:1/-1">Načítavam voľné termíny…</p>';
    try {
      const r = await fetch(`${B.api}?action=slots&therapist=${state.therapist.id}&therapy=${state.therapy.id}&month=${ym(state.month)}`);
      const json = await r.json();
      state.days = Array.isArray(json.days) ? {} : (json.days || {});
      renderCalendar(json.firstAvailable);
      return json;
    } catch (e) {
      $('#cal-grid').innerHTML = '<p class="alert alert-error" style="grid-column:1/-1">Nepodarilo sa načítať termíny. Skúste to prosím znova.</p>';
      return null;
    }
  }

  function renderCalendar(firstAvailable) {
    const grid = $('#cal-grid');
    const y = state.month.getFullYear(), m = state.month.getMonth();
    const first = new Date(y, m, 1);
    const offset = (first.getDay() + 6) % 7;
    const daysIn = new Date(y, m + 1, 0).getDate();
    const today = ymd(new Date());
    let html = DOW.map((d) => `<div class="cal-dow">${d}</div>`).join('');
    for (let i = 0; i < offset; i++) html += '<span></span>';
    for (let d = 1; d <= daysIn; d++) {
      const key = y + '-' + pad(m + 1) + '-' + pad(d);
      const has = !!state.days[key];
      const cls = ['cal-day', has ? 'has-slots' : '', key === today ? 'today' : '', key === state.date ? 'selected' : ''].join(' ');
      html += has
        ? `<button type="button" class="${cls}" data-date="${key}" title="${state.days[key].length} voľných termínov">${d}</button>`
        : `<span class="${cls}">${d}</span>`;
    }
    grid.innerHTML = html;
    $$('.cal-day.has-slots', grid).forEach((b) => b.addEventListener('click', () => selectDate(b.dataset.date)));

    const now = new Date();
    $('#cal-prev').disabled = y < now.getFullYear() || (y === now.getFullYear() && m <= now.getMonth());
    const max = new Date(); max.setDate(max.getDate() + B.horizonDays);
    $('#cal-next').disabled = new Date(y, m + 1, 1) > max;

    if (!Object.keys(state.days).length) {
      $('#times-title').textContent = 'V tomto mesiaci nie sú voľné termíny';
      $('#times').innerHTML = '';
      $('#times-empty').innerHTML = firstAvailable
        ? `Najbližší voľný termín: <button type="button" class="btn-link" id="jump">${fmtDate(firstAvailable)}</button>`
        : 'Terapeut momentálne nemá vypísané žiadne voľné termíny. Skúste prosím iného terapeuta alebo nás kontaktujte.';
      const j = $('#jump');
      if (j) j.addEventListener('click', async () => {
        const f = new Date(firstAvailable + 'T12:00:00');
        state.month = new Date(f.getFullYear(), f.getMonth(), 1);
        await loadMonth();
        selectDate(firstAvailable);
      });
    } else if (!state.date || !state.days[state.date]) {
      resetTimes();
    }
  }

  function resetTimes() {
    $('#times-title').textContent = 'Vyberte deň v kalendári';
    $('#times').innerHTML = '';
    $('#times-empty').textContent = 'Dni s voľnými termínmi sú zvýraznené.';
  }

  function selectDate(date) {
    state.date = date;
    $$('.cal-day').forEach((el) => el.classList.toggle('selected', el.dataset.date === date));
    const slots = state.days[date] || [];
    $('#times-title').textContent = 'Voľné časy – ' + fmtDate(date);
    $('#times-empty').textContent = '';
    if (window.innerWidth < 860) $('#times-title').scrollIntoView({ behavior: 'smooth', block: 'center' });
    $('#times').innerHTML = slots.map((s) => `<button type="button" class="time-btn" data-id="${s.id}">${s.start}</button>`).join('');
    $$('.time-btn').forEach((b) => b.addEventListener('click', () => {
      state.slot = slots.find((s) => s.id === +b.dataset.id);
      $('#summary-4').innerHTML = summaryHtml(true);
      $('#form-error').classList.add('hidden');
      go(4);
      $('#client_name').focus({ preventScroll: true });
    }));
  }

  $('#cal-prev').addEventListener('click', () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() - 1, 1); loadMonth(); });
  $('#cal-next').addEventListener('click', () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() + 1, 1); loadMonth(); });

  /* ---------- Krok 4: odoslanie ---------- */
  $('#booking-form').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const form = ev.target;
    const err = $('#form-error');
    err.classList.add('hidden');
    if (!form.checkValidity()) {
      err.textContent = 'Vyplňte prosím všetky povinné polia a potvrďte súhlas so spracovaním údajov.';
      err.classList.remove('hidden');
      form.reportValidity();
      return;
    }
    const fd = new FormData(form);
    fd.append('_csrf', B.csrf);
    fd.append('slot_id', state.slot.id);
    fd.append('therapy_id', state.therapy.id);
    const btn = $('#submit-btn');
    btn.disabled = true; btn.textContent = 'Odosielam…';
    try {
      const r = await fetch(B.api + '?action=book', { method: 'POST', body: fd });
      const json = await r.json();
      if (json.redirect) { window.location.href = json.redirect; return; }
      err.textContent = json.error || 'Rezerváciu sa nepodarilo odoslať.';
      err.classList.remove('hidden');
      if (json.slotTaken) {
        err.innerHTML += ' <button type="button" class="btn-link" id="back-cal">Vybrať iný čas</button>';
        $('#back-cal').addEventListener('click', async () => { state.slot = null; await loadMonth(); if (state.days[state.date]) selectDate(state.date); go(3); });
      }
    } catch (e) {
      err.textContent = 'Chyba spojenia. Skúste to prosím znova.';
      err.classList.remove('hidden');
    } finally {
      btn.disabled = false; btn.textContent = 'Záväzne rezervovať';
    }
  });

  renderTherapies();
  go(1);
  window.scrollTo(0, 0);
  if (B.preselect && B.therapies.some((t) => t.id === B.preselect)) selectTherapy(B.preselect);
})();
