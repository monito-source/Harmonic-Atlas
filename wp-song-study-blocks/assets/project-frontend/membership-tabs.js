(function () {
  const nav = document.querySelector('[data-membership-tabs]')
  const panelsRoot = document.querySelector('[data-membership-panels]')

  if (!nav || !panelsRoot) {
    return
  }

  const tabs = Array.from(nav.querySelectorAll('[data-membership-tab]'))
  const panels = Array.from(panelsRoot.querySelectorAll('[role="tabpanel"]'))

  if (!tabs.length || !panels.length) {
    return
  }

  const updateUrl = (tab) => {
    if (!window.history || typeof window.history.replaceState !== 'function') {
      return
    }

    const url = new URL(window.location.href)
    url.searchParams.set('membership_tab', tab)
    window.history.replaceState({}, '', url.toString())
  }

  const activateTab = (tabName, shouldFocus = false) => {
    tabs.forEach((tab) => {
      const isActive = tab.dataset.membershipTab === tabName
      tab.classList.toggle('is-active', isActive)
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false')
      tab.setAttribute('tabindex', isActive ? '0' : '-1')

      if (isActive && shouldFocus) {
        tab.focus()
      }
    })

    panels.forEach((panel) => {
      const isActive = panel.id === `pd-membership-panel-${tabName}`
      panel.hidden = !isActive
    })

    updateUrl(tabName)
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      activateTab(tab.dataset.membershipTab, false)
    })

    tab.addEventListener('keydown', (event) => {
      const currentIndex = tabs.indexOf(tab)

      if (event.key === 'ArrowRight') {
        event.preventDefault()
        const nextIndex = (currentIndex + 1) % tabs.length
        activateTab(tabs[nextIndex].dataset.membershipTab, true)
      }

      if (event.key === 'ArrowLeft') {
        event.preventDefault()
        const nextIndex = (currentIndex - 1 + tabs.length) % tabs.length
        activateTab(tabs[nextIndex].dataset.membershipTab, true)
      }
    })
  })
})()
