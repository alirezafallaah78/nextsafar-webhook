/**
 * NextSafar Dashboard
 * 📅 Calendar: single-system (Shamsi ⇄ Gregorian), custom header/footer
 * 🔍 Search Console chart (unchanged)
 */
(function ($) {
  "use strict";

  const AJAX_URL = nextsafarAdmin.ajaxUrl;
  const NONCE = nextsafarAdmin.dashboardNonce;

  async function ensureChartJs() {
    if (typeof Chart !== "undefined") return true;
    return new Promise((resolve, reject) => {
      const script = document.createElement("script");
      script.src = "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js";
      script.onload = () => resolve(true);
      script.onerror = () => reject(new Error("Chart.js از CDN بارگذاری نشد"));
      document.head.appendChild(script);
    });
  }

  function loadWidget(widgetId, action, params = {}) {
    const container = document.getElementById(widgetId);
    if (!container) return;
    if (!NONCE) {
      container.innerHTML = '<div class="widget-loading">در حال بارگذاری...</div>';
      return;
    }
    container.innerHTML = '<div class="widget-loading"><div class="loading-spinner"></div></div>';
    const formData = new FormData();
    formData.append("action", action);
    formData.append("nonce", NONCE);
    for (const [key, value] of Object.entries(params)) formData.append(key, value);
    fetch(AJAX_URL, { method: "POST", body: formData })
      .then((r) => r.json())
      .then((data) => {
        if (data.success && data.data?.html) container.innerHTML = data.data.html;
        else container.innerHTML = '<div class="widget-empty">خطا در بارگذاری</div>';
      })
      .catch(() => {
        container.innerHTML = '<div class="widget-empty">خطا در اتصال</div>';
      });
  }

  /* ============================================================
     📅 Calendar — Shamsi / Gregorian
  ============================================================ */
  const J_MONTHS = ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"];
  const G_MONTHS = ["ژانویه","فوریه","مارس","آوریل","مه","ژوئن","ژوئیه","اوت","سپتامبر","اکتبر","نوامبر","دسامبر"];
  const DAY_NAMES = ["ش","ی","د","س","چ","پ","ج"];

  const calState = {
    mode: "jalali",
    year: 1405,
    month: 8,
    holidays: {},
    selected: null,
    dir: 0,
  };

  function gregorianToJalali(gy, gm, gd) {
    const g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    const gy2 = gm > 2 ? gy + 1 : gy;
    let days = 355666 + 365*gy + Math.floor((gy2+3)/4) - Math.floor((gy2+99)/100) + Math.floor((gy2+399)/400) + gd + g_d_m[gm-1];
    let jy = -1595 + 33*Math.floor(days/12053);
    days %= 12053;
    jy += 4*Math.floor(days/1461);
    days %= 1461;
    if (days > 365) { jy += Math.floor((days-1)/365); days = (days-1)%365; }
    let jm, jd;
    if (days < 186) { jm = 1 + Math.floor(days/31); jd = 1 + (days%31); }
    else { jm = 7 + Math.floor((days-186)/30); jd = 1 + ((days-186)%30); }
    return [jy, jm, jd];
  }

  function jalaliToGregorian(jy, jm, jd) {
    let gy = jy <= 979 ? 621 : 1600;
    jy -= jy <= 979 ? 0 : 979;
    let days = 365*jy + Math.floor(jy/33)*8 + Math.floor(((jy%33)+3)/4) + 78 + jd + (jm<7 ? (jm-1)*31 : (jm-7)*30+186);
    gy += 400*Math.floor(days/146097);
    days %= 146097;
    if (days > 36524) { gy += 100*Math.floor(--days/36524); days %= 36524; if (days >= 365) days++; }
    gy += 4*Math.floor(days/1461);
    days %= 1461;
    if (days > 365) { gy += Math.floor((days-1)/365); days = (days-1)%365; }
    let gd = days + 1;
    const sal_a = [0,31,(gy%4===0&&gy%100!==0)||gy%400===0?29:28,31,30,31,30,31,31,30,31,30,31];
    let gm;
    for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
    return [gy, gm, gd];
  }

  function isJalaliLeap(jy) { return [1,5,9,13,17,22,26,30].indexOf(jy%33) !== -1; }
  function jalaliMonthDays(jy, jm) { if (jm <= 6) return 31; if (jm <= 11) return 30; return isJalaliLeap(jy) ? 30 : 29; }
  function pad2(n) { return String(n).padStart(2, "0"); }

  function daysInMonth(y, m) {
    return calState.mode === "jalali" ? jalaliMonthDays(y, m) : new Date(y, m, 0).getDate();
  }
  function firstWeekIndex(y, m) {
    const d = calState.mode === "jalali"
      ? (() => { const g = jalaliToGregorian(y, m, 1); return new Date(g[0], g[1]-1, g[2]); })()
      : new Date(y, m-1, 1);
    return (d.getDay() + 1) % 7; // Saturday-first
  }
  function shiftMonth(y, m, delta) {
    let nm = m + delta, ny = y;
    if (nm < 1) { nm = 12; ny--; } else if (nm > 12) { nm = 1; ny++; }
    return { y: ny, m: nm };
  }
  function todayParts() {
    const now = new Date();
    if (calState.mode === "jalali") {
      const [y, m, d] = gregorianToJalali(now.getFullYear(), now.getMonth()+1, now.getDate());
      return { y, m, d };
    }
    return { y: now.getFullYear(), m: now.getMonth()+1, d: now.getDate() };
  }
  function todayNumeric() {
    const t = todayParts();
    return calState.mode === "jalali"
      ? `${t.y}/${pad2(t.m)}/${pad2(t.d)}`
      : `${t.y}-${pad2(t.m)}-${pad2(t.d)}`;
  }
  function neededJalaliYears() {
    if (calState.mode === "jalali") return [calState.year];
    const dim = daysInMonth(calState.year, calState.month);
    const a = gregorianToJalali(calState.year, calState.month, 1);
    const b = gregorianToJalali(calState.year, calState.month, dim);
    return a[0] === b[0] ? [a[0]] : [a[0], b[0]];
  }

  async function ensureHolidays(jYear) {
    if (calState.holidays[jYear]) return;
    calState.holidays[jYear] = {};
    if (!NONCE) return;
    const fd = new FormData();
    fd.append("action", "nextsafar_get_calendar_events");
    fd.append("nonce", NONCE);
    fd.append("year", jYear);
    try {
      const res = await fetch(AJAX_URL, { method: "POST", body: fd });
      const data = await res.json();
      if (data.success && data.data?.holidays) calState.holidays[jYear] = data.data.holidays;
    } catch (e) { /* offline: calendar still works without holidays */ }
  }

  function holidayTitle(y, m, d) {
    let jy = y, jm = m, jd = d;
    if (calState.mode === "greg") {
      const j = gregorianToJalali(y, m, d);
      jy = j[0]; jm = j[1]; jd = j[2];
    }
    const map = calState.holidays[jy];
    if (!map) return null;
    const h = map[jm + "/" + jd];
    return h ? h.title : null;
  }

  function injectCalendarStyles() {
    if (document.getElementById("ns-cal-styles")) return;
    const css = `
      .ns-cal{--cal-primary:var(--ns-primary,#2563eb);--cal-danger:var(--ns-danger,#e11d48);--cal-text:var(--ns-text,#111827);--cal-muted:var(--ns-text-muted,#6b7280);--cal-border:var(--ns-border,#e5e7eb);display:flex;flex-direction:column;gap:12px}
      .ns-cal *{box-sizing:border-box}
      .ns-cal-header{display:flex;align-items:center;justify-content:space-between;gap:10px}
      .ns-cal-nav{display:flex;align-items:center;gap:8px}
      .ns-cal-title{font-size:15px;font-weight:700;color:var(--cal-text);letter-spacing:-.01em;min-width:104px;text-align:center;font-variant-numeric:tabular-nums}
      .ns-cal-title span{font-weight:500;color:var(--cal-muted);margin-inline-start:4px}
      .ns-cal-navbtn{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--cal-border);background:#fff;border-radius:9px;color:var(--cal-muted);cursor:pointer;transition:all .16s ease;padding:0}
      .ns-cal-navbtn:hover{color:var(--cal-primary);border-color:var(--cal-primary);background:rgba(37,99,235,.06)}
      .ns-cal-navbtn:active{transform:scale(.92)}
      .ns-cal-navbtn svg{width:15px;height:15px}
      .ns-cal-mode{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--cal-border);background:#f8fafc;color:var(--cal-text);font-size:12px;font-weight:600;padding:6px 12px;border-radius:8px;cursor:pointer;transition:all .16s ease;font-family:inherit}
      .ns-cal-mode:hover{border-color:var(--cal-primary);color:var(--cal-primary);background:rgba(37,99,235,.06)}
      .ns-cal-mode:active{transform:scale(.96)}
      .ns-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;animation:nsCalIn .26s cubic-bezier(.22,.8,.36,1)}
      .ns-cal-grid[data-dir="1"]{--cal-shift:-10px}
      .ns-cal-grid[data-dir="-1"]{--cal-shift:10px}
      .ns-cal-grid[data-dir="0"]{--cal-shift:0px}
      @keyframes nsCalIn{from{opacity:0;transform:translateX(var(--cal-shift,0))}to{opacity:1;transform:none}}
      .ns-cal-dow{font-size:10.5px;font-weight:600;color:var(--cal-muted);text-align:center;padding:4px 0 6px}
      .ns-cal-dow.is-fri{color:var(--cal-danger)}
      .ns-cal-day{position:relative;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;border:0;background:transparent;border-radius:10px;font-size:12.5px;font-weight:500;color:var(--cal-text);cursor:pointer;font-family:inherit;font-variant-numeric:tabular-nums;transition:background .14s ease,color .14s ease,transform .14s ease;padding:0}
      .ns-cal-day:hover{background:rgba(37,99,235,.08);transform:translateY(-1px)}
      .ns-cal-day.is-out{color:var(--cal-muted);opacity:.38}
      .ns-cal-day.is-holiday{color:var(--cal-danger)}
      .ns-cal-day.is-holiday::after{content:"";position:absolute;bottom:5px;left:50%;transform:translateX(-50%);width:3px;height:3px;border-radius:50%;background:currentColor;opacity:.85}
      .ns-cal-day.is-today{background:var(--cal-primary);color:#fff;font-weight:700;}
      .ns-cal-day.is-today::after{background:#fff}
      .ns-cal-day.is-today:hover{transform:translateY(-1px);background:var(--cal-primary)}
      .ns-cal-day.is-selected:not(.is-today){box-shadow:inset 0 0 0 1.5px var(--cal-primary);color:var(--cal-primary);background:rgba(37,99,235,.05)}
      .ns-cal-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;border-top:1px dashed var(--cal-border);padding-top:10px}
      .ns-cal-today-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--cal-border);background:#fff;color:var(--cal-text);font-size:11.5px;font-weight:600;padding:6px 12px;border-radius:8px;cursor:pointer;transition:all .16s ease;font-family:inherit}
      .ns-cal-today-btn:hover{border-color:var(--cal-primary);color:var(--cal-primary);background:rgba(37,99,235,.06)}
      .ns-cal-today-btn:active{transform:scale(.96)}
      .ns-cal-today-btn svg{width:12px;height:12px}
      .ns-cal-today-num{font-size:12px;color:var(--cal-muted);font-variant-numeric:tabular-nums;letter-spacing:.02em;direction:ltr}
      .ns-cal ::-webkit-scrollbar{width:8px;height:8px}
      .ns-cal ::-webkit-scrollbar-track{background:transparent}
      .ns-cal ::-webkit-scrollbar-thumb{background:rgba(37,99,235,.25);border-radius:99px}
      .ns-cal ::-webkit-scrollbar-thumb:hover{background:rgba(37,99,235,.45)}
      @media(max-width:480px){.ns-cal-title{font-size:13.5px;min-width:88px}.ns-cal-day{font-size:11.5px;border-radius:8px}.ns-cal-mode{padding:5px 10px;font-size:11px}}
    `;
    const tag = document.createElement("style");
    tag.id = "ns-cal-styles";
    tag.textContent = css;
    document.head.appendChild(tag);
  }

  const ICON_PREV = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
  const ICON_NEXT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>';
  const ICON_MODE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.6 3 14.4 0 18M12 3c-3 3.6-3 14.4 0 18"/></svg>';
  const ICON_TODAY = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2.6" fill="currentColor" stroke="none"/></svg>';

  function renderCalendar() {
    const container = document.getElementById("calendar-widget");
    if (!container) return;
    injectCalendarStyles();

    const { mode, year, month, dir, selected } = calState;
    const months = mode === "jalali" ? J_MONTHS : G_MONTHS;
    const today = todayParts();
    const firstIdx = firstWeekIndex(year, month);
    const dim = daysInMonth(year, month);
    const prev = shiftMonth(year, month, -1);
    const next = shiftMonth(year, month, 1);
    const prevDim = daysInMonth(prev.y, prev.m);
    const totalCells = Math.ceil((firstIdx + dim) / 7) * 7;

    let cells = "";
    for (let i = 0; i < totalCells; i++) {
      let cy = year, cm = month, cd, out = false;
      if (i < firstIdx) { cd = prevDim - firstIdx + 1 + i; cy = prev.y; cm = prev.m; out = true; }
      else if (i >= firstIdx + dim) { cd = i - firstIdx - dim + 1; cy = next.y; cm = next.m; out = true; }
      else { cd = i - firstIdx + 1; }

      const dow = i % 7;
      const isFri = dow === 6;
      const title = holidayTitle(cy, cm, cd);
      const isHoliday = isFri || !!title;
      const isToday = !out && cy === today.y && cm === today.m && cd === today.d;
      const isSel = selected && cy === selected.y && cm === selected.m && cd === selected.d;

      let cls = "ns-cal-day";
      if (out) cls += " is-out";
      if (isHoliday) cls += " is-holiday";
      if (isToday) cls += " is-today";
      if (isSel) cls += " is-selected";

      const tip = title ? ` title="${title.replace(/"/g, "&quot;")}"` : (isFri ? ' title="جمعه"' : "");
      cells += `<button type="button" class="${cls}" data-day="${cy},${cm},${cd}"${tip}>${cd}</button>`;
    }

    const dows = DAY_NAMES.map((n, i) => `<div class="ns-cal-dow${i === 6 ? " is-fri" : ""}">${n}</div>`).join("");
    const switchLabel = mode === "jalali" ? "میلادی" : "شمسی";

    container.innerHTML = `
      <div class="ns-cal">
        <div class="ns-cal-header">
          <div class="ns-cal-nav">
            <button type="button" class="ns-cal-navbtn" data-cal="prev" aria-label="ماه قبل">${ICON_PREV}</button>
            <div class="ns-cal-title">${months[month - 1]}<span>${year}</span></div>
            <button type="button" class="ns-cal-navbtn" data-cal="next" aria-label="ماه بعد">${ICON_NEXT}</button>
          </div>
          <button type="button" class="ns-cal-mode" data-cal="mode" aria-label="تغییر تقویم">${ICON_MODE}${switchLabel}</button>
        </div>
        <div class="ns-cal-grid" data-dir="${dir}">${dows}${cells}</div>
        <div class="ns-cal-footer">
          <button type="button" class="ns-cal-today-btn" data-cal="today">${ICON_TODAY}برو به امروز</button>
          <span class="ns-cal-today-num">${todayNumeric()}</span>
        </div>
      </div>`;

    if (!container.dataset.calBound) {
      container.dataset.calBound = "1";
      container.addEventListener("click", onCalendarClick);
    }
  }

  async function onCalendarClick(e) {
    const actionBtn = e.target.closest("[data-cal]");
    const dayBtn = e.target.closest("[data-day]");

    if (actionBtn) {
      const action = actionBtn.getAttribute("data-cal");
      if (action === "prev" || action === "next") {
        const delta = action === "next" ? 1 : -1;
        const s = shiftMonth(calState.year, calState.month, delta);
        calState.year = s.y; calState.month = s.m; calState.dir = delta;
      } else if (action === "mode") {
        if (calState.mode === "jalali") {
          const g = jalaliToGregorian(calState.year, calState.month, 15);
          calState.mode = "greg"; calState.year = g[0]; calState.month = g[1];
        } else {
          const j = gregorianToJalali(calState.year, calState.month, 15);
          calState.mode = "jalali"; calState.year = j[0]; calState.month = j[1];
        }
        calState.selected = null; calState.dir = 0;
      } else if (action === "today") {
        const t = todayParts();
        calState.year = t.y; calState.month = t.m;
        calState.selected = { y: t.y, m: t.m, d: t.d };
        calState.dir = 0;
      }
      await Promise.all(neededJalaliYears().map(ensureHolidays));
      renderCalendar();
      return;
    }

    if (dayBtn) {
      const [y, m, d] = dayBtn.getAttribute("data-day").split(",").map(Number);
      calState.dir = 0;
      if (y !== calState.year || m !== calState.month) {
        calState.year = y; calState.month = m;
        await Promise.all(neededJalaliYears().map(ensureHolidays));
      }
      calState.selected = { y, m, d };
      renderCalendar();
    }
  }

  async function initCalendar() {
    const container = document.getElementById("calendar-widget");
    if (!container) return;
    const now = new Date();
    const [jy, jm] = gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    calState.mode = "jalali";
    calState.year = jy;
    calState.month = jm;
    const t = todayParts();
    calState.selected = { y: t.y, m: t.m, d: t.d };
    await Promise.all(neededJalaliYears().map(ensureHolidays));
    renderCalendar();
  }

  /* ============================================================
     🔍 Search Console (unchanged)
  ============================================================ */
  let searchConsoleChart = null;
  let currentRange = "28d";

  function initSearchConsoleTabs() {
    const tabs = document.querySelectorAll(".gsc-range-tab");
    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        tabs.forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");
        const range = tab.dataset.range;
        if (range && range !== currentRange) {
          currentRange = range;
          loadSearchConsoleData(range);
        }
      });
    });
  }

  async function loadSearchConsoleData(range = "28d") {
    const formData = new FormData();
    formData.append("action", "nextsafar_get_search_console_data");
    formData.append("nonce", NONCE);
    formData.append("date_range", range);

    const container = document.getElementById("gsc-chart-container");
    if (container) {
      container.innerHTML = '<div class="widget-loading"><div class="loading-spinner"></div><span>در حال دریافت از Google Search Console...</span></div>';
    }

    try {
      const response = await fetch(AJAX_URL, { method: "POST", body: formData });
      const data = await response.json();

      if (data.success && data.data?.data) {
        const d = data.data.data;

        if (!d.chart || d.chart.length === 0) {
          if (container) {
            container.innerHTML = `<div class="widget-empty"><div class="widget-empty-icon">📊</div><p><strong>هیچ داده‌ای یافت نشد</strong></p></div>`;
          }
          return;
        }

        if (container) {
          container.innerHTML = `
            <div class="gsc-stats-container">
              <div class="gsc-stat-card gsc-stat-clicks"><div class="gsc-stat-indicator"></div><div class="gsc-stat-content"><div class="gsc-stat-label">کل کلیک‌ها</div><div class="gsc-stat-value" id="gsc-clicks">${d.totals.clicks}</div></div></div>
              <div class="gsc-stat-card gsc-stat-impressions"><div class="gsc-stat-indicator"></div><div class="gsc-stat-content"><div class="gsc-stat-label">کل نمایش‌ها</div><div class="gsc-stat-value" id="gsc-impressions">${d.totals.impressions}</div></div></div>
              <div class="gsc-stat-card gsc-stat-ctr"><div class="gsc-stat-indicator gsc-indicator-ctr"></div><div class="gsc-stat-content"><div class="gsc-stat-label">میانگین CTR</div><div class="gsc-stat-value" id="gsc-ctr">${d.totals.ctr}</div></div></div>
              <div class="gsc-stat-card gsc-stat-position"><div class="gsc-stat-indicator gsc-indicator-position"></div><div class="gsc-stat-content"><div class="gsc-stat-label">میانگین رتبه</div><div class="gsc-stat-value" id="gsc-position">${d.totals.position}</div></div></div>
            </div>
            <div class="gsc-chart-wrapper"><canvas id="searchConsoleChart"></canvas></div>`;
        }

        const labels = d.chart.map((r) => {
          const parts = r.date.split("-");
          const jalali = gregorianToJalali(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2]));
          return `${jalali[2]} ${getJalaliMonthShort(jalali[1])}`;
        });
        const clicksData = d.chart.map((r) => r.clicks);
        const impressionsData = d.chart.map((r) => r.impressions);

        const canvas = document.getElementById("searchConsoleChart");
        if (!canvas) return;
        if (searchConsoleChart) { searchConsoleChart.destroy(); searchConsoleChart = null; }
        if (typeof Chart === "undefined") { await ensureChartJs(); }
        if (typeof Chart === "undefined") return;

        const ctx = canvas.getContext("2d");
        const clicksGradient = ctx.createLinearGradient(0, 0, 0, 300);
        clicksGradient.addColorStop(0, "rgba(66, 133, 244, 0.35)");
        clicksGradient.addColorStop(1, "rgba(66, 133, 244, 0.0)");
        const impressionsGradient = ctx.createLinearGradient(0, 0, 0, 300);
        impressionsGradient.addColorStop(0, "rgba(161, 66, 244, 0.25)");
        impressionsGradient.addColorStop(1, "rgba(161, 66, 244, 0.0)");

        searchConsoleChart = new Chart(ctx, {
          type: "line",
          data: {
            labels: labels,
            datasets: [
              { label: "کلیک‌ها", data: clicksData, borderColor: "#4285f4", backgroundColor: clicksGradient, borderWidth: 2.5, tension: 0.4, fill: true, pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: "#4285f4", pointHoverBorderColor: "#fff", pointHoverBorderWidth: 3, yAxisID: "y", order: 1 },
              { label: "نمایش‌ها", data: impressionsData, borderColor: "#a142f4", backgroundColor: impressionsGradient, borderWidth: 2.5, tension: 0.4, fill: true, pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: "#a142f4", pointHoverBorderColor: "#fff", pointHoverBorderWidth: 3, yAxisID: "y1", order: 2 },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: "index", intersect: false },
            plugins: {
              legend: { display: true, position: "top", align: "end", rtl: true, labels: { usePointStyle: true, pointStyle: "circle", padding: 20, font: { size: 12, family: "inherit", weight: "500" }, color: "#374151", boxWidth: 8, boxHeight: 8 } },
              tooltip: {
                enabled: true, backgroundColor: "rgba(255, 255, 255, 0.98)", titleColor: "#111827", bodyColor: "#374151", borderColor: "#e5e7eb", borderWidth: 1, padding: 14, cornerRadius: 8, boxPadding: 6,
                titleFont: { size: 13, weight: "600", family: "inherit" }, bodyFont: { size: 12, family: "inherit" }, displayColors: true, usePointStyle: true, rtl: true, caretPadding: 10, caretSize: 6,
                callbacks: {
                  title: (items) => `📅 ${items[0].label}`,
                  label: (context) => { const label = context.dataset.label; const value = context.parsed.y.toLocaleString(); const icon = label === "کلیک‌ها" ? "🖱️" : "👁️"; return ` ${icon} ${label}: ${value}`; },
                  afterBody: (items) => { if (items.length >= 2) { const clicks = items[0].parsed.y; const impressions = items[1].parsed.y; const ctr = impressions > 0 ? ((clicks / impressions) * 100).toFixed(2) : 0; return `\n📊 CTR لحظه‌ای: ${ctr}%`; } return ""; },
                },
              },
            },
            scales: {
              x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10, font: { size: 10, family: "inherit" }, color: "#9ca3af", padding: 8 } },
              y: { type: "linear", display: true, position: "left", beginAtZero: true, grid: { color: "rgba(229, 231, 235, 0.5)", drawBorder: false }, border: { display: false, dash: [4, 4] }, title: { display: true, text: "🖱️ کلیک‌ها", color: "#4285f4", font: { size: 11, weight: "600", family: "inherit" }, padding: { bottom: 8 } }, ticks: { color: "#4285f4", font: { size: 10, family: "inherit" }, padding: 8, callback: (v) => v >= 1000 ? (v / 1000).toFixed(1) + "K" : v } },
              y1: { type: "linear", display: true, position: "right", beginAtZero: true, grid: { drawOnChartArea: false }, border: { display: false }, title: { display: true, text: "👁️ نمایش‌ها", color: "#a142f4", font: { size: 11, weight: "600", family: "inherit" }, padding: { bottom: 8 } }, ticks: { color: "#a142f4", font: { size: 10, family: "inherit" }, padding: 8, callback: (v) => v >= 1000 ? (v / 1000).toFixed(1) + "K" : v } },
            },
            layout: { padding: { top: 10, bottom: 5 } },
            animation: { duration: 1200, easing: "easeInOutQuart" },
          },
        });
      } else {
        const errorMsg = data.data?.message || "خطای ناشناخته";
        if (container) container.innerHTML = `<div class="widget-empty"><p>❌ ${errorMsg}</p></div>`;
      }
    } catch (e) {
      if (container) container.innerHTML = `<div class="widget-empty"><p>❌ ${e.message}</p></div>`;
    }
  }

  function getJalaliMonthShort(month) {
    const months = ["","فرو","ارد","خرد","تیر","مرد","شهر","مهر","آبا","آذر","دی","بهم","اسف"];
    return months[month] || "";
  }

  /* ============================================================
     Initialize
  ============================================================ */
  document.addEventListener("DOMContentLoaded", function () {
    initCalendar();
    initSearchConsoleTabs();
    loadSearchConsoleData("28d");

    setTimeout(() => {
      loadWidget("recent-comments", "nextsafar_get_recent_comments");
      loadWidget("yoast-widget", "nextsafar_get_yoast_stats");
    }, 200);

    setTimeout(() => {
      loadWidget("weather-widget", "nextsafar_get_weather");
      loadWidget("admin-logins", "nextsafar_get_admin_logins");
    }, 600);
  });
})(jQuery);