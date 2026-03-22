import { createRoot } from 'react-dom/client'
import './index.css'
import '../../cancion-dashboard.css'
import App from './App.jsx'

const container = document.getElementById('wpss-cancion-app')

if (container) {
  const wpData = window.WPSS || {}
  const view = container.dataset.view || 'dashboard'

  container.innerHTML = ''

  createRoot(container).render(<App wpData={wpData} view={view} />)
}
