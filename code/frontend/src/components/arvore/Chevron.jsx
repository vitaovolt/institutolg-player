export default function Chevron({ aberto }) {
  return (
    <span className={`inline-block text-[var(--brand-muted)] transition-transform ${aberto ? 'rotate-90' : ''}`} aria-hidden>
      ▸
    </span>
  )
}
