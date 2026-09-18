import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import './index.css'
import App from './App.jsx'
import { AuthProvider } from './context/AuthContext'
import { ToastProvider } from './context/ToastContext'

// Même configuration que l'application mobile (mobile-app/app/_layout.tsx) :
// staleTime généreux, une seule relance, pas de refetch au retour de focus —
// une page d'administration n'a pas besoin de revalider à chaque clic de
// fenêtre, et le cache GET maison (api/cache.js) gérait déjà l'invalidation
// après écriture pour les pages non encore migrées.
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000,
      retry: 1,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: 0,
    },
  },
})

createRoot(document.getElementById('root')).render(
  <QueryClientProvider client={queryClient}>
    <ToastProvider>
      <AuthProvider>
        <App />
      </AuthProvider>
    </ToastProvider>
  </QueryClientProvider>,
)
