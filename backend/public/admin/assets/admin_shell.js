(function () {
  const app = document.querySelector("[data-vp-app]");
  if (!app) return;

  const sidebar = app.querySelector("[data-vp-sidebar]");
  const openBtn = app.querySelector("[data-vp-sidebar-open]");
  const backdrop = app.querySelector("[data-vp-sidebar-backdrop]");

  function closeSidebar() {
    sidebar?.classList.remove("vp-sidebar--open");
    if (backdrop) {
      backdrop.hidden = true;
    }
    openBtn?.setAttribute("aria-expanded", "false");
  }

  function openSidebar() {
    sidebar?.classList.add("vp-sidebar--open");
    if (backdrop) {
      backdrop.hidden = false;
    }
    openBtn?.setAttribute("aria-expanded", "true");
  }

  openBtn?.addEventListener("click", function () {
    if (sidebar?.classList.contains("vp-sidebar--open")) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });

  backdrop?.addEventListener("click", closeSidebar);

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeSidebar();
      const openProfile = app.querySelector("[data-vp-profile][open]");
      if (openProfile) {
        openProfile.removeAttribute("open");
      }
    }
  });

  document.addEventListener("click", function (e) {
    const openProfile = app.querySelector("[data-vp-profile][open]");
    if (!openProfile || openProfile.contains(e.target)) return;
    openProfile.removeAttribute("open");
  });

  window.addEventListener("resize", function () {
    if (window.matchMedia("(min-width: 960px)").matches) {
      closeSidebar();
    }
  });
})();
