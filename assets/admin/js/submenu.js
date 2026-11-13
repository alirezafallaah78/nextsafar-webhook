/**
 * NextSafar - Flyout Submenu Manager
 * محاسبه موقعیت submenu با position: fixed
 */

document.addEventListener("DOMContentLoaded", function () {
  const navWrappers = document.querySelectorAll(".nextsafar-nav-item-wrapper");
  let activeSubmenu = null;
  let closeTimeout = null;

  navWrappers.forEach(function (wrapper) {
    const parentLink = wrapper.querySelector(".nextsafar-nav-parent");
    const submenu = wrapper.querySelector(".nextsafar-submenu");

    if (!parentLink || !submenu) return;

    // باز کردن submenu با hover
    wrapper.addEventListener("mouseenter", function () {
      clearTimeout(closeTimeout);

      // بستن submenu قبلی
      if (activeSubmenu && activeSubmenu !== submenu) {
        activeSubmenu.classList.remove("is-open");
      }

      // محاسبه موقعیت
      const rect = wrapper.getBoundingClientRect();
      const submenuWidth = 240;
      const gap = 4;

      // سایدبار در سمت راست است (RTL)
      // پس submenu باید در سمت چپ سایدبار ظاهر شود
      const sidebarRect = document
        .querySelector(".nextsafar-sidebar")
        .getBoundingClientRect();

      submenu.style.top = rect.top + "px";
      submenu.style.left = sidebarRect.left - submenuWidth - gap + "px";
      submenu.style.right = "auto";

      // بررسی اینکه submenu از پایین بیرون نزند
      const submenuHeight = submenu.offsetHeight || 200;
      const viewportHeight = window.innerHeight;

      if (rect.top + submenuHeight > viewportHeight - 20) {
        submenu.style.top =
          Math.max(20, viewportHeight - submenuHeight - 20) + "px";
      }

      submenu.classList.add("is-open");
      activeSubmenu = submenu;
    });

    wrapper.addEventListener("mouseleave", function () {
      closeTimeout = setTimeout(function () {
        submenu.classList.remove("is-open");
        if (activeSubmenu === submenu) {
          activeSubmenu = null;
        }
      }, 150);
    });

    // نگه داشتن submenu باز وقتی mouse روی آن است
    submenu.addEventListener("mouseenter", function () {
      clearTimeout(closeTimeout);
    });

    submenu.addEventListener("mouseleave", function () {
      closeTimeout = setTimeout(function () {
        submenu.classList.remove("is-open");
        if (activeSubmenu === submenu) {
          activeSubmenu = null;
        }
      }, 150);
    });
  });

  // بستن submenu وقتی روی content کلیک می‌شود
  document.addEventListener("click", function (e) {
    if (
      activeSubmenu &&
      !e.target.closest(".nextsafar-nav-item-wrapper") &&
      !e.target.closest(".nextsafar-submenu")
    ) {
      activeSubmenu.classList.remove("is-open");
      activeSubmenu = null;
    }
  });

  // بستن submenu در scroll
  window.addEventListener(
    "scroll",
    function () {
      if (activeSubmenu) {
        activeSubmenu.classList.remove("is-open");
        activeSubmenu = null;
      }
    },
    true,
  );

  // بستن submenu در resize
  window.addEventListener("resize", function () {
    if (activeSubmenu) {
      activeSubmenu.classList.remove("is-open");
      activeSubmenu = null;
    }
  });
});
