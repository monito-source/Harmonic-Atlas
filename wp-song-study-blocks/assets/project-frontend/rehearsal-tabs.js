(function () {
  const shells = Array.from(document.querySelectorAll('[data-rehearsal-shell]'))

  const describeSnapshotElement = (element) => {
    if (!(element instanceof Element)) {
      return null
    }

    let style = null

    try {
      style = window.getComputedStyle(element)
    } catch (error) {
      style = null
    }

    const rect = element.getBoundingClientRect()

    return {
      tagName: element.tagName,
      id: element.id || '',
      className: typeof element.className === 'string' ? element.className : '',
      hidden: Boolean(element.hidden),
      display: style?.display || '',
      visibility: style?.visibility || '',
      opacity: style?.opacity || '',
      position: style?.position || '',
      zIndex: style?.zIndex || '',
      rect: {
        width: Math.round(rect.width),
        height: Math.round(rect.height),
        top: Math.round(rect.top),
        left: Math.round(rect.left)
      }
    }
  }

  window.wpssbGetRehearsalSnapshot = () => {
    const script = Array.from(document.scripts).find((item) => item.src && item.src.includes('rehearsal-tabs.js'))
    const centerElement = document.elementFromPoint(
      Math.max(0, Math.floor(window.innerWidth / 2)),
      Math.max(0, Math.floor(window.innerHeight / 2))
    )

    return {
      readyState: document.readyState,
      scriptSrc: script?.src || '',
      shellCount: document.querySelectorAll('[data-rehearsal-shell]').length,
      shell: describeSnapshotElement(document.querySelector('[data-rehearsal-shell]')),
      activePanel: describeSnapshotElement(document.querySelector('[data-rehearsal-panel]:not([hidden])')),
      activeMemberPanel: describeSnapshotElement(document.querySelector('[data-rehearsal-member-panel]:not([hidden])')),
      centerElement: describeSnapshotElement(centerElement),
      html: describeSnapshotElement(document.documentElement),
      body: describeSnapshotElement(document.body),
      activeTab: document.querySelector('[data-rehearsal-tab].is-active')?.dataset?.rehearsalTab || '',
      activeMember: document.querySelector('[data-rehearsal-member-tab].is-active')?.dataset?.rehearsalMemberTab || ''
    }
  }

  if (!shells.length) {
    return
  }

  const updateUrl = (queryKey, tab) => {
    if (!window.history || typeof window.history.replaceState !== 'function') {
      return
    }

    const url = new URL(window.location.href)
    url.searchParams.set(queryKey, tab)
    window.history.replaceState({}, '', url.toString())
  }

  const hasUrlParam = (queryKey) => {
    try {
      return new URL(window.location.href).searchParams.has(queryKey)
    } catch (error) {
      return false
    }
  }

  const getShellStoragePrefix = (shell) => {
    const pathname = window.location.pathname || 'rehearsals'
    const projectId = shell?.dataset?.rehearsalProjectId || 'global'

    return `wpssb:rehearsal:${pathname}:${projectId}`
  }

  const readShellState = (shell, scope) => {
    if (!window.localStorage) {
      return ''
    }

    try {
      return window.localStorage.getItem(`${getShellStoragePrefix(shell)}:${scope}`) || ''
    } catch (error) {
      return ''
    }
  }

  const writeShellState = (shell, scope, value) => {
    if (!window.localStorage) {
      return
    }

    try {
      const storageKey = `${getShellStoragePrefix(shell)}:${scope}`

      if (!value) {
        window.localStorage.removeItem(storageKey)
        return
      }

      window.localStorage.setItem(storageKey, value)
    } catch (error) {
      // Ignore localStorage failures and keep the UI functional.
    }
  }

  const getCompactCollapsibleMedia = () => {
    if (!window.matchMedia) {
      return null
    }

    try {
      return window.matchMedia('(max-width: 1024px)')
    } catch (error) {
      return null
    }
  }

  const isDetailsElement = (element) => element?.tagName?.toLowerCase() === 'details'

  const initCollapsiblePanels = (shell) => {
    if (!shell) {
      return
    }

    const panels = Array.from(shell.querySelectorAll('[data-rehearsal-collapsible]'))

    if (!panels.length) {
      return
    }

    const compactMedia = getCompactCollapsibleMedia()
    const isCompact = Boolean(compactMedia?.matches)

    panels.forEach((panel, index) => {
      if (!isDetailsElement(panel) || panel.dataset.rehearsalCollapsibleInitialized === 'true') {
        return
      }

      panel.dataset.rehearsalCollapsibleInitialized = 'true'

      const key = panel.dataset.rehearsalCollapseKey || `panel-${index}`
      const storageScope = `collapse:v3:${key}`
      const storedState = readShellState(shell, storageScope)

      if (storedState === 'open' || storedState === 'closed') {
        panel.open = storedState === 'open'
      } else if (isCompact && panel.dataset.rehearsalCollapseDefault === 'mobile-closed') {
        panel.open = false
      }

      panel.addEventListener('toggle', () => {
        writeShellState(shell, storageScope, panel.open ? 'open' : 'closed')
      })
    })
  }

  const resolveDayForEditor = (editor, day = '') => {
    const tabs = Array.from(editor.querySelectorAll('[data-rehearsal-day-tab]'))
    const matchingTab = tabs.find((tab) => tab.dataset.rehearsalDayTab === day)

    return matchingTab?.dataset.rehearsalDayTab || tabs[0]?.dataset.rehearsalDayTab || ''
  }

  const resolveMemberDay = (memberEditor, day = '') => {
    const currentDayEditor = memberEditor.querySelector('[data-rehearsal-member-panel]:not([hidden]) [data-rehearsal-day-editor]')
    const fallbackDayEditor = memberEditor.querySelector('[data-rehearsal-day-editor]')
    const targetDayEditor = currentDayEditor || fallbackDayEditor

    return targetDayEditor ? resolveDayForEditor(targetDayEditor, day) : ''
  }

  const resolveMemberId = (editor, memberId = '') => {
    const tabs = Array.from(editor.querySelectorAll('[data-rehearsal-member-tab]'))
    const matchingTab = tabs.find((tab) => tab.dataset.rehearsalMemberTab === memberId)

    return matchingTab?.dataset.rehearsalMemberTab || tabs[0]?.dataset.rehearsalMemberTab || ''
  }

  const activateDayPanel = (editor, day, shouldFocus = false) => {
    const tabs = Array.from(editor.querySelectorAll('[data-rehearsal-day-tab]'))
    const panels = Array.from(editor.querySelectorAll('[data-rehearsal-day-panel]'))
    const activeDay = resolveDayForEditor(editor, day)

    if (!activeDay) {
      return
    }

    tabs.forEach((tab) => {
      const isActive = tab.dataset.rehearsalDayTab === activeDay
      tab.classList.toggle('is-active', isActive)
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false')
      tab.setAttribute('tabindex', isActive ? '0' : '-1')

      if (isActive && shouldFocus) {
        tab.focus()
      }

      if (isActive && shouldFocus) {
        safeScrollIntoView(tab, {
          behavior: 'smooth',
          block: 'nearest',
          inline: 'nearest'
        })
      }
    })

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.rehearsalDayPanel !== activeDay
    })
  }

  const getActiveDay = (editor) => {
    const activeTab = editor.querySelector('[data-rehearsal-day-tab].is-active')
    const fallbackTab = editor.querySelector('[data-rehearsal-day-tab]')

    return activeTab?.dataset.rehearsalDayTab || fallbackTab?.dataset.rehearsalDayTab || ''
  }

  const syncMemberEditorDay = (memberEditor, day, shouldFocus = false) => {
    logFrontendDebug('sync_member_editor_day_start', {
      requestedDay: day,
      shouldFocus
    })

    const activeDay = resolveMemberDay(memberEditor, day)

    if (!activeDay) {
      logFrontendDebug('sync_member_editor_day_no_active_day', {
        requestedDay: day
      })
      return
    }

    memberEditor.dataset.rehearsalActiveDay = activeDay
    writeShellState(memberEditor.closest('[data-rehearsal-shell]'), 'day', activeDay)

    const activePanel = memberEditor.querySelector('[data-rehearsal-member-panel]:not([hidden])')
    const dayEditor = activePanel?.querySelector('[data-rehearsal-day-editor]')

    if (dayEditor) {
      logFrontendDebug('sync_member_editor_day_before_panel_init', {
        activeDay,
        activePanel: activePanel?.dataset?.rehearsalMemberPanel || ''
      })
      initMemberPanelContent(activePanel)
      activateDayPanel(dayEditor, activeDay, shouldFocus)
      logFrontendDebug('sync_member_editor_day_complete', {
        activeDay,
        activePanel: activePanel?.dataset?.rehearsalMemberPanel || ''
      })
    }
  }

  const activateMemberPanel = (editor, memberId, shouldFocus = false) => {
    const tabs = Array.from(editor.querySelectorAll('[data-rehearsal-member-tab]'))
    const activeMemberId = resolveMemberId(editor, memberId)

    logFrontendDebug('activate_member_panel_start', {
      requestedMemberId: memberId,
      resolvedMemberId: activeMemberId,
      tabCount: tabs.length,
      shouldFocus
    })

    if (!activeMemberId) {
      logFrontendDebug('activate_member_panel_no_member', {
        requestedMemberId: memberId
      })
      return
    }

    const activePanel = ensureMemberPanel(editor, activeMemberId)
    logFrontendDebug('activate_member_panel_after_ensure', {
      activeMemberId,
      hasActivePanel: Boolean(activePanel)
    })
    const panels = Array.from(editor.querySelectorAll('[data-rehearsal-member-panel]'))
    const currentPanel = panels.find((panel) => !panel.hidden)
    const currentDayEditor = currentPanel?.querySelector('[data-rehearsal-day-editor]')
    const activeDay = editor.dataset.rehearsalActiveDay || (currentDayEditor ? getActiveDay(currentDayEditor) : '')
    const isAlreadyActive = currentPanel?.dataset?.rehearsalMemberPanel === activeMemberId
      && tabs.some((tab) => tab.dataset.rehearsalMemberTab === activeMemberId && tab.classList.contains('is-active'))

    logFrontendDebug('activate_member_panel_panel_state', {
      activeMemberId,
      panelCount: panels.length,
      currentPanel: currentPanel?.dataset?.rehearsalMemberPanel || '',
      activeDay,
      isAlreadyActive
    })

    if (isAlreadyActive && !shouldFocus) {
      initMemberPanelContent(activePanel)
      writeShellState(editor.closest('[data-rehearsal-shell]'), 'member', activeMemberId)
      logFrontendDebug('activate_member_panel_already_active', {
        activeMemberId,
        activeDay
      })
      return
    }

    tabs.forEach((tab) => {
      const isActive = tab.dataset.rehearsalMemberTab === activeMemberId
      tab.classList.toggle('is-active', isActive)
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false')
      tab.setAttribute('tabindex', isActive ? '0' : '-1')

      if (isActive && shouldFocus) {
        tab.focus()
      }

      if (isActive && shouldFocus) {
        safeScrollIntoView(tab, {
          behavior: 'smooth',
          block: 'nearest',
          inline: 'nearest'
        })
      }
    })

    logFrontendDebug('activate_member_panel_after_tabs', {
      activeMemberId
    })

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.rehearsalMemberPanel !== activeMemberId
    })

    logFrontendDebug('activate_member_panel_after_panel_toggle', {
      activeMemberId
    })

    initMemberPanelContent(activePanel)

    logFrontendDebug('activate_member_panel_after_content_init', {
      activeMemberId
    })

    writeShellState(editor.closest('[data-rehearsal-shell]'), 'member', activeMemberId)

    if (activeDay) {
      logFrontendDebug('activate_member_panel_before_day_sync', {
        activeMemberId,
        activeDay
      })
      syncMemberEditorDay(editor, activeDay, false)
    }

    logFrontendDebug('activate_member_panel_complete', {
      activeMemberId,
      activeDay
    })
  }

  const activateCalendarView = (shell, viewName, options = {}) => {
    const { shouldFocus = false, syncUrl = true } = options
    const nav = shell.querySelector('[data-rehearsal-calendar-view-tabs]')
    const select = shell.querySelector('[data-rehearsal-calendar-view-select]')
    const tabs = Array.from(shell.querySelectorAll('[data-rehearsal-calendar-view-tab]'))
    const panels = Array.from(shell.querySelectorAll('[data-rehearsal-calendar-view-panel]'))
    const selectOptions = select instanceof HTMLSelectElement ? Array.from(select.options) : []
    const activeView = tabs.find((tab) => tab.dataset.rehearsalCalendarViewTab === viewName)?.dataset.rehearsalCalendarViewTab
      || selectOptions.find((option) => option.value === viewName)?.value
      || panels.find((panel) => panel.dataset.rehearsalCalendarViewPanel === viewName)?.dataset.rehearsalCalendarViewPanel
      || tabs[0]?.dataset.rehearsalCalendarViewTab
      || selectOptions[0]?.value
      || panels[0]?.dataset.rehearsalCalendarViewPanel

    if (!activeView) {
      return
    }

    tabs.forEach((tab) => {
      const isActive = tab.dataset.rehearsalCalendarViewTab === activeView
      tab.classList.toggle('is-active', isActive)
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false')
      tab.setAttribute('tabindex', isActive ? '0' : '-1')

      if (isActive && shouldFocus) {
        tab.focus()
      }
    })

    if (select instanceof HTMLSelectElement && select.value !== activeView) {
      select.value = activeView
    }

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.rehearsalCalendarViewPanel !== activeView
    })

    const activePanel = panels.find((panel) => panel.dataset.rehearsalCalendarViewPanel === activeView)
    if (activePanel && !isHiddenInside(activePanel, shell)) {
      initVisiblePanelContent(activePanel)
    }

    writeShellState(shell, 'calendar-view', activeView)

    if (syncUrl) {
      updateUrl(nav?.dataset?.rehearsalQuery || select?.dataset?.rehearsalQuery || 'rehearsal_calendar_view', activeView)
    }
  }

  const focusProjectSelector = (shell) => {
    const selector = shell.querySelector('[data-rehearsal-project-selector]')
    const activeElement = document.activeElement

    if (!(selector instanceof HTMLSelectElement)) {
      return
    }

    if (activeElement && activeElement !== document.body && activeElement !== document.documentElement) {
      return
    }

    window.requestAnimationFrame(() => {
      selector.focus({ preventScroll: true })
    })
  }

  const initProjectSwitcher = (shell) => {
    if (shell.dataset.rehearsalProjectSwitcherInitialized === 'true') {
      return
    }

    const form = shell.querySelector('[data-rehearsal-project-switcher]')
    const selector = form?.querySelector('[data-rehearsal-project-selector]')

    if (!(form instanceof HTMLFormElement) || !(selector instanceof HTMLSelectElement)) {
      return
    }

    shell.dataset.rehearsalProjectSwitcherInitialized = 'true'
    selector.addEventListener('change', () => {
      const activeTab = shell.querySelector('[data-rehearsal-tab].is-active')
      const activeCalendarView = shell.querySelector('[data-rehearsal-calendar-view-tab].is-active')
      const activeCalendarViewSelect = shell.querySelector('[data-rehearsal-calendar-view-select]')
      const tabInput = form.querySelector('input[name="rehearsal_tab"]')
      const calendarViewInput = form.querySelector('input[name="rehearsal_calendar_view"]')

      if (tabInput instanceof HTMLInputElement && activeTab instanceof HTMLElement) {
        tabInput.value = activeTab.dataset.rehearsalTab || tabInput.value
      }

      if (calendarViewInput instanceof HTMLInputElement) {
        if (activeCalendarViewSelect instanceof HTMLSelectElement && activeCalendarViewSelect.value) {
          calendarViewInput.value = activeCalendarViewSelect.value
        } else if (activeCalendarView instanceof HTMLElement) {
          calendarViewInput.value = activeCalendarView.dataset.rehearsalCalendarViewTab || calendarViewInput.value
        }
      }

      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit()
        return
      }

      form.submit()
    })
  }

  const initCalendarEventModal = (shell) => {
    if (shell.dataset.rehearsalCalendarModalInitialized === 'true') {
      return
    }

    const modal = shell.querySelector('[data-rehearsal-calendar-event-modal]')
    const title = modal?.querySelector('[data-rehearsal-calendar-event-modal-title]')
    const content = modal?.querySelector('[data-rehearsal-calendar-event-modal-content]')
    const closeControls = modal ? Array.from(modal.querySelectorAll('[data-rehearsal-calendar-event-close]')) : []
    let activeTrigger = null

    if (!modal || !title || !content) {
      return
    }

    shell.dataset.rehearsalCalendarModalInitialized = 'true'
    const closeModal = () => {
      modal.hidden = true
      modal.setAttribute('aria-hidden', 'true')
      modal.classList.remove('is-open')
      content.innerHTML = ''
      title.textContent = ''

      if (activeTrigger instanceof HTMLElement) {
        activeTrigger.focus({ preventScroll: true })
      }

      activeTrigger = null
    }

    const openModal = (trigger) => {
      const detailId = trigger?.dataset?.rehearsalCalendarEventOpen || ''
      const detail = detailId ? document.getElementById(detailId) : null

      if (!detail || !shell.contains(detail)) {
        return
      }

      activeTrigger = trigger
      title.textContent = trigger.dataset.rehearsalCalendarEventTitle || ''
      content.innerHTML = detail.innerHTML
      modal.hidden = false
      modal.setAttribute('aria-hidden', 'false')
      modal.classList.add('is-open')

      window.requestAnimationFrame(() => {
        const closeButton = modal.querySelector('.pd-rehearsal-calendar-modal__close')
        if (closeButton instanceof HTMLElement) {
          closeButton.focus({ preventScroll: true })
        }
      })
    }

    shell.addEventListener('click', (event) => {
      const trigger = event.target instanceof Element
        ? event.target.closest('[data-rehearsal-calendar-event-open]')
        : null

      if (!(trigger instanceof HTMLElement) || !shell.contains(trigger)) {
        return
      }

      event.preventDefault()
      openModal(trigger)
    })

    closeControls.forEach((control) => {
      control.addEventListener('click', closeModal)
    })

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) {
        closeModal()
      }
    })
  }

  const initIntegratedCalendar = (calendar) => {
    if (calendar.dataset.rehearsalIntegratedCalendarInitialized === 'true') {
      return
    }

    const months = Array.from(calendar.querySelectorAll('[data-rehearsal-calendar-month]'))
    const switcher = calendar.querySelector('[data-rehearsal-calendar-month-switcher]')
    const select = calendar.querySelector('[data-rehearsal-calendar-month-select]')
    const prevButton = calendar.querySelector('[data-rehearsal-calendar-month-prev]')
    const nextButton = calendar.querySelector('[data-rehearsal-calendar-month-next]')
    const status = calendar.querySelector('[data-rehearsal-calendar-month-status]')
    const shell = calendar.closest('[data-rehearsal-shell]')
    let activeIndex = 0

    if (!months.length) {
      return
    }

    calendar.dataset.rehearsalIntegratedCalendarInitialized = 'true'

    const resolveMonthIndex = (monthKey) => {
      if (!monthKey) {
        return -1
      }

      return months.findIndex((month) => month.dataset.rehearsalCalendarMonth === monthKey)
    }

    const setActiveMonth = (nextIndex) => {
      activeIndex = Math.max(0, Math.min(months.length - 1, nextIndex))

      months.forEach((month, index) => {
        month.hidden = index !== activeIndex
      })

      const activeMonth = months[activeIndex]
      const activeMonthKey = activeMonth?.dataset?.rehearsalCalendarMonth || ''

      if (select instanceof HTMLSelectElement && activeMonthKey) {
        select.value = activeMonthKey
      }

      if (prevButton instanceof HTMLButtonElement) {
        prevButton.disabled = activeIndex <= 0
      }

      if (nextButton instanceof HTMLButtonElement) {
        nextButton.disabled = activeIndex >= months.length - 1
      }

      if (status instanceof HTMLElement) {
        status.textContent = `${activeIndex + 1} / ${months.length}`
      }

      if (activeMonthKey) {
        writeShellState(shell, 'integrated-calendar-month', activeMonthKey)
      }
    }

    const storedMonth = readShellState(shell, 'integrated-calendar-month')
    const visibleMonth = months.find((month) => !month.hidden)?.dataset?.rehearsalCalendarMonth || ''
    const initialMonth = storedMonth || switcher?.dataset?.rehearsalCalendarInitialMonth || visibleMonth || select?.value || months[0]?.dataset?.rehearsalCalendarMonth || ''
    const initialIndex = resolveMonthIndex(initialMonth)

    setActiveMonth(initialIndex >= 0 ? initialIndex : 0)

    if (prevButton instanceof HTMLButtonElement) {
      prevButton.addEventListener('click', () => {
        setActiveMonth(activeIndex - 1)
      })
    }

    if (nextButton instanceof HTMLButtonElement) {
      nextButton.addEventListener('click', () => {
        setActiveMonth(activeIndex + 1)
      })
    }

    if (select instanceof HTMLSelectElement) {
      select.addEventListener('change', () => {
        const nextIndex = resolveMonthIndex(select.value)
        if (nextIndex >= 0) {
          setActiveMonth(nextIndex)
        }
      })
    }
  }

  const initCardCarousel = (carousel) => {
    if (carousel.dataset.rehearsalCarouselInitialized === 'true') {
      return
    }

    const track = carousel.querySelector('[data-rehearsal-carousel-track]')
    const items = track ? Array.from(track.children).filter((item) => item instanceof HTMLElement) : []
    const prevButton = carousel.querySelector('[data-rehearsal-carousel-prev]')
    const nextButton = carousel.querySelector('[data-rehearsal-carousel-next]')
    const status = carousel.querySelector('[data-rehearsal-carousel-status]')
    let activeIndex = 0

    if (!track || !items.length) {
      return
    }

    carousel.dataset.rehearsalCarouselInitialized = 'true'
    const resolveInitialIndex = () => {
      const now = Date.now() / 1000
      const datedItems = items
        .map((item, index) => ({
          index,
          timestamp: Number.parseInt(item.dataset.rehearsalCarouselTimestamp || '', 10)
        }))
        .filter((entry) => Number.isFinite(entry.timestamp))

      if (!datedItems.length) {
        return 0
      }

      const upcoming = datedItems
        .filter((entry) => entry.timestamp >= now)
        .sort((left, right) => left.timestamp - right.timestamp)

      if (upcoming.length) {
        return upcoming[0].index
      }

      return datedItems
        .sort((left, right) => right.timestamp - left.timestamp)[0].index
    }

    const updateCarousel = (options = {}) => {
      const { shouldFocus = false } = options
      const maxIndex = items.length - 1
      activeIndex = Math.max(0, Math.min(activeIndex, maxIndex))
      track.style.transform = `translateX(-${activeIndex * 100}%)`

      items.forEach((item, index) => {
        const isActive = index === activeIndex
        item.classList.toggle('is-active', isActive)
        item.setAttribute('aria-hidden', isActive ? 'false' : 'true')
        item.setAttribute('tabindex', isActive ? '0' : '-1')

        if ('inert' in item) {
          item.inert = !isActive
        }
      })

      if (prevButton) {
        prevButton.disabled = activeIndex <= 0
      }

      if (nextButton) {
        nextButton.disabled = activeIndex >= maxIndex
      }

      if (status) {
        status.textContent = `${activeIndex + 1} / ${items.length}`
      }

      if (shouldFocus && items[activeIndex] instanceof HTMLElement) {
        items[activeIndex].focus({ preventScroll: true })
      }
    }

    const move = (direction) => {
      activeIndex += direction
      updateCarousel({ shouldFocus: true })
    }

    prevButton?.addEventListener('click', () => {
      move(-1)
    })

    nextButton?.addEventListener('click', () => {
      move(1)
    })

    carousel.addEventListener('keydown', (event) => {
      const target = event.target instanceof Element ? event.target : null
      if (target?.closest('input, textarea, select, [contenteditable="true"]')) {
        return
      }

      if (event.key === 'ArrowLeft') {
        event.preventDefault()
        move(-1)
      }

      if (event.key === 'ArrowRight') {
        event.preventDefault()
        move(1)
      }
    })

    activeIndex = resolveInitialIndex()
    updateCarousel()
  }

  const toggleEmptyState = (list) => {
    const empty = list.querySelector('[data-rehearsal-empty]')
    const rows = Array.from(list.querySelectorAll('[data-rehearsal-slot-row]'))

    if (!empty) {
      return
    }

    empty.hidden = rows.length > 0
  }

  const getFrontendConfig = () => {
    if (!window.wpssbRehearsalFrontend || typeof window.wpssbRehearsalFrontend !== 'object') {
      return null
    }

    return window.wpssbRehearsalFrontend
  }

  const getFrontendDebug = () => {
    const config = getFrontendConfig()
    const debug = config?.debug

    if (!debug || typeof debug !== 'object' || !debug.enabled) {
      return null
    }

    return debug
  }

  const logFrontendDebug = (eventName, details = {}) => {
    const debug = getFrontendDebug()

    if (!debug) {
      return
    }

    try {
      console.log(
        `[WPSS Rehearsals][${debug.requestId || 'frontend'}] ${eventName}`,
        {
          viewerId: debug.viewerId || 0,
          ...details
        }
      )
    } catch (error) {
      // Ignore console serialization issues.
    }
  }

  const supportsBrowserNotifications = () => typeof window !== 'undefined' && 'Notification' in window

  const getBrowserNotificationPreference = (shell) => readShellState(shell, 'browser-notifications')

  const setBrowserNotificationPreference = (shell, value) => {
    writeShellState(shell, 'browser-notifications', value || '')
  }

  const getBrowserNotificationCursor = (shell) => readShellState(shell, 'browser-notifications-cursor')

  const setBrowserNotificationCursor = (shell, value) => {
    writeShellState(shell, 'browser-notifications-cursor', value || '')
  }

  const clearBrowserNotificationTimer = (shell) => {
    if (!shell?._wpssbBrowserNotificationTimerId) {
      return
    }

    window.clearTimeout(shell._wpssbBrowserNotificationTimerId)
    shell._wpssbBrowserNotificationTimerId = null
  }

  const getBrowserNotificationConfig = () => {
    const config = getFrontendConfig()
    return config?.browserNotifications && typeof config.browserNotifications === 'object'
      ? config.browserNotifications
      : {}
  }

  const getBrowserNotificationMessages = () => {
    const config = getBrowserNotificationConfig()
    return config?.messages && typeof config.messages === 'object'
      ? config.messages
      : {}
  }

  const getWebPushConfig = () => {
    const config = getFrontendConfig()
    return config?.webPush && typeof config.webPush === 'object'
      ? config.webPush
      : {}
  }

  const getWebPushMessages = () => {
    const config = getWebPushConfig()
    return config?.messages && typeof config.messages === 'object'
      ? config.messages
      : {}
  }

  const isSecureContextAvailable = () => typeof window !== 'undefined' && window.isSecureContext === true

  const isIosMobile = () => {
    if (typeof navigator === 'undefined') {
      return false
    }

    const userAgent = String(navigator.userAgent || '')
    const platform = String(navigator.platform || '')

    return /iPhone|iPad|iPod/i.test(userAgent)
      || (platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  }

  const isStandaloneDisplay = () => {
    if (typeof window === 'undefined') {
      return false
    }

    try {
      if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
        return true
      }
    } catch (error) {
      // Ignore matchMedia failures.
    }

    return window.navigator?.standalone === true
  }

  const looksLikeEmbeddedMobileBrowser = () => {
    if (typeof navigator === 'undefined') {
      return false
    }

    const userAgent = String(navigator.userAgent || '')

    return /(FBAN|FBAV|Instagram|Line|MicroMessenger|wv\b|WebView)/i.test(userAgent)
  }

  const getUnsupportedNotificationMessage = () => {
    const browserMessages = getBrowserNotificationMessages()
    const webPushMessages = getWebPushMessages()

    if (!isSecureContextAvailable()) {
      return webPushMessages.secureContextRequired
        || browserMessages.secureContextRequired
        || webPushMessages.unsupported
        || browserMessages.unsupported
        || ''
    }

    if (isIosMobile() && !isStandaloneDisplay()) {
      return webPushMessages.iosHomeScreenOnly
        || browserMessages.iosHomeScreenOnly
        || webPushMessages.unsupported
        || browserMessages.unsupported
        || ''
    }

    if (looksLikeEmbeddedMobileBrowser()) {
      return webPushMessages.embeddedBrowserUnsupported
        || browserMessages.embeddedBrowserUnsupported
        || webPushMessages.unsupported
        || browserMessages.unsupported
        || ''
    }

    return webPushMessages.unsupported
      || browserMessages.unsupported
      || ''
  }

  const supportsWebPush = () => supportsBrowserNotifications()
    && typeof navigator !== 'undefined'
    && 'serviceWorker' in navigator
    && typeof window !== 'undefined'
    && 'PushManager' in window

  const canFetchBrowserNotifications = (shell) => {
    const config = getFrontendConfig()
    const projectId = shell?.dataset?.rehearsalProjectId || ''

    return Boolean(
      projectId
      && config?.ajaxUrl
      && config?.browserNotificationsNonce
      && supportsBrowserNotifications()
      && window.Notification.permission === 'granted'
      && getBrowserNotificationPreference(shell) === 'enabled'
    )
  }

  const shouldUseWebPush = (shell) => {
    const config = getWebPushConfig()

    return Boolean(
      config?.enabled
      && config?.publicKey
      && config?.serviceWorkerUrl
      && supportsWebPush()
      && window.Notification.permission === 'granted'
      && getBrowserNotificationPreference(shell) === 'enabled'
    )
  }

  const base64UrlToUint8Array = (value) => {
    const padding = '='.repeat((4 - (value.length % 4)) % 4)
    const base64 = `${value}${padding}`.replace(/-/g, '+').replace(/_/g, '/')
    const rawData = window.atob(base64)
    const outputArray = new Uint8Array(rawData.length)

    for (let index = 0; index < rawData.length; index += 1) {
      outputArray[index] = rawData.charCodeAt(index)
    }

    return outputArray
  }

  const serializePushSubscription = (subscription) => {
    if (!subscription) {
      return null
    }

    if (typeof subscription.toJSON === 'function') {
      return subscription.toJSON()
    }

    const json = {
      endpoint: subscription.endpoint || '',
      expirationTime: subscription.expirationTime || null,
      keys: {}
    }

    try {
      const p256dh = subscription.getKey ? subscription.getKey('p256dh') : null
      const auth = subscription.getKey ? subscription.getKey('auth') : null

      if (p256dh) {
        json.keys.p256dh = window.btoa(String.fromCharCode(...new Uint8Array(p256dh)))
          .replace(/\+/g, '-')
          .replace(/\//g, '_')
          .replace(/=+$/g, '')
      }

      if (auth) {
        json.keys.auth = window.btoa(String.fromCharCode(...new Uint8Array(auth)))
          .replace(/\+/g, '-')
          .replace(/\//g, '_')
          .replace(/=+$/g, '')
      }
    } catch (error) {
      return json
    }

    return json
  }

  const persistWebPushSubscription = async (shell, subscription, actionName, nonce) => {
    const frontendConfig = getFrontendConfig()
    const projectId = shell?.dataset?.rehearsalProjectId || ''
    const serialized = serializePushSubscription(subscription)

    if (!frontendConfig?.ajaxUrl || !projectId || !serialized) {
      throw new Error('Push subscription payload missing.')
    }

    const formData = new FormData()
    formData.append('action', actionName)
    formData.append('nonce', nonce)
    formData.append('project_id', projectId)
    formData.append('subscription', JSON.stringify(serialized))

    const response = await window.fetch(frontendConfig.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    })

    const payload = await response.json().catch(() => null)

    if (!response.ok || !payload?.success) {
      throw new Error(payload?.data?.message || 'Push subscription request failed.')
    }

    return payload?.data && typeof payload.data === 'object' ? payload.data : null
  }

  const ensureWebPushSubscription = async (shell) => {
    const config = getWebPushConfig()
    const messages = getWebPushMessages()

    if (!shouldUseWebPush(shell)) {
      return false
    }

    try {
      const registration = await navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: '/' })
      let subscription = await registration.pushManager.getSubscription()

      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: base64UrlToUint8Array(String(config.publicKey || ''))
        })
      }

      await persistWebPushSubscription(shell, subscription, 'wpssb_save_rehearsal_push_subscription', config.subscriptionNonce)
      shell._wpssbWebPushActive = true
      return true
    } catch (error) {
      shell._wpssbWebPushActive = false
      logFrontendDebug('web_push_subscription_error', {
        projectId: shell?.dataset?.rehearsalProjectId || '',
        message: error?.message || ''
      })
      setBrowserNotificationStatus(shell, `${messages.saveError || ''} ${String(error?.message || '')}`.trim())
      return false
    }
  }

  const disableWebPushSubscription = async (shell) => {
    const config = getWebPushConfig()
    const messages = getWebPushMessages()

    if (!config?.enabled || !supportsWebPush()) {
      shell._wpssbWebPushActive = false
      return
    }

    try {
      const registration = await navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: '/' })
      const subscription = await registration.pushManager.getSubscription()

      if (subscription) {
        await persistWebPushSubscription(shell, subscription, 'wpssb_remove_rehearsal_push_subscription', config.unsubscribeNonce)
      }

      shell._wpssbWebPushActive = false
    } catch (error) {
      logFrontendDebug('web_push_unsubscribe_error', {
        projectId: shell?.dataset?.rehearsalProjectId || '',
        message: error?.message || ''
      })
      setBrowserNotificationStatus(shell, `${messages.removeError || ''} ${String(error?.message || '')}`.trim())
    }
  }

  const getBrowserNotificationStatusElement = (shell) => shell?.querySelector('[data-rehearsal-browser-notification-status]')

  const setBrowserNotificationStatus = (shell, text = '') => {
    const status = getBrowserNotificationStatusElement(shell)
    if (!status) {
      return
    }

    const content = String(text || '').trim()
    status.hidden = !content
    status.textContent = content
  }

  const formatBrowserNotificationCheckDebug = (data) => {
    const messages = getBrowserNotificationMessages()
    const template = String(messages.checkDebug || '')

    if (!template) {
      return ''
    }

    const total = Number.isFinite(Number(data?.total_events)) ? Number(data.total_events) : 0
    const visible = Number.isFinite(Number(data?.visible_event_count)) ? Number(data.visible_event_count) : 0
    const afterCursor = String(data?.after_cursor_gmt || 'sin cursor')
    const latestCursor = String(data?.latest_cursor_gmt || 'sin eventos')

    return template
      .replace('%1$d', String(total))
      .replace('%2$d', String(visible))
      .replace('%3$s', afterCursor)
      .replace('%4$s', latestCursor)
  }

  const shouldPollBrowserNotifications = (shell) => canFetchBrowserNotifications(shell) && !shouldUseWebPush(shell)

  const updateBrowserNotificationUi = (shell) => {
    const panel = shell?.querySelector('[data-rehearsal-browser-notifications]')
    if (!panel) {
      return
    }

    panel.hidden = false

    const message = panel.querySelector('[data-rehearsal-browser-notification-message]')
    const enableButton = panel.querySelector('[data-rehearsal-browser-enable]')
    const dismissButton = panel.querySelector('[data-rehearsal-browser-dismiss]')
    const testButton = panel.querySelector('[data-rehearsal-browser-test]')
    const checkButton = panel.querySelector('[data-rehearsal-browser-check]')
    const disableButton = panel.querySelector('[data-rehearsal-browser-disable]')
    const messages = getBrowserNotificationMessages()
    const preference = getBrowserNotificationPreference(shell)
    const permission = supportsBrowserNotifications() ? window.Notification.permission : 'unsupported'

    if (!supportsBrowserNotifications()) {
      if (message) message.textContent = getUnsupportedNotificationMessage()
      if (enableButton) enableButton.hidden = true
      if (dismissButton) dismissButton.hidden = true
      if (testButton) testButton.hidden = true
      if (checkButton) checkButton.hidden = true
      if (disableButton) disableButton.hidden = true
      setBrowserNotificationStatus(shell, '')
      return
    }

    if (permission === 'denied') {
      if (message) message.textContent = messages.denied || ''
      if (enableButton) enableButton.hidden = true
      if (dismissButton) dismissButton.hidden = true
      if (testButton) testButton.hidden = true
      if (checkButton) checkButton.hidden = true
      if (disableButton) disableButton.hidden = true
      setBrowserNotificationStatus(shell, '')
      return
    }

    if (preference === 'enabled' && permission === 'granted') {
      if (message) {
        message.textContent = shouldUseWebPush(shell)
          ? (getWebPushMessages().enabled || messages.enabled || '')
          : (messages.enabled || '')
      }
      if (enableButton) enableButton.hidden = true
      if (dismissButton) dismissButton.hidden = true
      if (testButton) testButton.hidden = false
      if (checkButton) checkButton.hidden = false
      if (disableButton) disableButton.hidden = false
      if (!getBrowserNotificationStatusElement(shell)?.textContent) {
        setBrowserNotificationStatus(shell, messages.checkIdle || '')
      }
      return
    }

    if (preference === 'disabled') {
      if (message) message.textContent = messages.disabled || ''
      if (enableButton) enableButton.hidden = false
      if (dismissButton) dismissButton.hidden = true
      if (testButton) testButton.hidden = true
      if (checkButton) checkButton.hidden = true
      if (disableButton) disableButton.hidden = true
      setBrowserNotificationStatus(shell, '')
      return
    }

    if (message) {
      message.textContent = getWebPushConfig()?.enabled
        ? (getWebPushMessages().prompt || messages.prompt || '')
        : (messages.prompt || '')
    }
    if (enableButton) enableButton.hidden = false
    if (dismissButton) dismissButton.hidden = false
    if (testButton) testButton.hidden = true
    if (checkButton) checkButton.hidden = true
    if (disableButton) disableButton.hidden = true
    setBrowserNotificationStatus(shell, '')
  }

  const fetchBrowserNotifications = async (shell, options = {}) => {
    const config = getFrontendConfig()
    const projectId = shell?.dataset?.rehearsalProjectId || ''

    if (!config?.ajaxUrl || !config?.browserNotificationsNonce || !projectId) {
      return null
    }

    const formData = new FormData()
    formData.append('action', 'wpssb_get_rehearsal_browser_notifications')
    formData.append('nonce', config.browserNotificationsNonce)
    formData.append('project_id', projectId)

    if (options?.after) {
      formData.append('after', options.after)
    }

    if (options?.prime) {
      formData.append('prime', '1')
    }

    const response = await window.fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    })

    const payload = await response.json().catch(() => null)
    if (!response.ok || !payload?.success) {
      throw new Error(payload?.data?.message || getBrowserNotificationMessages().error || 'Browser notifications request failed.')
    }

    return payload?.data && typeof payload.data === 'object' ? payload.data : null
  }

  const primeBrowserNotifications = async (shell) => {
    try {
      const data = await fetchBrowserNotifications(shell, { prime: true })
      const cursor = typeof data?.latest_cursor_gmt === 'string' ? data.latest_cursor_gmt : ''
      if (cursor) {
        setBrowserNotificationCursor(shell, cursor)
      }
    } catch (error) {
      logFrontendDebug('browser_notifications_prime_error', {
        projectId: shell?.dataset?.rehearsalProjectId || '',
        message: error?.message || ''
      })
    }
  }

  const showBrowserNotification = (shell, event) => {
    if (!supportsBrowserNotifications() || window.Notification.permission !== 'granted' || !event) {
      return
    }

    const projectId = shell?.dataset?.rehearsalProjectId || 'project'
    const url = typeof event?.url === 'string' && event.url ? event.url : window.location.href
    const eventId = String(event?.id || '')
    const sessionId = String(event?.session_id || '')
    const createdAt = String(event?.created_at_gmt || '')
    const notification = new window.Notification(String(event?.title || ''), {
      body: String(event?.body || ''),
      tag: `wpssb-rehearsal:${projectId}:${eventId || createdAt || sessionId || Date.now()}`,
      requireInteraction: true,
      data: { url },
    })

    notification.onclick = () => {
      try {
        window.focus()
      } catch (error) {
        // Ignore focus failures and continue with navigation.
      }

      if (url) {
        window.location.href = url
      }

      notification.close()
    }
  }

  const pollBrowserNotifications = async (shell) => {
    if (!shouldPollBrowserNotifications(shell)) {
      return []
    }

    const after = getBrowserNotificationCursor(shell)
    const data = await fetchBrowserNotifications(shell, { after })
    const events = Array.isArray(data?.events) ? data.events : []
    const latestCursor = typeof data?.latest_cursor_gmt === 'string' ? data.latest_cursor_gmt : ''

    events.forEach((event) => {
      showBrowserNotification(shell, event)
    })

    if (latestCursor) {
      setBrowserNotificationCursor(shell, latestCursor)
    }

    return events
  }

  const scheduleBrowserNotificationPoll = (shell, delayMs = null) => {
    clearBrowserNotificationTimer(shell)

    if (!shouldPollBrowserNotifications(shell)) {
      return
    }

    const config = getBrowserNotificationConfig()
    const interval = Number(config?.pollIntervalMs) > 0 ? Number(config.pollIntervalMs) : 45000
    const waitMs = Number.isFinite(delayMs) && delayMs >= 0 ? delayMs : interval

    shell._wpssbBrowserNotificationTimerId = window.setTimeout(async () => {
      try {
        await pollBrowserNotifications(shell)
      } catch (error) {
        logFrontendDebug('browser_notifications_poll_error', {
          projectId: shell?.dataset?.rehearsalProjectId || '',
          message: error?.message || ''
        })
      } finally {
        scheduleBrowserNotificationPoll(shell)
      }
    }, waitMs)
  }

  const sendBrowserNotificationActivationTest = (shell = null) => {
    const config = getBrowserNotificationConfig()
    const messages = getBrowserNotificationMessages()
    const testTitle = String(config?.test?.title || '')
    const testBody = String(config?.test?.body || '')

    if (!supportsBrowserNotifications() || window.Notification.permission !== 'granted' || !testTitle || !testBody) {
      return
    }

    const notification = new window.Notification(testTitle, { body: testBody, tag: 'wpssb-rehearsal-activation-test' })
    window.setTimeout(() => notification.close(), 5000)
    setBrowserNotificationStatus(shell, messages.testSent || '')
  }

  const checkBrowserNotificationsNow = async (shell) => {
    const messages = getBrowserNotificationMessages()

    if (!canFetchBrowserNotifications(shell)) {
      setBrowserNotificationStatus(shell, messages.checkError || messages.error || '')
      return
    }

    try {
      const data = await fetchBrowserNotifications(shell, { after: getBrowserNotificationCursor(shell) })
      const events = Array.isArray(data?.events) ? data.events : []
      const latestCursor = typeof data?.latest_cursor_gmt === 'string' ? data.latest_cursor_gmt : ''

      events.forEach((event) => {
        showBrowserNotification(shell, event)
      })

      if (latestCursor) {
        setBrowserNotificationCursor(shell, latestCursor)
      }

      const debugLine = formatBrowserNotificationCheckDebug(data)

      if (events.length === 1) {
        setBrowserNotificationStatus(shell, [messages.checkOne || '', debugLine].filter(Boolean).join(' '))
      } else if (events.length > 1) {
        setBrowserNotificationStatus(shell, [String(messages.checkMany || '').replace('%d', String(events.length)), debugLine].filter(Boolean).join(' '))
      } else {
        setBrowserNotificationStatus(shell, [messages.checkEmpty || '', debugLine].filter(Boolean).join(' '))
      }
    } catch (error) {
      logFrontendDebug('browser_notifications_manual_check_error', {
        projectId: shell?.dataset?.rehearsalProjectId || '',
        message: error?.message || ''
      })
      const errorText = error?.message ? `${messages.checkError || messages.error || ''} ${String(error.message)}`.trim() : (messages.checkError || messages.error || '')
      setBrowserNotificationStatus(shell, errorText)
    }
  }

  const enableBrowserNotifications = async (shell) => {
    if (!supportsBrowserNotifications()) {
      updateBrowserNotificationUi(shell)
      return
    }

    let permission = window.Notification.permission

    if (permission !== 'granted') {
      try {
        permission = await window.Notification.requestPermission()
      } catch (error) {
        permission = window.Notification.permission
      }
    }

    if (permission === 'granted') {
      setBrowserNotificationPreference(shell, 'enabled')
      clearBrowserNotificationTimer(shell)
      await primeBrowserNotifications(shell)
      await ensureWebPushSubscription(shell)
      updateBrowserNotificationUi(shell)
      sendBrowserNotificationActivationTest(shell)
      if (!shouldUseWebPush(shell)) {
        scheduleBrowserNotificationPoll(shell, 5000)
      }
      return
    }

    setBrowserNotificationPreference(shell, permission === 'denied' ? 'denied' : 'dismissed')
    clearBrowserNotificationTimer(shell)
    updateBrowserNotificationUi(shell)
  }

  const initBrowserNotifications = (shell) => {
    const panel = shell?.querySelector('[data-rehearsal-browser-notifications]')
    if (!panel || panel.dataset.rehearsalBrowserNotificationsReady === 'true') {
      return
    }

    panel.dataset.rehearsalBrowserNotificationsReady = 'true'

    const enableButton = panel.querySelector('[data-rehearsal-browser-enable]')
    const dismissButton = panel.querySelector('[data-rehearsal-browser-dismiss]')
    const testButton = panel.querySelector('[data-rehearsal-browser-test]')
    const checkButton = panel.querySelector('[data-rehearsal-browser-check]')
    const disableButton = panel.querySelector('[data-rehearsal-browser-disable]')
    const preference = getBrowserNotificationPreference(shell)

    if (supportsBrowserNotifications() && window.Notification.permission === 'granted' && preference !== 'disabled') {
      setBrowserNotificationPreference(shell, 'enabled')
    }

    if (enableButton) {
      enableButton.addEventListener('click', async () => {
        enableButton.disabled = true
        try {
          await enableBrowserNotifications(shell)
        } finally {
          enableButton.disabled = false
        }
      })
    }

    if (dismissButton) {
      dismissButton.addEventListener('click', () => {
        setBrowserNotificationPreference(shell, 'dismissed')
        clearBrowserNotificationTimer(shell)
        updateBrowserNotificationUi(shell)
      })
    }

    if (testButton) {
      testButton.addEventListener('click', () => {
        sendBrowserNotificationActivationTest(shell)
      })
    }

    if (checkButton) {
      checkButton.addEventListener('click', async () => {
        checkButton.disabled = true
        try {
          await checkBrowserNotificationsNow(shell)
        } finally {
          checkButton.disabled = false
        }
      })
    }

    if (disableButton) {
      disableButton.addEventListener('click', async () => {
        setBrowserNotificationPreference(shell, 'disabled')
        clearBrowserNotificationTimer(shell)
        await disableWebPushSubscription(shell)
        updateBrowserNotificationUi(shell)
      })
    }

    updateBrowserNotificationUi(shell)

    if (canFetchBrowserNotifications(shell)) {
      if (shouldUseWebPush(shell)) {
        ensureWebPushSubscription(shell).finally(() => {
          updateBrowserNotificationUi(shell)
        })
      } else if (getBrowserNotificationCursor(shell)) {
        pollBrowserNotifications(shell)
          .catch((error) => {
            logFrontendDebug('browser_notifications_initial_poll_error', {
              projectId: shell?.dataset?.rehearsalProjectId || '',
              message: error?.message || ''
            })
          })
          .finally(() => {
            scheduleBrowserNotificationPoll(shell)
          })
      } else {
        primeBrowserNotifications(shell).finally(() => {
          scheduleBrowserNotificationPoll(shell)
        })
      }
    }
  }

  const getPageLoadDiagnostics = () => {
    let resources = []

    try {
      resources = typeof window.performance?.getEntriesByType === 'function'
        ? window.performance.getEntriesByType('resource')
        : []
    } catch (error) {
      resources = []
    }

    const compactResources = resources.slice(-16).map((entry) => ({
      name: typeof entry.name === 'string' ? entry.name.slice(0, 220) : '',
      initiatorType: entry.initiatorType || '',
      duration: Math.round(entry.duration || 0),
      responseEnd: Math.round(entry.responseEnd || 0),
      transferSize: Number.isFinite(entry.transferSize) ? entry.transferSize : 0
    }))

    return {
      readyState: document.readyState,
      visibilityState: document.visibilityState || '',
      elapsedMs: Math.round(window.performance?.now?.() || 0),
      resourceCount: resources.length,
      recentResources: compactResources,
      snapshot: typeof window.wpssbGetRehearsalSnapshot === 'function'
        ? window.wpssbGetRehearsalSnapshot()
        : null
    }
  }

  let loadWatchdogStarted = false

  const startLoadWatchdog = () => {
    if (loadWatchdogStarted) {
      return
    }

    loadWatchdogStarted = true

    const config = getFrontendConfig()
    const watchdogConfig = config?.loadWatchdog && typeof config.loadWatchdog === 'object'
      ? config.loadWatchdog
      : {}
    const isEnabled = watchdogConfig.enabled === true
    const stopAfterMs = Number.isFinite(Number.parseInt(watchdogConfig.stopAfterMs, 10))
      ? Math.max(500, Number.parseInt(watchdogConfig.stopAfterMs, 10))
      : 2400

    document.documentElement.classList.add('wpssb-rehearsals-js-ready')
    document.body?.classList?.add('wpssb-rehearsals-js-ready')

    logFrontendDebug('page_load_watchdog_start', {
      enabled: isEnabled,
      stopAfterMs,
      readyState: document.readyState
    })

    window.addEventListener(
      'load',
      () => {
        document.documentElement.classList.add('wpssb-rehearsals-load-complete')
        document.body?.classList?.add('wpssb-rehearsals-load-complete')
        logFrontendDebug('page_load_event', getPageLoadDiagnostics())
      },
      { once: true }
    )

    if (!isEnabled) {
      return
    }

    window.setTimeout(() => {
      if (document.readyState === 'complete') {
        logFrontendDebug('page_load_watchdog_complete', getPageLoadDiagnostics())
        return
      }

      logFrontendDebug('page_load_watchdog_pending', getPageLoadDiagnostics())
    }, stopAfterMs)
  }

  const safeScrollIntoView = (element, options = {}) => {
    if (!(element instanceof HTMLElement) || typeof element.scrollIntoView !== 'function') {
      return
    }

    try {
      element.scrollIntoView(options)
    } catch (error) {
      logFrontendDebug('scroll_into_view_error', {
        message: error?.message || '',
        tagName: element.tagName || '',
        className: element.className || ''
      })
    }
  }

  const describeDebugRoot = (root) => {
    if (!(root instanceof HTMLElement)) {
      return {
        tagName: '',
        className: '',
        dataset: {}
      }
    }

    return {
      tagName: root.tagName || '',
      className: root.className || '',
      dataset: { ...root.dataset }
    }
  }

  const getValidationMessages = () => {
    const config = getFrontendConfig()
    return config?.validationMessages || {}
  }

  const parseTimeToMinutes = (value) => {
    if (!value || !/^\d{2}:\d{2}$/.test(value)) {
      return null
    }

    const [hours, minutes] = value.split(':').map((part) => Number.parseInt(part, 10))

    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
      return null
    }

    return (hours * 60) + minutes
  }

  const formatMinutesAsTime = (totalMinutes) => {
    const safeMinutes = Math.max(0, Math.min(1435, totalMinutes))
    const hours = Math.floor(safeMinutes / 60)
    const minutes = safeMinutes % 60

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
  }

  const normalizeTimeToFiveMinutes = (value) => {
    const totalMinutes = parseTimeToMinutes(value)

    if (totalMinutes === null) {
      return value
    }

    return formatMinutesAsTime(Math.round(totalMinutes / 5) * 5)
  }

  const setSlotFeedback = (row, message = '', state = '') => {
    const feedback = row.querySelector('[data-rehearsal-slot-feedback]')

    if (!feedback) {
      return
    }

    feedback.textContent = message
    feedback.dataset.state = state
    feedback.hidden = !message
  }

  const clearSlotRowValidation = (row) => {
    Array.from(row.querySelectorAll('input[type="time"]')).forEach((input) => {
      input.setCustomValidity('')
    })

    setSlotFeedback(row, '', '')
  }

  const isDayPanelBlocked = (panel) => {
    const checkbox = panel?.querySelector('[data-rehearsal-day-block]')
    return checkbox instanceof HTMLInputElement ? checkbox.checked : false
  }

  const validateSlotRow = (row, options = {}) => {
    const { report = false } = options
    const messages = getValidationMessages()
    const startInput = row.querySelector('input[name$="[start]"]')
    const endInput = row.querySelector('input[name$="[end]"]')
    const dayPanel = row.closest('[data-rehearsal-day-panel]')

    if (!(startInput instanceof HTMLInputElement) || !(endInput instanceof HTMLInputElement)) {
      return true
    }

    if (dayPanel && isDayPanelBlocked(dayPanel)) {
      clearSlotRowValidation(row)
      return true
    }

    let feedbackMessage = ''
    let feedbackState = ''
    let isValid = true
    let reportTarget = endInput

    startInput.setCustomValidity('')
    endInput.setCustomValidity('')

    ;[startInput, endInput].forEach((input) => {
      if (!input.value) {
        return
      }

      const normalizedValue = normalizeTimeToFiveMinutes(input.value)

      if (normalizedValue !== input.value) {
        input.value = normalizedValue
        feedbackMessage = messages.rounded || 'Se ajustó automáticamente a bloques de 5 minutos.'
        feedbackState = 'info'
      }
    })

    const startMinutes = parseTimeToMinutes(startInput.value)
    const endMinutes = parseTimeToMinutes(endInput.value)
    const hasStart = !!startInput.value
    const hasEnd = !!endInput.value

    if (!hasStart && !hasEnd) {
      return true
    }

    if (!hasStart || !hasEnd) {
      const message = messages.incompleteRange || 'Completa o elimina este rango antes de guardar.'
      if (!hasStart) {
        startInput.setCustomValidity(message)
        reportTarget = startInput
      }
      if (!hasEnd) {
        endInput.setCustomValidity(message)
        reportTarget = endInput
      }
      feedbackMessage = message
      feedbackState = 'error'
      isValid = false
    }

    if (isValid && hasStart && hasEnd && startMinutes !== null && endMinutes !== null && endMinutes <= startMinutes) {
      const message = messages.invalidRange || 'La hora final debe ser mayor a la hora inicial.'
      endInput.setCustomValidity(message)
      feedbackMessage = message
      feedbackState = 'error'
      isValid = false
    }

    setSlotFeedback(row, feedbackMessage, feedbackState)

    if (report && !isValid) {
      reportTarget.reportValidity()
    }

    return isValid
  }

  const validateDayEditorRows = (dayEditor, options = {}) => {
    let firstInvalid = null

    Array.from(dayEditor.querySelectorAll('[data-rehearsal-day-panel]')).forEach((panel) => {
      const rows = Array.from(panel.querySelectorAll('[data-rehearsal-slot-row]'))

      if (isDayPanelBlocked(panel)) {
        rows.forEach(clearSlotRowValidation)
        return
      }

      rows.forEach((row) => {
        const valid = validateSlotRow(row, options)
        if (!valid && !firstInvalid) {
          firstInvalid = row
        }
      })
    })

    return {
      valid: !firstInvalid,
      firstInvalid
    }
  }

  const updateAvailabilitySubmitState = (form) => {
    if (!form) {
      return true
    }

    const submitButton = form.querySelector('[data-rehearsal-availability-submit], button[type="submit"], input[type="submit"]')
    const messages = getValidationMessages()
    const editors = Array.from(form.querySelectorAll('[data-rehearsal-day-editor]'))

    let firstInvalidRow = null

    editors.forEach((editor) => {
      const result = validateDayEditorRows(editor, { report: false })
      if (!result.valid && !firstInvalidRow) {
        firstInvalidRow = result.firstInvalid
      }
    })

    if (submitButton) {
      const shouldDisable = !!firstInvalidRow
      submitButton.disabled = shouldDisable
      submitButton.setAttribute('aria-disabled', shouldDisable ? 'true' : 'false')

      if ('title' in submitButton) {
        submitButton.title = shouldDisable
          ? (messages.invalidDisabled || messages.invalidSubmit || 'Corrige los horarios marcados antes de guardar tu disponibilidad.')
          : ''
      }
    }

    return !firstInvalidRow
  }

  const dispatchAvailabilityAutosaveChange = (form) => {
    if (!form) {
      return
    }

    form.dispatchEvent(new CustomEvent('rehearsal-availability-change'))
  }

  const initProposalDeleteForm = (form) => {
    if (form.dataset.rehearsalProposalDeleteInitialized === 'true') {
      return
    }

    form.dataset.rehearsalProposalDeleteInitialized = 'true'
    const config = getFrontendConfig()

    form.addEventListener('submit', (event) => {
      if (!config?.deleteConfirm) {
        return
      }

      if (!window.confirm(config.deleteConfirm)) {
        event.preventDefault()
      }
    })
  }

  const initProposalAutosave = (form) => {
    if (form.dataset.rehearsalProposalAutosaveInitialized === 'true') {
      return
    }

    const config = getFrontendConfig()
    const status = form.querySelector('[data-rehearsal-proposal-status]')

    if (!config?.ajaxUrl || !config?.autosaveNonce) {
      return
    }

    form.dataset.rehearsalProposalAutosaveInitialized = 'true'
    let saveTimer = null
    let isSaving = false
    let pendingSave = false
    let lastSerialized = new URLSearchParams(new FormData(form)).toString()

    const setStatus = (message, state = '') => {
      if (!status) {
        return
      }

      status.textContent = message
      status.dataset.state = state
    }

    const serializeForm = () => new URLSearchParams(new FormData(form)).toString()

    const runSave = async () => {
      if (isSaving) {
        pendingSave = true
        return
      }

      const currentSerialized = serializeForm()

      if (currentSerialized === lastSerialized) {
        return
      }

      isSaving = true
      setStatus(config.autosaveMessages?.saving || 'Guardando cambios...', 'saving')

      const payload = new URLSearchParams()
      const formData = new FormData(form)

      payload.set('action', 'wpssb_autosave_frontend_rehearsal_proposal')
      payload.set('nonce', config.autosaveNonce)

      formData.forEach((value, key) => {
        if (key === 'action' || key === 'nonce') {
          return
        }

        if (typeof value === 'string') {
          payload.set(key, value)
        }
      })

      try {
        const response = await window.fetch(config.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload.toString()
        })

        const result = await response.json()

        if (!response.ok || !result?.success) {
          throw new Error(result?.data?.message || config.autosaveMessages?.error || 'No se pudieron guardar los cambios.')
        }

        lastSerialized = currentSerialized
        setStatus(result?.data?.message || config.autosaveMessages?.saved || 'Cambios guardados.', 'saved')
      } catch (error) {
        setStatus(error?.message || config.autosaveMessages?.error || 'No se pudieron guardar los cambios.', 'error')
      } finally {
        isSaving = false
      }
    }

    const scheduleSave = () => {
      if (saveTimer) {
        window.clearTimeout(saveTimer)
      }

      saveTimer = window.setTimeout(() => {
        runSave()
      }, 650)
    }

    form.addEventListener('input', (event) => {
      if (!event.target.closest('input, textarea, select')) {
        return
      }

      scheduleSave()
    })

    form.addEventListener('change', (event) => {
      if (!event.target.closest('input, textarea, select')) {
        return
      }

      scheduleSave()
    })

    form.addEventListener('submit', (event) => {
      event.preventDefault()
      if (saveTimer) {
        window.clearTimeout(saveTimer)
      }

      runSave()
    })
  }

  const initAvailabilityAutosave = (form) => {
    if (form.dataset.rehearsalAvailabilityAutosaveInitialized === 'true') {
      return
    }

    logFrontendDebug('init_availability_autosave_start', {
      formAction: form.getAttribute('action') || '',
      memberPanel: form.closest('[data-rehearsal-member-panel]')?.dataset?.rehearsalMemberPanel || ''
    })

    const config = getFrontendConfig()
    const messages = config?.availabilityAutosaveMessages || {}
    const validationMessages = getValidationMessages()
    const status = form.querySelector('[data-rehearsal-availability-status]')
    const memberPanel = form.closest('[data-rehearsal-member-panel]')
    const updatedLabel = memberPanel?.querySelector('[data-rehearsal-member-updated-label]')
    const memberEditor = memberPanel?.closest('[data-rehearsal-member-editor]')
    const memberId = memberPanel?.dataset.rehearsalMemberPanel || ''
    const memberTab = memberId ? memberEditor?.querySelector(`[data-rehearsal-member-tab="${memberId}"]`) : null
    const memberTabMeta = memberTab?.querySelector('[data-rehearsal-member-tab-meta]')
    const memberTabSummary = memberTab?.querySelector('[data-rehearsal-member-tab-summary]')

    if (!config?.ajaxUrl || !config?.availabilityAutosaveNonce) {
      logFrontendDebug('init_availability_autosave_skipped', {
        reason: 'missing_config',
        hasAjaxUrl: Boolean(config?.ajaxUrl),
        hasNonce: Boolean(config?.availabilityAutosaveNonce)
      })
      return
    }

    form.dataset.rehearsalAvailabilityAutosaveInitialized = 'true'
    logFrontendDebug('init_availability_autosave_ready', {
      memberPanel: memberPanel?.dataset?.rehearsalMemberPanel || '',
      memberId
    })
    let saveTimer = null
    let isSaving = false
    let pendingSave = false
    let lastSerialized = new URLSearchParams(new FormData(form)).toString()
    let memberTabStateTimer = null

    const setStatus = (message, state = '') => {
      if (!status) {
        return
      }

      status.textContent = message
      status.dataset.state = state
    }

    const serializeForm = () => new URLSearchParams(new FormData(form)).toString()

    const setUpdatedLabel = (text = '') => {
      if (!updatedLabel) {
        return
      }

      updatedLabel.textContent = text
      updatedLabel.hidden = !text
    }

    const setMemberTabSummary = (text = '') => {
      if (!memberTabSummary || !text) {
        return
      }

      memberTabSummary.textContent = text
    }

    const setMemberTabState = (state = '') => {
      if (!memberTabMeta) {
        return
      }

      if (state) {
        memberTabMeta.dataset.state = state
        return
      }

      delete memberTabMeta.dataset.state
    }

    const flashMemberTabState = (state = '') => {
      if (!memberTabMeta) {
        return
      }

      if (memberTabStateTimer) {
        window.clearTimeout(memberTabStateTimer)
      }

      setMemberTabState(state)

      if (!state) {
        return
      }

      memberTabStateTimer = window.setTimeout(() => {
        setMemberTabState('')
      }, 2600)
    }

    const setInvalidStatus = () => {
      setStatus(
        messages.invalid || validationMessages.invalidDisabled || validationMessages.invalidSubmit || 'Corrige los horarios marcados para reactivar el autoguardado.',
        'error',
      )
    }

    const runSave = async () => {
      if (isSaving) {
        pendingSave = true
        return
      }

      const currentSerialized = serializeForm()

      if (currentSerialized === lastSerialized) {
        setStatus(messages.idle || 'Tu disponibilidad se guarda automáticamente cuando todos los horarios están completos.', '')
        return
      }

      if (!updateAvailabilitySubmitState(form)) {
        setInvalidStatus()
        return
      }

      isSaving = true
      setStatus(messages.saving || 'Guardando disponibilidad...', 'saving')
      setMemberTabState('saving')

      const payload = new URLSearchParams()
      const formData = new FormData(form)

      payload.set('action', 'wpssb_autosave_frontend_rehearsal_availability')
      payload.set('nonce', config.availabilityAutosaveNonce)

      formData.forEach((value, key) => {
        if (key === 'action' || key === 'redirect_to' || key === 'wpssb_rehearsal_availability_nonce') {
          return
        }

        if (typeof value === 'string') {
          payload.append(key, value)
        }
      })

      try {
        const response = await window.fetch(config.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload.toString()
        })

        const result = await response.json()

        if (!response.ok || !result?.success) {
          throw new Error(result?.data?.message || messages.error || 'No se pudo guardar tu disponibilidad.')
        }

        lastSerialized = currentSerialized
        setStatus(result?.data?.message || messages.saved || 'Disponibilidad guardada.', 'saved')
        setUpdatedLabel(result?.data?.updated_label_text || '')
        setMemberTabSummary(result?.data?.summary_text || '')
        flashMemberTabState('saved')
      } catch (error) {
        setStatus(error?.message || messages.error || 'No se pudo guardar tu disponibilidad.', 'error')
        flashMemberTabState('error')
      } finally {
        isSaving = false

        if (pendingSave) {
          pendingSave = false

          if (updateAvailabilitySubmitState(form)) {
            runSave()
          } else {
            setInvalidStatus()
          }
        }
      }
    }

    const scheduleSave = () => {
      if (saveTimer) {
        window.clearTimeout(saveTimer)
      }

      if (!updateAvailabilitySubmitState(form)) {
        setInvalidStatus()
        return
      }

      const currentSerialized = serializeForm()

      if (currentSerialized === lastSerialized) {
        setStatus(messages.idle || 'Tu disponibilidad se guarda automáticamente cuando todos los horarios están completos.', '')
        return
      }

      saveTimer = window.setTimeout(() => {
        runSave()
      }, 700)
    }

    form.addEventListener('input', (event) => {
      if (!event.target.closest('input, textarea, select')) {
        return
      }

      scheduleSave()
    })

    form.addEventListener('change', (event) => {
      if (!event.target.closest('input, textarea, select')) {
        return
      }

      scheduleSave()
    })

    form.addEventListener('rehearsal-availability-change', () => {
      scheduleSave()
    })

    form.addEventListener('submit', (event) => {
      event.preventDefault()

      if (saveTimer) {
        window.clearTimeout(saveTimer)
      }

      runSave()
    })

    setStatus(messages.idle || 'Tu disponibilidad se guarda automáticamente cuando todos los horarios están completos.', '')
  }

  const initLogbookAutosave = (form) => {
    if (form.dataset.rehearsalLogbookAutosaveInitialized === 'true') {
      return
    }

    const config = getFrontendConfig()
    const messages = config?.logbookAutosaveMessages || {}
    const status = form.querySelector('[data-rehearsal-logbook-status]')

    if (!config?.ajaxUrl || !config?.logbookAutosaveNonce) {
      return
    }

    form.dataset.rehearsalLogbookAutosaveInitialized = 'true'
    let saveTimer = null
    let isSaving = false
    let pendingSave = false
    let lastSerialized = new URLSearchParams(new FormData(form)).toString()

    const setStatus = (message, state = '') => {
      if (!status) {
        return
      }

      status.textContent = message
      status.dataset.state = state
    }

    const serializeForm = () => new URLSearchParams(new FormData(form)).toString()

    const runSave = async () => {
      if (isSaving) {
        pendingSave = true
        return
      }

      const currentSerialized = serializeForm()

      if (currentSerialized === lastSerialized) {
        setStatus(messages.idle || 'Las observaciones y la asistencia se guardan automáticamente mientras escribes.', '')
        return
      }

      isSaving = true
      setStatus(messages.saving || 'Guardando bitácora...', 'saving')

      const payload = new URLSearchParams()
      const formData = new FormData(form)

      payload.set('action', 'wpssb_autosave_frontend_rehearsal_logbook')
      payload.set('nonce', config.logbookAutosaveNonce)

      formData.forEach((value, key) => {
        if (key === 'action' || key === 'redirect_to' || key === 'wpssb_rehearsal_logbook_nonce') {
          return
        }

        if (typeof value === 'string') {
          payload.append(key, value)
        }
      })

      try {
        const response = await window.fetch(config.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload.toString()
        })

        const result = await response.json()

        if (!response.ok || !result?.success) {
          throw new Error(result?.data?.message || messages.error || 'No se pudo guardar la bitácora del ensayo.')
        }

        lastSerialized = currentSerialized
        const successMessage = result?.data?.updated_label_text
          ? `${result?.data?.message || messages.saved || 'Bitácora guardada con éxito.'} · ${result.data.updated_label_text}`
          : (result?.data?.message || messages.saved || 'Bitácora guardada con éxito.')
        setStatus(successMessage, 'saved')
      } catch (error) {
        setStatus(error?.message || messages.error || 'No se pudo guardar la bitácora del ensayo.', 'error')
      } finally {
        isSaving = false

        if (pendingSave) {
          pendingSave = false
          runSave()
        }
      }
    }

    const scheduleSave = () => {
      if (saveTimer) {
        window.clearTimeout(saveTimer)
      }

      const currentSerialized = serializeForm()

      if (currentSerialized === lastSerialized) {
        setStatus(messages.idle || 'Las observaciones y la asistencia se guardan automáticamente mientras escribes.', '')
        return
      }

      saveTimer = window.setTimeout(() => {
        runSave()
      }, 650)
    }

    form.addEventListener('input', (event) => {
      if (!event.target.closest('input, textarea, select')) {
        return
      }

      scheduleSave()
    })

    form.addEventListener('change', (event) => {
      if (!event.target.closest('input, textarea, select')) {
        return
      }

      scheduleSave()
    })

    form.addEventListener('submit', (event) => {
      event.preventDefault()

      if (saveTimer) {
        window.clearTimeout(saveTimer)
      }

      runSave()
    })

    setStatus(messages.idle || 'Las observaciones y la asistencia se guardan automáticamente mientras escribes.', '')
  }

  const syncBlockedDayState = (panel) => {
    const checkbox = panel.querySelector('[data-rehearsal-day-block]')
    const sections = panel.querySelector('[data-rehearsal-day-sections]')
    const note = panel.querySelector('[data-rehearsal-day-blocked-note]')

    if (!checkbox || !sections) {
      return
    }

    const isBlocked = checkbox.checked
    panel.classList.toggle('is-day-blocked', isBlocked)

    if (note) {
      note.hidden = !isBlocked
    }

    sections.hidden = isBlocked

    const controls = Array.from(
      sections.querySelectorAll('input, select, button[data-rehearsal-slot-add], button[data-rehearsal-slot-remove], textarea')
    )

    controls.forEach((control) => {
      control.disabled = isBlocked
    })
  }

  const createRowFromTemplate = (template, indexToken) => {
    const templateMarkup = template.innerHTML.replace(/__INDEX__/g, indexToken)
    const scratch = document.createElement('template')
    scratch.innerHTML = templateMarkup.trim()
    return scratch.content.firstElementChild
  }

  const initDayEditor = (dayEditor) => {
    if (dayEditor.dataset.rehearsalDayEditorInitialized === 'true') {
      return
    }

    const dayTabs = Array.from(dayEditor.querySelectorAll('[data-rehearsal-day-tab]'))
    const dayPanels = Array.from(dayEditor.querySelectorAll('[data-rehearsal-day-panel]'))
    const prevDayButton = dayEditor.querySelector('[data-rehearsal-day-prev]')
    const nextDayButton = dayEditor.querySelector('[data-rehearsal-day-next]')
    const memberEditor = dayEditor.closest('[data-rehearsal-member-editor]')
    const shell = dayEditor.closest('[data-rehearsal-shell]')
    const form = dayEditor.closest('form')

    if (!dayTabs.length || !dayPanels.length) {
      return
    }

    dayEditor.dataset.rehearsalDayEditorInitialized = 'true'
    const setActiveDay = (day, shouldFocus = false) => {
      if (memberEditor) {
        syncMemberEditorDay(memberEditor, day, shouldFocus)
        return
      }

      const activeDay = resolveDayForEditor(dayEditor, day)

      if (!activeDay) {
        return
      }

      activateDayPanel(dayEditor, activeDay, shouldFocus)
      writeShellState(shell, 'day', activeDay)
    }

    const moveDay = (direction) => {
      const activeIndex = dayTabs.findIndex((tab) => tab.classList.contains('is-active'))

      if (activeIndex === -1) {
        return
      }

      const nextIndex = (activeIndex + direction + dayTabs.length) % dayTabs.length
      setActiveDay(dayTabs[nextIndex].dataset.rehearsalDayTab, true)
    }

    const storedDay = !memberEditor ? readShellState(shell, 'day') : ''
    const initialActiveTab = (!memberEditor && storedDay)
      ? dayTabs.find((tab) => tab.dataset.rehearsalDayTab === storedDay) || dayTabs.find((tab) => tab.classList.contains('is-active')) || dayTabs[0]
      : dayTabs.find((tab) => tab.classList.contains('is-active')) || dayTabs[0]

    if (initialActiveTab) {
      if (memberEditor) {
        memberEditor.dataset.rehearsalActiveDay = initialActiveTab.dataset.rehearsalDayTab || memberEditor.dataset.rehearsalActiveDay || ''
      } else {
        setActiveDay(initialActiveTab.dataset.rehearsalDayTab, false)
      }
    }

    dayTabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        setActiveDay(tab.dataset.rehearsalDayTab, false)
      })

      tab.addEventListener('keydown', (event) => {
        const currentIndex = dayTabs.indexOf(tab)

        if (event.key === 'ArrowRight') {
          event.preventDefault()
          const nextIndex = (currentIndex + 1) % dayTabs.length
          setActiveDay(dayTabs[nextIndex].dataset.rehearsalDayTab, true)
        }

        if (event.key === 'ArrowLeft') {
          event.preventDefault()
          const nextIndex = (currentIndex - 1 + dayTabs.length) % dayTabs.length
          setActiveDay(dayTabs[nextIndex].dataset.rehearsalDayTab, true)
        }
      })
    })

    if (prevDayButton) {
      prevDayButton.addEventListener('click', () => {
        moveDay(-1)
      })
    }

    if (nextDayButton) {
      nextDayButton.addEventListener('click', () => {
        moveDay(1)
      })
    }

    dayPanels.forEach((panel) => {
      const lists = Array.from(panel.querySelectorAll('[data-rehearsal-slot-list]'))
      const checkbox = panel.querySelector('[data-rehearsal-day-block]')

      lists.forEach(toggleEmptyState)
      syncBlockedDayState(panel)

      if (checkbox) {
        checkbox.addEventListener('change', () => {
          syncBlockedDayState(panel)
          if (form) {
            updateAvailabilitySubmitState(form)
          }
        })
      }
    })

    dayEditor.addEventListener('click', (event) => {
      const addButton = event.target.closest('[data-rehearsal-slot-add]')
      const removeButton = event.target.closest('[data-rehearsal-slot-remove]')

      if (addButton) {
        event.preventDefault()

        const panel = addButton.closest('[data-rehearsal-day-panel]')
        const slotType = addButton.dataset.rehearsalSlotAdd

        if (!panel || !slotType) {
          return
        }

        const template = panel.querySelector(`[data-rehearsal-slot-template="${slotType}"]`)
        const list = panel.querySelector(`[data-rehearsal-slot-list="${slotType}"]`)

        if (!(template instanceof HTMLTemplateElement) || !list) {
          return
        }

        const row = createRowFromTemplate(template, `slot_${Date.now()}_${Math.round(Math.random() * 1000)}`)

        if (!row) {
          return
        }

        list.appendChild(row)
        toggleEmptyState(list)
        syncBlockedDayState(panel)
        validateSlotRow(row)
        if (form) {
          updateAvailabilitySubmitState(form)
          dispatchAvailabilityAutosaveChange(form)
        }

        const firstInput = row.querySelector('input')
        if (firstInput) {
          firstInput.focus()
        }
      }

      if (removeButton) {
        event.preventDefault()

        const row = removeButton.closest('[data-rehearsal-slot-row]')
        const list = removeButton.closest('[data-rehearsal-slot-list]')

        if (!row || !list) {
          return
        }

        row.remove()
        toggleEmptyState(list)
        if (form) {
          updateAvailabilitySubmitState(form)
          dispatchAvailabilityAutosaveChange(form)
        }
      }
    })

    dayEditor.addEventListener('input', (event) => {
      const input = event.target.closest('input[type="time"]')

      if (!input) {
        return
      }

      const row = input.closest('[data-rehearsal-slot-row]')
      if (!row) {
        return
      }

      validateSlotRow(row, { report: false })
      if (form) {
        updateAvailabilitySubmitState(form)
      }
    })

    dayEditor.addEventListener('change', (event) => {
      const input = event.target.closest('input[type="time"]')

      if (!input) {
        return
      }

      const row = input.closest('[data-rehearsal-slot-row]')
      if (!row) {
        return
      }

      validateSlotRow(row, { report: true })
      if (form) {
        updateAvailabilitySubmitState(form)
      }
    })

    dayEditor.addEventListener('blur', (event) => {
      const input = event.target.closest('input[type="time"]')

      if (!input) {
        return
      }

      const row = input.closest('[data-rehearsal-slot-row]')
      if (!row) {
        return
      }

      validateSlotRow(row, { report: true })
      if (form) {
        updateAvailabilitySubmitState(form)
      }
    }, true)

    if (form && !form.dataset.rehearsalAvailabilityValidated) {
      form.dataset.rehearsalAvailabilityValidated = 'true'

      form.addEventListener('submit', (event) => {
        const messages = getValidationMessages()
        const editors = Array.from(form.querySelectorAll('[data-rehearsal-day-editor]'))

        let firstInvalidRow = null

        editors.forEach((editor) => {
          const result = validateDayEditorRows(editor, { report: false })
          if (!result.valid && !firstInvalidRow) {
            firstInvalidRow = result.firstInvalid
          }
        })

        if (!firstInvalidRow) {
          return
        }

        event.preventDefault()
        const invalidInput = firstInvalidRow.querySelector('input[type="time"]')
        if (invalidInput instanceof HTMLInputElement) {
          invalidInput.reportValidity()
          invalidInput.focus()
        }

        setSlotFeedback(
          firstInvalidRow,
          messages.invalidSubmit || 'Corrige los horarios marcados antes de guardar tu disponibilidad.',
          'error'
        )
      })

      updateAvailabilitySubmitState(form)
    }
  }

  const isHiddenInside = (element, root) => {
    let current = element

    while (current && current !== root) {
      if (current.hidden) {
        return true
      }

      current = current.parentElement
    }

    return false
  }

  const initMemberPanelContent = (panel) => {
    if (!(panel instanceof HTMLElement)) {
      logFrontendDebug('init_member_panel_content_skipped', {
        reason: 'invalid_panel'
      })
      return
    }

    const dayEditors = Array.from(panel.querySelectorAll('[data-rehearsal-day-editor]'))
    const availabilityForms = Array.from(panel.querySelectorAll('[data-rehearsal-availability-autosave]'))

    logFrontendDebug('init_member_panel_content_start', {
      memberPanel: panel.dataset?.rehearsalMemberPanel || '',
      dayEditorCount: dayEditors.length,
      availabilityFormCount: availabilityForms.length
    })

    dayEditors.forEach((element, index) => {
      logFrontendDebug('init_member_panel_day_editor', {
        memberPanel: panel.dataset?.rehearsalMemberPanel || '',
        index,
        dayTabCount: element.querySelectorAll('[data-rehearsal-day-tab]').length
      })
      initDayEditor(element)
    })

    availabilityForms.forEach((element, index) => {
      logFrontendDebug('init_member_panel_availability_form', {
        memberPanel: panel.dataset?.rehearsalMemberPanel || '',
        index
      })
      initAvailabilityAutosave(element)
    })

    logFrontendDebug('init_member_panel_content_complete', {
      memberPanel: panel.dataset?.rehearsalMemberPanel || ''
    })
  }

  const findMemberTemplate = (editor, memberId) => Array.from(editor.querySelectorAll('template[data-rehearsal-member-template]'))
    .find((template) => template.dataset.rehearsalMemberTemplate === String(memberId))

  const ensureMemberPanel = (editor, memberId) => {
    const existingPanel = Array.from(editor.querySelectorAll('[data-rehearsal-member-panel]'))
      .find((panel) => panel.dataset.rehearsalMemberPanel === String(memberId))

    if (existingPanel) {
      logFrontendDebug('ensure_member_panel_existing', {
        memberId
      })
      return existingPanel
    }

    const template = findMemberTemplate(editor, memberId)
    const panelsRoot = editor.querySelector('.pd-rehearsal-member-editor__panels')

    if (!(template instanceof HTMLTemplateElement) || !panelsRoot) {
      logFrontendDebug('ensure_member_panel_missing_template', {
        memberId,
        hasTemplate: template instanceof HTMLTemplateElement,
        hasPanelsRoot: Boolean(panelsRoot)
      })
      return null
    }

    const fragment = template.content.cloneNode(true)
    const panel = fragment.querySelector('[data-rehearsal-member-panel]')

    if (!(panel instanceof HTMLElement)) {
      logFrontendDebug('ensure_member_panel_invalid_fragment', {
        memberId
      })
      return null
    }

    panelsRoot.insertBefore(fragment, template)
    template.remove()

    logFrontendDebug('ensure_member_panel_created', {
      memberId
    })

    return panel
  }

  const initVisiblePanelContent = (root) => {
    if (!(root instanceof HTMLElement)) {
      return
    }

    const memberEditors = Array.from(root.querySelectorAll('[data-rehearsal-member-editor]'))
      .filter((element) => !isHiddenInside(element, root))
    const dayEditors = Array.from(root.querySelectorAll('[data-rehearsal-day-editor]'))
      .filter((element) => !element.closest('[data-rehearsal-member-editor]') && !isHiddenInside(element, root))
    const availabilityForms = Array.from(root.querySelectorAll('[data-rehearsal-availability-autosave]'))
      .filter((element) => !element.closest('[data-rehearsal-member-editor]') && !isHiddenInside(element, root))
    const logbookForms = Array.from(root.querySelectorAll('[data-rehearsal-logbook-autosave]'))
      .filter((element) => !isHiddenInside(element, root))
    const proposalForms = Array.from(root.querySelectorAll('[data-rehearsal-proposal-autosave]'))
      .filter((element) => !isHiddenInside(element, root))
    const deleteForms = Array.from(root.querySelectorAll('[data-rehearsal-proposal-delete]'))
      .filter((element) => !isHiddenInside(element, root))
    const carousels = Array.from(root.querySelectorAll('[data-rehearsal-card-carousel]'))
      .filter((element) => !isHiddenInside(element, root))
    const integratedCalendars = Array.from(root.querySelectorAll('[data-rehearsal-integrated-calendar]'))
      .filter((element) => !isHiddenInside(element, root))

    logFrontendDebug('init_visible_panel_content_start', {
      root: describeDebugRoot(root),
      memberEditorCount: memberEditors.length,
      dayEditorCount: dayEditors.length,
      availabilityFormCount: availabilityForms.length,
      logbookFormCount: logbookForms.length,
      proposalFormCount: proposalForms.length,
      deleteFormCount: deleteForms.length,
      carouselCount: carousels.length,
      integratedCalendarCount: integratedCalendars.length
    })

    memberEditors.forEach((element, index) => {
      logFrontendDebug('init_visible_panel_member_editor', {
        index,
        memberTabCount: element.querySelectorAll('[data-rehearsal-member-tab]').length
      })
      initMemberEditor(element)
    })

    dayEditors.forEach((element, index) => {
      logFrontendDebug('init_visible_panel_day_editor', {
        index,
        dayTabCount: element.querySelectorAll('[data-rehearsal-day-tab]').length
      })
      initDayEditor(element)
    })

    availabilityForms.forEach((element, index) => {
      logFrontendDebug('init_visible_panel_availability_form', { index })
      initAvailabilityAutosave(element)
    })

    logbookForms.forEach(initLogbookAutosave)
    proposalForms.forEach(initProposalAutosave)
    deleteForms.forEach(initProposalDeleteForm)
    carousels.forEach(initCardCarousel)
    integratedCalendars.forEach(initIntegratedCalendar)

    logFrontendDebug('init_visible_panel_content_complete', {
      root: describeDebugRoot(root)
    })
  }

  const initMemberEditor = (memberEditor) => {
    if (memberEditor.dataset.rehearsalMemberEditorInitialized === 'true') {
      return
    }

    const memberTabs = Array.from(memberEditor.querySelectorAll('[data-rehearsal-member-tab]'))
    const prevMemberButton = memberEditor.querySelector('[data-rehearsal-member-prev]')
    const nextMemberButton = memberEditor.querySelector('[data-rehearsal-member-next]')
    const shell = memberEditor.closest('[data-rehearsal-shell]')

    if (!memberTabs.length) {
      return
    }

    logFrontendDebug('init_member_editor_start', {
      memberTabCount: memberTabs.length
    })

    memberEditor.dataset.rehearsalMemberEditorInitialized = 'true'
    const moveMember = (direction) => {
      const activeIndex = memberTabs.findIndex((tab) => tab.classList.contains('is-active'))

      if (activeIndex === -1) {
        return
      }

      const nextIndex = (activeIndex + direction + memberTabs.length) % memberTabs.length
      activateMemberPanel(memberEditor, memberTabs[nextIndex].dataset.rehearsalMemberTab, true)
    }

    const storedMemberId = readShellState(shell, 'member')
    const storedDay = readShellState(shell, 'day')
    const initialActiveTab = memberTabs.find((tab) => tab.classList.contains('is-active')) || memberTabs.find((tab) => tab.dataset.rehearsalMemberTab === storedMemberId) || memberTabs[0]
    const initialMemberId = initialActiveTab?.dataset.rehearsalMemberTab || ''
    const initialPanel = initialMemberId ? ensureMemberPanel(memberEditor, initialMemberId) : null
    const initialDayEditor = initialPanel?.querySelector('[data-rehearsal-day-editor]') || memberEditor.querySelector('[data-rehearsal-day-editor]')

    if (initialActiveTab) {
      logFrontendDebug('init_member_editor_initial_tab', {
        memberId: initialActiveTab.dataset.rehearsalMemberTab || '',
        storedMemberId,
        storedDay
      })

      if (initialDayEditor) {
        memberEditor.dataset.rehearsalActiveDay = getActiveDay(initialDayEditor)
      } else if (storedDay) {
        memberEditor.dataset.rehearsalActiveDay = resolveMemberDay(memberEditor, storedDay)
      }

      if (initialPanel) {
        writeShellState(shell, 'member', initialActiveTab.dataset.rehearsalMemberTab || '')
      }

      logFrontendDebug('init_member_editor_initial_panel_ready', {
        memberId: initialActiveTab.dataset.rehearsalMemberTab || '',
        activeDay: memberEditor.dataset.rehearsalActiveDay || ''
      })
    }

    logFrontendDebug('init_member_editor_complete', {
      activeDay: memberEditor.dataset.rehearsalActiveDay || ''
    })

    const hydrateMemberPanelForTarget = (target) => {
      const source = target instanceof Element ? target : null
      const panel = source?.closest('[data-rehearsal-member-panel]')
        || memberEditor.querySelector('[data-rehearsal-member-panel]:not([hidden])')

      initMemberPanelContent(panel)
    }

    memberEditor.addEventListener('focusin', (event) => {
      hydrateMemberPanelForTarget(event.target)
    })

    memberEditor.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null

      if (target?.closest('[data-rehearsal-member-tab], [data-rehearsal-member-prev], [data-rehearsal-member-next]')) {
        return
      }

      hydrateMemberPanelForTarget(target)
    })

    memberTabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        activateMemberPanel(memberEditor, tab.dataset.rehearsalMemberTab, false)
      })

      tab.addEventListener('keydown', (event) => {
        const currentIndex = memberTabs.indexOf(tab)

        if (event.key === 'ArrowRight') {
          event.preventDefault()
          const nextIndex = (currentIndex + 1) % memberTabs.length
          activateMemberPanel(memberEditor, memberTabs[nextIndex].dataset.rehearsalMemberTab, true)
        }

        if (event.key === 'ArrowLeft') {
          event.preventDefault()
          const nextIndex = (currentIndex - 1 + memberTabs.length) % memberTabs.length
          activateMemberPanel(memberEditor, memberTabs[nextIndex].dataset.rehearsalMemberTab, true)
        }
      })
    })

    if (prevMemberButton) {
      prevMemberButton.addEventListener('click', () => {
        moveMember(-1)
      })
    }

    if (nextMemberButton) {
      nextMemberButton.addEventListener('click', () => {
        moveMember(1)
      })
    }
  }

  logFrontendDebug('bootstrap_start', {
    shellCount: shells.length,
    currentUrl: window.location.href
  })

  shells.forEach((shell, shellIndex) => {
    const projectId = shell?.dataset?.rehearsalProjectId || ''

    try {
      logFrontendDebug('shell_init_start', {
        shellIndex,
        projectId
      })

      const nav = shell.querySelector('[data-rehearsal-tabs]')
      const panelsRoot = shell.querySelector('[data-rehearsal-panels]')

      if (nav && panelsRoot) {
        const tabs = Array.from(nav.querySelectorAll('[data-rehearsal-tab]'))
        const panels = Array.from(panelsRoot.children).filter((panel) => panel.getAttribute('role') === 'tabpanel')

        logFrontendDebug('shell_tabs_detected', {
          shellIndex,
          projectId,
          tabCount: tabs.length,
          panelCount: panels.length
        })

        if (tabs.length && panels.length) {
          const queryKey = nav.dataset.rehearsalQuery || 'rehearsal_tab'
          const storedTab = readShellState(shell, 'tab')

          const activateTab = (tabName, options = {}) => {
            const { shouldFocus = false, syncUrl = true } = options
            const activeTab = tabs.find((tab) => tab.dataset.rehearsalTab === tabName)?.dataset.rehearsalTab || tabs[0]?.dataset.rehearsalTab

            if (!activeTab) {
              return
            }

            tabs.forEach((tab) => {
              const isActive = tab.dataset.rehearsalTab === activeTab
              tab.classList.toggle('is-active', isActive)
              tab.setAttribute('aria-selected', isActive ? 'true' : 'false')
              tab.setAttribute('tabindex', isActive ? '0' : '-1')

              if (isActive && shouldFocus) {
                tab.focus()
              }
            })

            panels.forEach((panel) => {
              panel.hidden = panel.dataset.rehearsalPanel !== activeTab
            })

            const activePanelElement = panels.find((panel) => panel.dataset.rehearsalPanel === activeTab)
            logFrontendDebug('activate_tab_before_visible_content', {
              projectId,
              activeTab,
              panelFound: Boolean(activePanelElement)
            })
            initVisiblePanelContent(activePanelElement)
            logFrontendDebug('activate_tab_after_visible_content', {
              projectId,
              activeTab
            })

            writeShellState(shell, 'tab', activeTab)

            if (syncUrl) {
              updateUrl(queryKey, activeTab)
            }
          }

          const initialTab = tabs.find((tab) => tab.classList.contains('is-active'))
            || (!hasUrlParam(queryKey) && storedTab ? tabs.find((tab) => tab.dataset.rehearsalTab === storedTab) : null)
            || tabs[0]

          if (initialTab) {
            const initialTabName = initialTab.dataset.rehearsalTab || ''
            const initialPanelElement = panels.find((panel) => panel.dataset.rehearsalPanel === initialTabName)

            logFrontendDebug('shell_initial_tab', {
              shellIndex,
              projectId,
              tab: initialTabName,
              panelFound: Boolean(initialPanelElement)
            })

            initVisiblePanelContent(initialPanelElement)
            writeShellState(shell, 'tab', initialTabName)
          }

          tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
              activateTab(tab.dataset.rehearsalTab, { shouldFocus: false })
            })

            tab.addEventListener('keydown', (event) => {
              const currentIndex = tabs.indexOf(tab)

              if (event.key === 'ArrowRight') {
                event.preventDefault()
                const nextIndex = (currentIndex + 1) % tabs.length
                activateTab(tabs[nextIndex].dataset.rehearsalTab, { shouldFocus: true })
              }

              if (event.key === 'ArrowLeft') {
                event.preventDefault()
                const nextIndex = (currentIndex - 1 + tabs.length) % tabs.length
                activateTab(tabs[nextIndex].dataset.rehearsalTab, { shouldFocus: true })
              }
            })
          })
        }
      }

      const calendarToggle = shell.querySelector('[data-rehearsal-calendar-view-tabs]')
      const calendarViewSelect = shell.querySelector('[data-rehearsal-calendar-view-select]')

      if (calendarToggle || calendarViewSelect) {
        const calendarTabs = calendarToggle ? Array.from(calendarToggle.querySelectorAll('[data-rehearsal-calendar-view-tab]')) : []
        const calendarOptions = calendarViewSelect instanceof HTMLSelectElement ? Array.from(calendarViewSelect.options) : []
        const queryKey = calendarToggle?.dataset?.rehearsalQuery || calendarViewSelect?.dataset?.rehearsalQuery || 'rehearsal_calendar_view'
        const storedCalendarView = readShellState(shell, 'calendar-view')
        const activeCalendarTab = calendarTabs.find((tab) => tab.classList.contains('is-active'))
        const storedCalendarTab = !hasUrlParam(queryKey) && storedCalendarView
          ? calendarTabs.find((tab) => tab.dataset.rehearsalCalendarViewTab === storedCalendarView)
          : null
        const storedCalendarOption = !hasUrlParam(queryKey) && storedCalendarView
          ? calendarOptions.find((option) => option.value === storedCalendarView)
          : null
        const initialCalendarView = activeCalendarTab?.dataset?.rehearsalCalendarViewTab
          || storedCalendarTab?.dataset?.rehearsalCalendarViewTab
          || storedCalendarOption?.value
          || (calendarViewSelect instanceof HTMLSelectElement ? calendarViewSelect.value : '')
          || calendarTabs[0]?.dataset?.rehearsalCalendarViewTab
          || calendarOptions[0]?.value
          || ''

        logFrontendDebug('shell_calendar_detected', {
          shellIndex,
          projectId,
          calendarTabCount: calendarTabs.length,
          initialCalendarView
        })

        if (initialCalendarView) {
          activateCalendarView(shell, initialCalendarView, { syncUrl: false })
        }

        calendarTabs.forEach((tab) => {
          tab.addEventListener('click', () => {
            activateCalendarView(shell, tab.dataset.rehearsalCalendarViewTab)
          })

          tab.addEventListener('keydown', (event) => {
            const currentIndex = calendarTabs.indexOf(tab)

            if (event.key === 'ArrowRight') {
              event.preventDefault()
              const nextIndex = (currentIndex + 1) % calendarTabs.length
              activateCalendarView(shell, calendarTabs[nextIndex].dataset.rehearsalCalendarViewTab, { shouldFocus: true })
            }

            if (event.key === 'ArrowLeft') {
              event.preventDefault()
              const nextIndex = (currentIndex - 1 + calendarTabs.length) % calendarTabs.length
              activateCalendarView(shell, calendarTabs[nextIndex].dataset.rehearsalCalendarViewTab, { shouldFocus: true })
            }
          })
        })

        if (calendarViewSelect instanceof HTMLSelectElement) {
          calendarViewSelect.addEventListener('change', () => {
            activateCalendarView(shell, calendarViewSelect.value)
          })
        }
      }

      logFrontendDebug('shell_content_init_start', {
        shellIndex,
        projectId
      })

      initVisiblePanelContent(shell)
      initCollapsiblePanels(shell)
      initProjectSwitcher(shell)
      initBrowserNotifications(shell)
      initCalendarEventModal(shell)
      focusProjectSelector(shell)

      logFrontendDebug('shell_init_complete', {
        shellIndex,
        projectId
      })
    } catch (error) {
      logFrontendDebug('shell_init_error', {
        shellIndex,
        projectId,
        message: error?.message || '',
        stack: error?.stack || ''
      })
    }
  })

  window.requestAnimationFrame(() => {
    logFrontendDebug('bootstrap_first_frame')
    startLoadWatchdog()
  })

  window.setTimeout(() => {
    logFrontendDebug('bootstrap_post_timeout', {
      shellCount: shells.length
    })
  }, 1000)
})()
