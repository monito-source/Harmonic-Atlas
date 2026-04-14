(() => {
  const navigations = Array.from(document.querySelectorAll("[data-site-navigation]"));

  if (!navigations.length) {
    return;
  }

  const closeTimers = new WeakMap();

  const getParts = (navigation) => ({
    toggle: navigation.querySelector("[data-site-navigation-toggle]"),
    panel: navigation.querySelector("[data-site-navigation-panel]"),
  });

  const clearCloseTimer = (navigation) => {
    const timerId = closeTimers.get(navigation);

    if (timerId) {
      window.clearTimeout(timerId);
      closeTimers.delete(navigation);
    }
  };

  const syncExpanded = (navigation, expanded) => {
    const { toggle } = getParts(navigation);

    if (toggle) {
      toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    }
  };

  const finishClose = (navigation) => {
    const { panel } = getParts(navigation);

    navigation.classList.remove("is-closing");

    if (!navigation.classList.contains("is-open") && panel) {
      panel.hidden = true;
    }
  };

  const closeNavigation = (navigation, { restoreFocus = false } = {}) => {
    const { toggle, panel } = getParts(navigation);

    clearCloseTimer(navigation);

    if (!panel) {
      return;
    }

    if (!navigation.classList.contains("is-open") && !navigation.classList.contains("is-closing")) {
      return;
    }

    navigation.classList.remove("is-open");
    navigation.classList.add("is-closing");
    syncExpanded(navigation, false);

    const timerId = window.setTimeout(() => {
      finishClose(navigation);
      closeTimers.delete(navigation);
    }, 280);

    closeTimers.set(navigation, timerId);

    if (restoreFocus && toggle) {
      toggle.focus();
    }
  };

  const closeOtherNavigations = (activeNavigation) => {
    navigations.forEach((navigation) => {
      if (navigation !== activeNavigation) {
        closeNavigation(navigation);
      }
    });
  };

  const openNavigation = (navigation) => {
    const { panel } = getParts(navigation);

    if (!panel) {
      return;
    }

    clearCloseTimer(navigation);
    closeOtherNavigations(navigation);
    panel.hidden = false;
    navigation.classList.remove("is-closing");
    window.requestAnimationFrame(() => {
      navigation.classList.add("is-open");
      syncExpanded(navigation, true);
    });
  };

  navigations.forEach((navigation) => {
    const { toggle } = getParts(navigation);

    if (!toggle) {
      return;
    }

    toggle.addEventListener("click", () => {
      if (navigation.classList.contains("is-open")) {
        closeNavigation(navigation);
        return;
      }

      openNavigation(navigation);
    });
  });

  document.addEventListener("pointerdown", (event) => {
    navigations.forEach((navigation) => {
      if (!navigation.contains(event.target)) {
        closeNavigation(navigation);
      }
    });
  });

  document.addEventListener("focusin", (event) => {
    navigations.forEach((navigation) => {
      if (!navigation.contains(event.target)) {
        closeNavigation(navigation);
      }
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") {
      return;
    }

    navigations.forEach((navigation) => {
      if (navigation.classList.contains("is-open")) {
        closeNavigation(navigation, { restoreFocus: true });
      }
    });
  });
})();
