import { useEffect } from 'react'
import { createPortal } from 'react-dom'

export default function Toast({ message, tone = 'ok', onClose, autoHideMs = 7000 }) {
  useEffect(() => {
    if (!message || !onClose || !autoHideMs) return undefined
    const timer = setTimeout(() => onClose(), autoHideMs)
    return () => clearTimeout(timer)
  }, [message, autoHideMs, onClose])

  if (!message) return null

  const cor = tone === 'erro' ? 'var(--brand-danger)' : 'var(--brand-ok)'

  return createPortal(
    <div
      data-testid="toast"
      role="status"
      className="fixed left-1/2 top-16 z-[80] flex w-[min(36rem,calc(100%-2rem))] -translate-x-1/2 items-start gap-3 rounded-lg border bg-[var(--brand-surface)] px-4 py-3 text-sm font-semibold shadow-[0_10px_30px_rgba(16,5,107,0.12)]"
      style={{ borderColor: cor, color: cor }}
    >
      <p className="m-0 flex-1">{message}</p>
      {onClose ? (
        <button
          type="button"
          data-testid="toast-fechar"
          aria-label="Fechar aviso"
          onClick={onClose}
          className="shrink-0 rounded px-1.5 text-lg leading-none font-bold opacity-70 hover:opacity-100"
        >
          ×
        </button>
      ) : null}
    </div>,
    document.body,
  )
}
