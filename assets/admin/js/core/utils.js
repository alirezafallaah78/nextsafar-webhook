/**
 * NextSafar Utils - توابع کمکی مشترک
 */
const NextSafarUtils = {
  /**
   * تبدیل تاریخ میلادی به شمسی
   */
  gregorianToJalali(gy, gm, gd) {
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
  },

  /**
   * تبدیل تاریخ شمسی به میلادی
   */
  jalaliToGregorian(jy, jm, jd) {
    jy += 1595;
    let days =
      -355668 +
      365 * jy +
      Math.floor(jy / 33) * 8 +
      Math.floor(((jy % 33) + 3) / 4) +
      jd +
      (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);

    let gy = 400 * Math.floor(days / 146097);
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
  },

  /**
   * بررسی سال کبیسه شمسی
   */
  isJalaliLeapYear(jy) {
    const breaks = [
      -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097,
      2192, 2262, 2324, 2394, 2456, 3178,
    ];
    let jp = breaks[0],
      jump;

    for (let i = 1; i < breaks.length; i++) {
      const jm = breaks[i];
      jump = jm - jp;
      if (jy < jm) break;
      jp = jm;
    }

    let n = jy - jp;
    if (jump - n < 6) n = n - jump + Math.floor((jump + 4) / 33) * 33;
    let leap = (((n + 1) % 33) - 1) % 4 === 0;

    if (leap && (n === jump || n === jump - Math.floor(jump / 33) - 1)) {
      leap = false;
    }

    return leap;
  },

  /**
   * گرفتن تعداد روزهای یک ماه شمسی
   */
  getJalaliMonthDays(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return this.isJalaliLeapYear(jy) ? 30 : 29;
  },

  /**
   * Escape HTML
   */
  escapeHtml(text) {
    if (!text && text !== 0) return "";
    const div = document.createElement("div");
    div.textContent = String(text);
    return div.innerHTML;
  },

  /**
   * Debounce function
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  /**
   * Format number with Persian locale
   */
  formatNumber(num) {
    return new Intl.NumberFormat("fa-IR").format(num);
  },
};

// Export برای استفاده در سایر ماژول‌ها
if (typeof window !== "undefined") {
  window.NextSafarUtils = NextSafarUtils;
}
