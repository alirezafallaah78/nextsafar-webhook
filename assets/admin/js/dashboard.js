/**
 * NextSafar Dashboard - Search Console Only (v3)
 * ✅ تب‌های زمانی: ۲۴ ساعته / ۷ روز / ۲۸ روز / ۳ ماه
 * ✅ ۴ معیار: Clicks, Impressions, CTR, Position
 */
(function ($) {
  "use strict";

  const AJAX_URL = nextsafarAdmin.ajaxUrl;
  const NONCE = nextsafarAdmin.dashboardNonce;

  // 🆕 Dynamic Chart.js loader (اگر از PHP لود نشد، اینجا لود می‌شود)
  async function ensureChartJs() {
    if (typeof Chart !== "undefined") return true;

    return new Promise((resolve, reject) => {
      console.log("📦 Chart.js در حال بارگذاری از CDN...");
      const script = document.createElement("script");
      script.src =
        "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js";
      script.onload = () => {
        console.log("✅ Chart.js با موفقیت لود شد");
        resolve(true);
      };
      script.onerror = () => reject(new Error("Chart.js از CDN بارگذاری نشد"));
      document.head.appendChild(script);
    });
  }

  // ============================================================
  // Utility Functions
  // ============================================================
  function loadWidget(widgetId, action, params = {}) {
    const container = document.getElementById(widgetId);
    if (!container) return;
    if (!NONCE) {
      container.innerHTML =
        '<div class="widget-loading">در حال بارگذاری...</div>';
      return;
    }
    container.innerHTML =
      '<div class="widget-loading"><div class="loading-spinner"></div></div>';
    const formData = new FormData();
    formData.append("action", action);
    formData.append("nonce", NONCE);
    for (const [key, value] of Object.entries(params)) {
      formData.append(key, value);
    }
    fetch(AJAX_URL, { method: "POST", body: formData })
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.data?.html) {
          container.innerHTML = data.data.html;
        } else {
          container.innerHTML =
            '<div class="widget-empty">خطا در بارگذاری</div>';
        }
      })
      .catch((err) => {
        console.error("Widget load error:", err);
        container.innerHTML = '<div class="widget-empty">خطا در اتصال</div>';
      });
  }

  // ============================================================
  // 📅 Calendar Widget
  // ============================================================
  const calendarState = { year: 1404, month: 1, holidays: {}, today: null };

  function gregorianToJalali(gy, gm, gd) {
    const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    const gy2 = gm > 2 ? gy + 1 : gy;
    let days =
      355666 +
      365 * gy +
      Math.floor((gy2 + 3) / 4) -
      Math.floor((gy2 + 99) / 100) +
      Math.floor((gy2 + 399) / 400) +
      gd +
      g_d_m[gm - 1];
    let jy = -1595 + 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      jy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    let jm, jd;
    if (days < 186) {
      jm = 1 + Math.floor(days / 31);
      jd = 1 + (days % 31);
    } else {
      jm = 7 + Math.floor((days - 186) / 30);
      jd = 1 + ((days - 186) % 30);
    }
    return [jy, jm, jd];
  }

  function jalaliToGregorian(jy, jm, jd) {
    let gy = jy <= 979 ? 621 : 1600;
    jy -= jy <= 979 ? 0 : 979;
    let days =
      365 * jy +
      Math.floor(jy / 33) * 8 +
      Math.floor(((jy % 33) + 3) / 4) +
      78 +
      jd +
      (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
    gy += 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
      gy += 100 * Math.floor(--days / 36524);
      days %= 36524;
      if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      gy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    let gd = days + 1;
    const sal_a = [
      0,
      31,
      (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0 ? 29 : 28,
      31,
      30,
      31,
      30,
      31,
      31,
      30,
      31,
      30,
      31,
    ];
    let gm;
    for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) {
      gd -= sal_a[gm];
    }
    return [gy, gm, gd];
  }

  function isJalaliLeap(jy) {
    return [1, 5, 9, 13, 17, 22, 26, 30].indexOf(jy % 33) !== -1;
  }
  function jalaliMonthDays(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isJalaliLeap(jy) ? 30 : 29;
  }
  function getFirstDayOfMonth(jy, jm) {
    const greg = jalaliToGregorian(jy, jm, 1);
    const date = new Date(greg[0], greg[1] - 1, greg[2]);
    return (date.getDay() + 1) % 7;
  }

  async function loadCalendarEvents(year) {
    if (!NONCE) return;
    const formData = new FormData();
    formData.append("action", "nextsafar_get_calendar_events");
    formData.append("nonce", NONCE);
    formData.append("year", year);
    try {
      const response = await fetch(AJAX_URL, {
        method: "POST",
        body: formData,
      });
      const data = await response.json();
      if (data.success && data.data?.holidays) {
        calendarState.holidays = data.data.holidays;
      }
    } catch (e) {
      console.error("Failed to load calendar events:", e);
    }
  }

  function renderCalendar() {
    const container = document.getElementById("calendar-widget");
    if (!container) return;

    const jy = calendarState.year;
    const jm = calendarState.month;
    const jMonths = [
      "فروردین",
      "اردیبهشت",
      "خرداد",
      "تیر",
      "مرداد",
      "شهریور",
      "مهر",
      "آبان",
      "آذر",
      "دی",
      "بهمن",
      "اسفند",
    ];
    const dayNames = ["ش", "ی", "د", "س", "چ", "پ", "ج"];
    const now = new Date();
    const todayJalali = gregorianToJalali(
      now.getFullYear(),
      now.getMonth() + 1,
      now.getDate(),
    );

    const firstDayOfWeek = getFirstDayOfMonth(jy, jm);
    const daysInMonth = jalaliMonthDays(jy, jm);

    let html = '<div class="calendar-container">';
    html += '<div class="calendar-header">';

    // سمت چپ: دراپ‌داون سال و ماه
    html += '<div class="calendar-selectors">';
    html += `<select class="calendar-select" id="calendar-month-select" dir="rtl">`;
    jMonths.forEach((m, idx) => {
      const selected = idx + 1 === jm ? "selected" : "";
      html += `<option value="${idx + 1}" ${selected}>${m}</option>`;
    });
    html += `</select>`;

    html += `<select class="calendar-select" id="calendar-year-select" dir="rtl">`;
    for (let y = todayJalali[0] - 5; y <= todayJalali[0] + 5; y++) {
      const selected = y === jy ? "selected" : "";
      html += `<option value="${y}" ${selected}>${y}</option>`;
    }
    html += `</select>`;
    html += "</div>";

    // سمت راست: دکمه‌های ناوبری
    html += '<div class="calendar-nav">';
    html +=
      '<button class="calendar-nav-btn" data-delta="-1" title="ماه قبل">‹</button>';
    html +=
      '<button class="calendar-nav-btn" data-delta="1" title="ماه بعد">›</button>';
    html += "</div>";
    html += "</div>"; // end calendar-header

    html += '<div class="calendar-grid">';
    dayNames.forEach((name, idx) => {
      html += `<div class="calendar-day-name ${idx === 6 ? "friday" : ""}">${name}</div>`;
    });

    for (let i = 0; i < firstDayOfWeek; i++) {
      html += '<div class="calendar-day other-month"></div>';
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const dayOfWeek = (firstDayOfWeek + day - 1) % 7;
      const isFriday = dayOfWeek === 6;
      const isToday =
        day === todayJalali[2] &&
        jm === todayJalali[1] &&
        jy === todayJalali[0];
      const holidayKey = `${jm}/${day}`;
      const holidayData = calendarState.holidays[holidayKey];
      const isHoliday = holidayData || isFriday;
      const holidayTitle = holidayData
        ? holidayData.title
        : isFriday
          ? "جمعه"
          : "";

      let classes = "calendar-day";
      if (isToday) classes += " today";
      if (isHoliday) classes += " holiday";
      if (isFriday) classes += " friday";

      const titleAttr = holidayTitle ? `title="${holidayTitle}"` : "";
      const greg = jalaliToGregorian(jy, jm, day);
      const gregorianDate = `${greg[2]}/${greg[1]}`; // فرمت میلادی ریز

      html += `<div class="${classes}" ${titleAttr}>`;
      html += `<div class="calendar-dual-date">`;
      html += `<span class="shamsi">${day}</span>`;
      html += `<span class="gregorian">${gregorianDate}</span>`;
      html += `</div></div>`;
    }
    html += "</div></div>";
    container.innerHTML = html;

    // رویدادهای دکمه‌های ناوبری
    container.querySelectorAll(".calendar-nav-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        const delta = parseInt(btn.dataset.delta);
        calendarState.month += delta;
        if (calendarState.month > 12) {
          calendarState.month = 1;
          calendarState.year++;
        } else if (calendarState.month < 1) {
          calendarState.month = 12;
          calendarState.year--;
        }
        updateCalendarSelectors();
        loadCalendarEvents(calendarState.year).then(() => renderCalendar());
      });
    });

    // رویدادهای تغییر دراپ‌داون
    const monthSelect = container.querySelector("#calendar-month-select");
    const yearSelect = container.querySelector("#calendar-year-select");

    if (monthSelect) {
      monthSelect.addEventListener("change", () => {
        calendarState.month = parseInt(monthSelect.value);
        updateCalendarSelectors();
        loadCalendarEvents(calendarState.year).then(() => renderCalendar());
      });
    }
    if (yearSelect) {
      yearSelect.addEventListener("change", () => {
        calendarState.year = parseInt(yearSelect.value);
        updateCalendarSelectors();
        loadCalendarEvents(calendarState.year).then(() => renderCalendar());
      });
    }
  }

  function updateCalendarSelectors() {
    const monthSelect = document.getElementById("calendar-month-select");
    const yearSelect = document.getElementById("calendar-year-select");
    if (monthSelect) monthSelect.value = calendarState.month;
    if (yearSelect) yearSelect.value = calendarState.year;
  }

  async function initCalendar() {
    const now = new Date();
    const todayJalali = gregorianToJalali(
      now.getFullYear(),
      now.getMonth() + 1,
      now.getDate(),
    );
    calendarState.year = todayJalali[0];
    calendarState.month = todayJalali[1];
    calendarState.today = todayJalali;
    await loadCalendarEvents(calendarState.year);
    renderCalendar();
  }

  // ============================================================
  // 🔍 Search Console - با تب‌های زمانی
  // ============================================================
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
      container.innerHTML =
        '<div class="widget-loading"><div class="loading-spinner"></div><span>در حال دریافت از Google Search Console...</span></div>';
    }

    try {
      const response = await fetch(AJAX_URL, {
        method: "POST",
        body: formData,
      });
      const data = await response.json();

      if (data.success && data.data?.data) {
        const d = data.data.data;

        // بررسی داده خالی
        if (!d.chart || d.chart.length === 0) {
          if (container) {
            container.innerHTML = `
                        <div class="widget-empty">
                            <div class="widget-empty-icon">📊</div>
                            <p><strong>هیچ داده‌ای یافت نشد</strong></p>
                        </div>`;
          }
          return;
        }

        // 🎨 بازسازی container با طراحی مدرن
        if (container) {
          container.innerHTML = `
                    <div class="gsc-stats-container">
                        <div class="gsc-stat-card gsc-stat-clicks">
                            <div class="gsc-stat-indicator"></div>
                            <div class="gsc-stat-content">
                                <div class="gsc-stat-label">کل کلیک‌ها</div>
                                <div class="gsc-stat-value" id="gsc-clicks">${d.totals.clicks}</div>
                            </div>
                        </div>
                        <div class="gsc-stat-card gsc-stat-impressions">
                            <div class="gsc-stat-indicator"></div>
                            <div class="gsc-stat-content">
                                <div class="gsc-stat-label">کل نمایش‌ها</div>
                                <div class="gsc-stat-value" id="gsc-impressions">${d.totals.impressions}</div>
                            </div>
                        </div>
                        <div class="gsc-stat-card gsc-stat-ctr">
                            <div class="gsc-stat-indicator gsc-indicator-ctr"></div>
                            <div class="gsc-stat-content">
                                <div class="gsc-stat-label">میانگین CTR</div>
                                <div class="gsc-stat-value" id="gsc-ctr">${d.totals.ctr}</div>
                            </div>
                        </div>
                        <div class="gsc-stat-card gsc-stat-position">
                            <div class="gsc-stat-indicator gsc-indicator-position"></div>
                            <div class="gsc-stat-content">
                                <div class="gsc-stat-label">میانگین رتبه</div>
                                <div class="gsc-stat-value" id="gsc-position">${d.totals.position}</div>
                            </div>
                        </div>
                    </div>
                    <div class="gsc-chart-wrapper">
                        <canvas id="searchConsoleChart"></canvas>
                    </div>
                `;
        }

        // 🆕 تبدیل تاریخ میلادی به شمسی برای X-axis
        const labels = d.chart.map((r) => {
          const parts = r.date.split("-");
          const gy = parseInt(parts[0]);
          const gm = parseInt(parts[1]);
          const gd = parseInt(parts[2]);
          const jalali = gregorianToJalali(gy, gm, gd);
          return `${jalali[2]} ${getJalaliMonthShort(jalali[1])}`;
        });
        const clicksData = d.chart.map((r) => r.clicks);
        const impressionsData = d.chart.map((r) => r.impressions);

        const canvas = document.getElementById("searchConsoleChart");
        if (!canvas) return;

        if (searchConsoleChart) {
          searchConsoleChart.destroy();
          searchConsoleChart = null;
        }

        if (typeof Chart === "undefined") {
          console.error("Chart.js not loaded");
          return;
        }

        const ctx = canvas.getContext("2d");

        // 🎨 ساخت Gradient برای Clicks (آبی)
        const clicksGradient = ctx.createLinearGradient(0, 0, 0, 300);
        clicksGradient.addColorStop(0, "rgba(66, 133, 244, 0.35)");
        clicksGradient.addColorStop(1, "rgba(66, 133, 244, 0.0)");

        // 🎨 ساخت Gradient برای Impressions (بنفش)
        const impressionsGradient = ctx.createLinearGradient(0, 0, 0, 300);
        impressionsGradient.addColorStop(0, "rgba(161, 66, 244, 0.25)");
        impressionsGradient.addColorStop(1, "rgba(161, 66, 244, 0.0)");

        searchConsoleChart = new Chart(ctx, {
          type: "line",
          data: {
            labels: labels,
            datasets: [
              {
                label: "کلیک‌ها",
                data: clicksData,
                borderColor: "#4285f4",
                backgroundColor: clicksGradient,
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: "#4285f4",
                pointHoverBorderColor: "#fff",
                pointHoverBorderWidth: 3,
                yAxisID: "y",
                order: 1,
              },
              {
                label: "نمایش‌ها",
                data: impressionsData,
                borderColor: "#a142f4",
                backgroundColor: impressionsGradient,
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: "#a142f4",
                pointHoverBorderColor: "#fff",
                pointHoverBorderWidth: 3,
                yAxisID: "y1",
                order: 2,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
              mode: "index",
              intersect: false,
            },
            plugins: {
              legend: {
                display: true,
                position: "top",
                align: "end",
                rtl: true,
                labels: {
                  usePointStyle: true,
                  pointStyle: "circle",
                  padding: 20,
                  font: { size: 12, family: "inherit", weight: "500" },
                  color: "#374151",
                  boxWidth: 8,
                  boxHeight: 8,
                },
              },
              tooltip: {
                enabled: true,
                backgroundColor: "rgba(255, 255, 255, 0.98)",
                titleColor: "#111827",
                bodyColor: "#374151",
                borderColor: "#e5e7eb",
                borderWidth: 1,
                padding: 14,
                cornerRadius: 8,
                boxPadding: 6,
                titleFont: { size: 13, weight: "600", family: "inherit" },
                bodyFont: { size: 12, family: "inherit" },
                displayColors: true,
                usePointStyle: true,
                rtl: true,
                caretPadding: 10,
                caretSize: 6,
                callbacks: {
                  title: function (items) {
                    return `📅 ${items[0].label}`;
                  },
                  label: function (context) {
                    const label = context.dataset.label;
                    const value = context.parsed.y.toLocaleString();
                    const icon = label === "کلیک‌ها" ? "🖱️" : "👁️";
                    return ` ${icon} ${label}: ${value}`;
                  },
                  afterBody: function (items) {
                    if (items.length >= 2) {
                      const clicks = items[0].parsed.y;
                      const impressions = items[1].parsed.y;
                      const ctr =
                        impressions > 0
                          ? ((clicks / impressions) * 100).toFixed(2)
                          : 0;
                      return `\n📊 CTR لحظه‌ای: ${ctr}%`;
                    }
                    return "";
                  },
                },
              },
            },
            scales: {
              x: {
                grid: {
                  display: false,
                },
                border: { display: false },
                ticks: {
                  maxRotation: 0,
                  autoSkip: true,
                  maxTicksLimit: 10,
                  font: { size: 10, family: "inherit" },
                  color: "#9ca3af",
                  padding: 8,
                },
              },
              y: {
                type: "linear",
                display: true,
                position: "left",
                beginAtZero: true,
                grid: {
                  color: "rgba(229, 231, 235, 0.5)",
                  drawBorder: false,
                },
                border: { display: false, dash: [4, 4] },
                title: {
                  display: true,
                  text: "🖱️ کلیک‌ها",
                  color: "#4285f4",
                  font: { size: 11, weight: "600", family: "inherit" },
                  padding: { bottom: 8 },
                },
                ticks: {
                  color: "#4285f4",
                  font: { size: 10, family: "inherit" },
                  padding: 8,
                  callback: function (value) {
                    if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                    return value;
                  },
                },
              },
              y1: {
                type: "linear",
                display: true,
                position: "right",
                beginAtZero: true,
                grid: { drawOnChartArea: false },
                border: { display: false },
                title: {
                  display: true,
                  text: "👁️ نمایش‌ها",
                  color: "#a142f4",
                  font: { size: 11, weight: "600", family: "inherit" },
                  padding: { bottom: 8 },
                },
                ticks: {
                  color: "#a142f4",
                  font: { size: 10, family: "inherit" },
                  padding: 8,
                  callback: function (value) {
                    if (value >= 1000) return (value / 1000).toFixed(1) + "K";
                    return value;
                  },
                },
              },
            },
            layout: {
              padding: { top: 10, bottom: 5 },
            },
            animation: {
              duration: 1200,
              easing: "easeInOutQuart",
            },
          },
        });
      } else {
        const errorMsg = data.data?.message || "خطای ناشناخته";
        if (container) {
          container.innerHTML = `<div class="widget-empty"><p>❌ ${errorMsg}</p></div>`;
        }
      }
    } catch (e) {
      console.error("Search Console error:", e);
      if (container) {
        container.innerHTML = `<div class="widget-empty"><p>❌ ${e.message}</p></div>`;
      }
    }
  }

  // 🆕 Helper: نام کوتاه ماه‌های شمسی
  function getJalaliMonthShort(month) {
    const months = [
      "",
      "فرو",
      "ارد",
      "خرد",
      "تیر",
      "مرد",
      "شهر",
      "مهر",
      "آبا",
      "آذر",
      "دی",
      "بهم",
      "اسف",
    ];
    return months[month] || "";
  }

  // ============================================================
  // Initialize All Widgets
  // ============================================================
  document.addEventListener("DOMContentLoaded", function () {
    // Calendar
    initCalendar();

    // 🔍 Search Console
    initSearchConsoleTabs();
    loadSearchConsoleData("28d");

    // 🚀 بقیه ویجت‌ها با تأخیر
    setTimeout(() => {
      loadWidget("recent-comments", "nextsafar_get_recent_comments");
      loadWidget("yoast-widget", "nextsafar_get_yoast_stats");
    }, 200);

    setTimeout(() => {
      loadWidget("expiring-tours", "nextsafar_get_expiring_tours");
      loadWidget("recent-bookings", "nextsafar_get_recent_bookings");
    }, 400);

    setTimeout(() => {
      loadWidget("weather-widget", "nextsafar_get_weather");
      loadWidget("upcoming-tours", "nextsafar_get_upcoming_tours");
      loadWidget("admin-logins", "nextsafar_get_admin_logins");
    }, 600);

    console.log("✅ NextSafar Dashboard v3 initialized (Search Console Only)");
  });
})(jQuery);
