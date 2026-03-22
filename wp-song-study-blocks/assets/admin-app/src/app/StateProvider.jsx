import { createContext, useContext, useMemo, useReducer } from 'react'
import { createApi } from './api.js'
import { buildInitialState, reducer } from './state.js'

const AppStateContext = createContext(null)

export function StateProvider({ wpData, view, children }) {
  const [state, dispatch] = useReducer(reducer, buildInitialState(wpData, view))
  const api = useMemo(() => createApi(wpData), [wpData])

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
