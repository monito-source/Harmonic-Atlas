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

  const activateDayPanel = (editor, day, shouldFocus = false) => {
    const tabs = Array.from(editor.querySelectorAll('[data-rehearsal-day-tab]'))
    const panels = Array.from(editor.querySelectorAll('[data-rehearsal-day-panel]'))

    tabs.forEach((tab) => {
      const isActive = tab.dataset.rehearsalDayTab === day
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
      panel.hidden = panel.dataset.rehearsalDayPanel !== day
    })
  }

  const getActiveDay = (editor) => {
    const activeTab = editor.querySelector('[data-rehearsal-day-tab].is-active')
    const fallbackTab = editor.querySelector('[data-rehearsal-day-tab]')

    return activeTab?.dataset.rehearsalDayTab || fallbackTab?.dataset.rehearsalDayTab || ''
  }

  const syncMemberEditorDay = (memberEditor, day, shouldFocus = false) => {
    if (!day) {
      return
    }

    memberEditor.dataset.rehearsalActiveDay = day

    Array.from(memberEditor.querySelectorAll('[data-rehearsal-member-panel]')).forEach((panel) => {
      const dayEditor = panel.querySelector('[data-rehearsal-day-editor]')

      if (!dayEditor) {
        return
      }

      activateDayPanel(dayEditor, day, shouldFocus && !panel.hidden)
    })
  }

  const activateMemberPanel = (editor, memberId, shouldFocus = false) => {
    const tabs = Array.from(editor.querySelectorAll('[data-rehearsal-member-tab]'))
    const panels = Array.from(editor.querySelectorAll('[data-rehearsal-member-panel]'))
    const currentPanel = panels.find((panel) => !panel.hidden)
    const currentDayEditor = currentPanel?.querySelector('[data-rehearsal-day-editor]')
    const activeDay = editor.dataset.rehearsalActiveDay || (currentDayEditor ? getActiveDay(currentDayEditor) : '')

    tabs.forEach((tab) => {
      const isActive = tab.dataset.rehearsalMemberTab === memberId
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
      panel.hidden = panel.dataset.rehearsalMemberPanel !== memberId
    })

    if (activeDay) {
      syncMemberEditorDay(editor, activeDay, false)
    }
  }

  const activateCalendarView = (shell, viewName, shouldFocus = false) => {
    const nav = shell.querySelector('[data-rehearsal-calendar-view-tabs]')
    const tabs = Array.from(shell.querySelectorAll('[data-rehearsal-calendar-view-tab]'))
    const panels = Array.from(shell.querySelectorAll('[data-rehearsal-calendar-view-panel]'))

    tabs.forEach((tab) => {
      const isActive = tab.dataset.rehearsalCalendarViewTab === viewName
      tab.classList.toggle('is-active', isActive)
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false')
      tab.setAttribute('tabindex', isActive ? '0' : '-1')

      if (isActive && shouldFocus) {
        tab.focus()
      }
    })

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.rehearsalCalendarViewPanel !== viewName
    })

    if (nav) {
      updateUrl(nav.dataset.rehearsalQuery || 'rehearsal_calendar_view', viewName)
    }
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
      sections.querySelectorAll('select, button[data-rehearsal-slot-add], button[data-rehearsal-slot-remove]')
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

    if (!dayTabs.length || !dayPanels.length) {
      return
    }

    const setActiveDay = (day, shouldFocus = false) => {
      if (memberEditor) {
        syncMemberEditorDay(memberEditor, day, shouldFocus)
        return
      }

      activateDayPanel(dayEditor, day, shouldFocus)
    }

    const moveDay = (direction) => {
      const activeIndex = dayTabs.findIndex((tab) => tab.classList.contains('is-active'))

      if (activeIndex === -1) {
        return
      }

      const nextIndex = (activeIndex + direction + dayTabs.length) % dayTabs.length
      setActiveDay(dayTabs[nextIndex].dataset.rehearsalDayTab, true)
    }

    const initialActiveTab = dayTabs.find((tab) => tab.classList.contains('is-active')) || dayTabs[0]

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

        const firstSelect = row.querySelector('select')
        if (firstSelect) {
          firstSelect.focus()
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
      }
    })
  }

  const initMemberEditor = (memberEditor) => {
    const memberTabs = Array.from(memberEditor.querySelectorAll('[data-rehearsal-member-tab]'))
    const memberPanels = Array.from(memberEditor.querySelectorAll('[data-rehearsal-member-panel]'))
    const prevMemberButton = memberEditor.querySelector('[data-rehearsal-member-prev]')
    const nextMemberButton = memberEditor.querySelector('[data-rehearsal-member-next]')

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

    const initialActiveTab = memberTabs.find((tab) => tab.classList.contains('is-active')) || memberTabs[0]
    const initialDayEditor = memberEditor.querySelector('[data-rehearsal-member-panel]:not([hidden]) [data-rehearsal-day-editor]') || memberEditor.querySelector('[data-rehearsal-day-editor]')

    if (initialActiveTab) {
      if (initialDayEditor) {
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

        const activateTab = (tabName, shouldFocus = false) => {
          tabs.forEach((tab) => {
            const isActive = tab.dataset.rehearsalTab === tabName
            tab.classList.toggle('is-active', isActive)
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false')
            tab.setAttribute('tabindex', isActive ? '0' : '-1')

            if (isActive && shouldFocus) {
              tab.focus()
            }
          })

          panels.forEach((panel) => {
            panel.hidden = panel.dataset.rehearsalPanel !== tabName
          })

          updateUrl(queryKey, tabName)
        }

        tabs.forEach((tab) => {
          tab.addEventListener('click', () => {
            activateTab(tab.dataset.rehearsalTab, false)
          })

          tab.addEventListener('keydown', (event) => {
            const currentIndex = tabs.indexOf(tab)

            if (event.key === 'ArrowRight') {
              event.preventDefault()
              const nextIndex = (currentIndex + 1) % tabs.length
              activateTab(tabs[nextIndex].dataset.rehearsalTab, true)
            }

            if (event.key === 'ArrowLeft') {
              event.preventDefault()
              const nextIndex = (currentIndex - 1 + tabs.length) % tabs.length
              activateTab(tabs[nextIndex].dataset.rehearsalTab, true)
            }
          })
        })
      }
    }

    const calendarToggle = shell.querySelector('[data-rehearsal-calendar-view-tabs]')

    if (calendarToggle) {
      const calendarTabs = Array.from(calendarToggle.querySelectorAll('[data-rehearsal-calendar-view-tab]'))
      const initialCalendarTab = calendarTabs.find((tab) => tab.classList.contains('is-active')) || calendarTabs[0]

      if (initialCalendarTab) {
        activateCalendarView(shell, initialCalendarTab.dataset.rehearsalCalendarViewTab, false)
      }

      calendarTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
          activateCalendarView(shell, tab.dataset.rehearsalCalendarViewTab, false)
        })

        tab.addEventListener('keydown', (event) => {
          const currentIndex = calendarTabs.indexOf(tab)

          if (event.key === 'ArrowRight') {
            event.preventDefault()
            const nextIndex = (currentIndex + 1) % calendarTabs.length
            activateCalendarView(shell, calendarTabs[nextIndex].dataset.rehearsalCalendarViewTab, true)
          }

          if (event.key === 'ArrowLeft') {
            event.preventDefault()
            const nextIndex = (currentIndex - 1 + calendarTabs.length) % calendarTabs.length
            activateCalendarView(shell, calendarTabs[nextIndex].dataset.rehearsalCalendarViewTab, true)
          }
        })
      })
    }

    Array.from(shell.querySelectorAll('[data-rehearsal-day-editor]')).forEach(initDayEditor)
    Array.from(shell.querySelectorAll('[data-rehearsal-member-editor]')).forEach(initMemberEditor)
    Array.from(shell.querySelectorAll('[data-rehearsal-proposal-autosave]')).forEach(initProposalAutosave)
    Array.from(shell.querySelectorAll('[data-rehearsal-proposal-delete]')).forEach(initProposalDeleteForm)
  })
})()
