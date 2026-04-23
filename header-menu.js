(() => {
  "use strict";

  const nav = document.querySelector(".header-nav");
  const menuToggle = document.getElementById("menuToggle");
  if (!nav || !menuToggle) return;

  const mobileMedia = window.matchMedia("(max-width: 720px)");

  const closeMenu = () => {
    nav.classList.remove("is-open");
    menuToggle.setAttribute("aria-expanded", "false");
  };

  const openMenu = () => {
    nav.classList.add("is-open");
    menuToggle.setAttribute("aria-expanded", "true");
  };

  menuToggle.addEventListener("click", () => {
    if (!mobileMedia.matches) return;
    const isOpen = nav.classList.contains("is-open");
    if (isOpen) closeMenu();
    else openMenu();
  });

  document.addEventListener("click", (event) => {
    if (!mobileMedia.matches) return;
    if (!nav.classList.contains("is-open")) return;
    if (nav.contains(event.target) || menuToggle.contains(event.target)) return;
    closeMenu();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeMenu();
  });

  nav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      if (mobileMedia.matches) closeMenu();
    });
  });

  if (typeof mobileMedia.addEventListener === "function") {
    mobileMedia.addEventListener("change", (event) => {
      if (!event.matches) closeMenu();
    });
  } else if (typeof mobileMedia.addListener === "function") {
    mobileMedia.addListener((event) => {
      if (!event.matches) closeMenu();
    });
  }

  async function syncAuthLinks() {
    const navMe = document.getElementById("navMe");
    const navAdmin = document.getElementById("navAdmin");
    if (!navMe && !navAdmin) return;

    try {
      const res = await fetch("/api/auth/me.php", { credentials: "same-origin", cache: "no-cache" });
      const data = await res.json().catch(() => ({}));
      if (!data.user) return;
      if (navMe) navMe.hidden = false;
      if (navAdmin && data.user.is_admin) navAdmin.hidden = false;
    } catch (_err) {
      // Keep defaults when auth endpoint is unavailable.
    }
  }

  syncAuthLinks();
})();
