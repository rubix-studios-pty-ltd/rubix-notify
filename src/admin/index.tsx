import { createRoot } from '@wordpress/element'

import { App } from './components/app'

const container = document.getElementById('rubix-notify')

if (container) {
  createRoot(container).render(<App />)
}
