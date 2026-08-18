import { createContext, useCallback, useContext, useMemo, useState } from 'react'
import Toast from '../components/ui/Toast.jsx'

const ToastContext = createContext(null)

export function ToastProvider({ children }) {
  const [toast, setToast] = useState(null)

  const hide = useCallback(() => setToast(null), [])
  const show = useCallback((message, tone = 'ok') => {
    setToast({ message, tone, at: Date.now() })
  }, [])

  const value = useMemo(() => ({ show, hide }), [show, hide])

  return (
    <ToastContext.Provider value={value}>
      {children}
      <Toast
        key={toast?.at}
        message={toast?.message}
        tone={toast?.tone}
        onClose={hide}
        autoHideMs={toast?.tone === 'erro' ? 10000 : 7000}
      />
    </ToastContext.Provider>
  )
}

export function useToast() {
  const ctx = useContext(ToastContext)
  if (!ctx) {
    throw new Error('useToast precisa do ToastProvider')
  }
  return ctx
}
