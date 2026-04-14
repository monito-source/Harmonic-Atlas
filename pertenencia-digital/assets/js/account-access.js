(() => {
  const menus = Array.from(document.querySelectorAll("[data-account-menu]"));

  if (!menus.length) {
    return;
  }

  const closeTimers = new WeakMap();

  const getParts = (menu) => ({
    trigger: menu.querySelector("[data-account-menu-trigger]"),
    panel: menu.querySelector("[data-account-menu-panel]"),
  });

  const syncExpanded = (menu, expanded) => {
    const { trigger } = getParts(menu);

    if (trigger) {
      trigger.setAttribute("aria-expanded", expanded ? "true" : "false");
    }
  };

  const clearCloseTimer = (menu) => {
    const timerId = closeTimers.get(menu);

    if (timerId) {
      window.clearTimeout(timerId);
      closeTimers.delete(menu);
    }
  };

  const finishClose = (menu) => {
    const { panel } = getParts(menu);

    menu.classList.remove("is-closing");

    if (!menu.classList.contains("is-open") && panel) {
      panel.hidden = true;
    }
  };

  const closeMenu = (menu, { restoreFocus = false } = {}) => {
    const { trigger, panel } = getParts(menu);

    if (!panel) {
      return;
    }

    clearCloseTimer(menu);

    if (!menu.classList.contains("is-open") && !menu.classList.contains("is-closing")) {
      return;
    }

    menu.classList.remove("is-open");
    menu.classList.add("is-closing");
    syncExpanded(menu, false);

    const timerId = window.setTimeout(() => {
      finishClose(menu);
      closeTimers.delete(menu);
    }, 240);

    closeTimers.set(menu, timerId);

    if (restoreFocus && trigger) {
      trigger.focus();
    }
  };

  const closeOtherMenus = (activeMenu) => {
    menus.forEach((menu) => {
      if (menu !== activeMenu) {
        closeMenu(menu);
      }
    });
  };

  const openMenu = (menu) => {
    const { panel } = getParts(menu);

    if (!panel) {
      return;
    }

    clearCloseTimer(menu);
    closeOtherMenus(menu);
    panel.hidden = false;
    menu.classList.remove("is-closing");

    window.requestAnimationFrame(() => {
      menu.classList.add("is-open");
      syncExpanded(menu, true);
    });
  };

  menus.forEach((menu) => {
    const { trigger } = getParts(menu);

    if (!trigger) {
      return;
    }

    trigger.addEventListener("click", () => {
      if (menu.classList.contains("is-open")) {
        closeMenu(menu);
        return;
      }

      openMenu(menu);
    });
  });

  document.addEventListener("pointerdown", (event) => {
    menus.forEach((menu) => {
      if (!menu.contains(event.target)) {
        closeMenu(menu);
      }
    });
  });

  document.addEventListener("focusin", (event) => {
    menus.forEach((menu) => {
      if (!menu.contains(event.target)) {
        closeMenu(menu);
      }
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") {
      return;
    }

    menus.forEach((menu) => {
      if (menu.classList.contains("is-open")) {
        closeMenu(menu, { restoreFocus: true });
      }
    });
  });
})();
