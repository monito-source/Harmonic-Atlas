import { createContext, useContext, useEffect, useMemo, useReducer } from 'react'
import { createApi } from './api.js'
import { buildInitialState, persistReadingPreferences, reducer } from './state.js'

const AppStateContext = createContext(null)

export function StateProvider({ wpData, view, children }) {
  const [state, dispatch] = useReducer(reducer, buildInitialState(wpData, view))
  const api = useMemo(() => createApi(wpData), [wpData])
  const currentUserId = Number(wpData?.currentUserId) || 0

  useEffect(() => {
    persistReadingPreferences(currentUserId, {
      readingMode: state.readingMode,
      readingFollowStructure: state.readingFollowStructure,
      readingShowNotes: state.readingShowNotes,
      readingDoubleColumn: state.readingDoubleColumn,
      readingInstrument: state.readingInstrument,
      readingTransposeTarget: state.readingTransposeTarget,
    })
  }, [
    currentUserId,
    state.readingMode,
    state.readingFollowStructure,
    state.readingShowNotes,
    state.readingDoubleColumn,
    state.readingInstrument,
    state.readingTransposeTarget,
  ])

  const value = useMemo(
    () => ({
      state,
      dispatch,
      api,
      wpData,
    }),
    [state, dispatch, api, wpData],
  )

  return <AppStateContext.Provider value={value}>{children}</AppStateContext.Provider>
}

export function useAppState() {
  const ctx = useContext(AppStateContext)
  if (!ctx) {
    throw new Error('useAppState must be used within StateProvider')
  }
  return ctx
}
