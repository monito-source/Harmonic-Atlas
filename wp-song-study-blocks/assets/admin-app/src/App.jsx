import { StateProvider } from './app/StateProvider.jsx'
import AppShell from './app/AppShell.jsx'

function App({ wpData, view }) {
  return (
    <StateProvider wpData={wpData} view={view}>
      <AppShell />
    </StateProvider>
  )
}

export default App
