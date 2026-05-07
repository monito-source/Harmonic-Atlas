(function () {
  const shells = Array.from(document.querySelectorAll('[data-rehearsal-shell]'))

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

      if (isActive && typeof tab.scrollIntoView === 'function') {
        tab.scrollIntoView({
          behavior: shouldFocus ? 'smooth' : 'auto',
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
    const activeDay = resolveMemberDay(memberEditor, day)

    if (!activeDay) {
      return
    }

    memberEditor.dataset.rehearsalActiveDay = activeDay
    writeShellState(memberEditor.closest('[data-rehearsal-shell]'), 'day', activeDay)

    Array.from(memberEditor.querySelectorAll('[data-rehearsal-member-panel]')).forEach((panel) => {
      const dayEditor = panel.querySelector('[data-rehearsal-day-editor]')

      if (!dayEditor) {
        return
      }

      activateDayPanel(dayEditor, activeDay, shouldFocus && !panel.hidden)
    })
  }

  const activateMemberPanel = (editor, memberId, shouldFocus = false) => {
    const tabs = Array.from(editor.querySelectorAll('[data-rehearsal-member-tab]'))
    const panels = Array.from(editor.querySelectorAll('[data-rehearsal-member-panel]'))
    const activeMemberId = resolveMemberId(editor, memberId)

    if (!activeMemberId) {
      return
    }

    const currentPanel = panels.find((panel) => !panel.hidden)
    const currentDayEditor = currentPanel?.querySelector('[data-rehearsal-day-editor]')
    const activeDay = editor.dataset.rehearsalActiveDay || (currentDayEditor ? getActiveDay(currentDayEditor) : '')

    tabs.forEach((tab) => {
      const isActive = tab.dataset.rehearsalMemberTab === activeMemberId
      tab.classList.toggle('is-active', isActive)
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false')
      tab.setAttribute('tabindex', isActive ? '0' : '-1')

      if (isActive && shouldFocus) {
        tab.focus()
      }

      if (isActive && typeof tab.scrollIntoView === 'function') {
        tab.scrollIntoView({
          behavior: shouldFocus ? 'smooth' : 'auto',
          block: 'nearest',
          inline: 'nearest'
        })
      }
    })

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.rehearsalMemberPanel !== activeMemberId
    })

    writeShellState(editor.closest('[data-rehearsal-shell]'), 'member', activeMemberId)

    if (activeDay) {
      syncMemberEditorDay(editor, activeDay, false)
    }
  }

  const activateCalendarView = (shell, viewName, options = {}) => {
    const { shouldFocus = false, syncUrl = true } = options
    const nav = shell.querySelector('[data-rehearsal-calendar-view-tabs]')
    const tabs = Array.from(shell.querySelectorAll('[data-rehearsal-calendar-view-tab]'))
    const panels = Array.from(shell.querySelectorAll('[data-rehearsal-calendar-view-panel]'))
    const activeView = tabs.find((tab) => tab.dataset.rehearsalCalendarViewTab === viewName)?.dataset.rehearsalCalendarViewTab || tabs[0]?.dataset.rehearsalCalendarViewTab

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

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.rehearsalCalendarViewPanel !== activeView
    })

    writeShellState(shell, 'calendar-view', activeView)

    if (nav && syncUrl) {
      updateUrl(nav.dataset.rehearsalQuery || 'rehearsal_calendar_view', activeView)
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
    const form = shell.querySelector('[data-rehearsal-project-switcher]')
    const selector = form?.querySelector('[data-rehearsal-project-selector]')

    if (!(form instanceof HTMLFormElement) || !(selector instanceof HTMLSelectElement)) {
      return
    }

    selector.addEventListener('change', () => {
      const activeTab = shell.querySelector('[data-rehearsal-tab].is-active')
      const activeCalendarView = shell.querySelector('[data-rehearsal-calendar-view-tab].is-active')
      const tabInput = form.querySelector('input[name="rehearsal_tab"]')
      const calendarViewInput = form.querySelector('input[name="rehearsal_calendar_view"]')

      if (tabInput instanceof HTMLInputElement && activeTab instanceof HTMLElement) {
        tabInput.value = activeTab.dataset.rehearsalTab || tabInput.value
      }

      if (calendarViewInput instanceof HTMLInputElement && activeCalendarView instanceof HTMLElement) {
        calendarViewInput.value = activeCalendarView.dataset.rehearsalCalendarViewTab || calendarViewInput.value
      }

      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit()
        return
      }

      form.submit()
    })
  }

  const initCalendarEventModal = (shell) => {
    const modal = shell.querySelector('[data-rehearsal-calendar-event-modal]')
    const title = modal?.querySelector('[data-rehearsal-calendar-event-modal-title]')
    const content = modal?.querySelector('[data-rehearsal-calendar-event-modal-content]')
    const closeControls = modal ? Array.from(modal.querySelectorAll('[data-rehearsal-calendar-event-close]')) : []
    let activeTrigger = null

    if (!modal || !title || !content) {
      return
    }

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

  const initCardCarousel = (carousel) => {
    const track = carousel.querySelector('[data-rehearsal-carousel-track]')
    const items = track ? Array.from(track.children).filter((item) => item instanceof HTMLElement) : []
    const prevButton = carousel.querySelector('[data-rehearsal-carousel-prev]')
    const nextButton = carousel.querySelector('[data-rehearsal-carousel-next]')
    const status = carousel.querySelector('[data-rehearsal-carousel-status]')
    let activeIndex = 0

    if (!track || !items.length) {
      return
    }

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
    const config = getFrontendConfig()
    const status = form.querySelector('[data-rehearsal-proposal-status]')

    if (!config?.ajaxUrl || !config?.autosaveNonce) {
      return
    }

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
      return
    }

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
    const config = getFrontendConfig()
    const messages = config?.logbookAutosaveMessages || {}
    const status = form.querySelector('[data-rehearsal-logbook-status]')

    if (!config?.ajaxUrl || !config?.logbookAutosaveNonce) {
      return
    }

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
      setActiveDay(initialActiveTab.dataset.rehearsalDayTab, false)
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

  const initMemberEditor = (memberEditor) => {
    const memberTabs = Array.from(memberEditor.querySelectorAll('[data-rehearsal-member-tab]'))
    const memberPanels = Array.from(memberEditor.querySelectorAll('[data-rehearsal-member-panel]'))
    const prevMemberButton = memberEditor.querySelector('[data-rehearsal-member-prev]')
    const nextMemberButton = memberEditor.querySelector('[data-rehearsal-member-next]')
    const shell = memberEditor.closest('[data-rehearsal-shell]')

    if (!memberTabs.length || !memberPanels.length) {
      return
    }

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
    const initialActiveTab = memberTabs.find((tab) => tab.dataset.rehearsalMemberTab === storedMemberId) || memberTabs.find((tab) => tab.classList.contains('is-active')) || memberTabs[0]
    const initialMemberId = initialActiveTab?.dataset.rehearsalMemberTab || ''
    const initialDayEditor = (initialMemberId ? memberEditor.querySelector(`[data-rehearsal-member-panel="${initialMemberId}"] [data-rehearsal-day-editor]`) : null) || memberEditor.querySelector('[data-rehearsal-day-editor]')

    if (initialActiveTab) {
      if (storedDay) {
        memberEditor.dataset.rehearsalActiveDay = resolveMemberDay(memberEditor, storedDay)
      } else if (initialDayEditor) {
        memberEditor.dataset.rehearsalActiveDay = getActiveDay(initialDayEditor)
      }

      activateMemberPanel(memberEditor, initialActiveTab.dataset.rehearsalMemberTab, false)
    }

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

  shells.forEach((shell) => {
    const nav = shell.querySelector('[data-rehearsal-tabs]')
    const panelsRoot = shell.querySelector('[data-rehearsal-panels]')

    if (nav && panelsRoot) {
      const tabs = Array.from(nav.querySelectorAll('[data-rehearsal-tab]'))
      const panels = Array.from(panelsRoot.querySelectorAll('[role="tabpanel"]'))

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

          writeShellState(shell, 'tab', activeTab)

          if (syncUrl) {
            updateUrl(queryKey, activeTab)
          }
        }

        const initialTab = (!hasUrlParam(queryKey) && storedTab)
          ? tabs.find((tab) => tab.dataset.rehearsalTab === storedTab) || tabs.find((tab) => tab.classList.contains('is-active')) || tabs[0]
          : tabs.find((tab) => tab.classList.contains('is-active')) || tabs[0]

        if (initialTab) {
          activateTab(initialTab.dataset.rehearsalTab, { syncUrl: false })
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

    if (calendarToggle) {
      const calendarTabs = Array.from(calendarToggle.querySelectorAll('[data-rehearsal-calendar-view-tab]'))
      const queryKey = calendarToggle.dataset.rehearsalQuery || 'rehearsal_calendar_view'
      const storedCalendarView = readShellState(shell, 'calendar-view')
      const activeCalendarTab = calendarTabs.find((tab) => tab.classList.contains('is-active')) || calendarTabs[0]
      const initialCalendarTab = (!hasUrlParam(queryKey) && storedCalendarView)
        && activeCalendarTab?.dataset.rehearsalCalendarViewTab !== 'overview'
        ? calendarTabs.find((tab) => tab.dataset.rehearsalCalendarViewTab === storedCalendarView) || activeCalendarTab
        : activeCalendarTab

      if (initialCalendarTab) {
        activateCalendarView(shell, initialCalendarTab.dataset.rehearsalCalendarViewTab, { syncUrl: false })
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
    }

    Array.from(shell.querySelectorAll('[data-rehearsal-member-editor]')).forEach(initMemberEditor)
    Array.from(shell.querySelectorAll('[data-rehearsal-day-editor]')).forEach(initDayEditor)
    Array.from(shell.querySelectorAll('[data-rehearsal-availability-autosave]')).forEach(initAvailabilityAutosave)
    Array.from(shell.querySelectorAll('[data-rehearsal-logbook-autosave]')).forEach(initLogbookAutosave)
    Array.from(shell.querySelectorAll('[data-rehearsal-proposal-autosave]')).forEach(initProposalAutosave)
    Array.from(shell.querySelectorAll('[data-rehearsal-proposal-delete]')).forEach(initProposalDeleteForm)
    Array.from(shell.querySelectorAll('[data-rehearsal-card-carousel]')).forEach(initCardCarousel)
    initProjectSwitcher(shell)
    initCalendarEventModal(shell)
    focusProjectSelector(shell)
  })
})()
