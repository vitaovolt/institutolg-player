const BASE =
  'inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-extrabold leading-none disabled:opacity-70'

const VARIANTES = {
  primary: 'bg-[var(--brand-accent)] text-[var(--brand-ink)] hover:brightness-110',
  secondary:
    'border border-[var(--brand-line)] bg-[var(--brand-surface)] text-[var(--brand-primary)] hover:bg-[var(--brand-bg)]',
  ghost: 'bg-transparent text-[var(--brand-primary)] hover:bg-[var(--brand-bg)]',
  danger:
    'border border-[var(--brand-danger)] bg-[var(--brand-surface)] text-[var(--brand-danger)] hover:bg-[#FDECEC]',
}

export function classesBotao(variant = 'primary', extra = '') {
  return `${BASE} ${VARIANTES[variant] || VARIANTES.primary} ${extra}`.trim()
}

export default function Button({
  variant = 'primary',
  type = 'button',
  disabled = false,
  className = '',
  children,
  ...props
}) {
  return (
    <button type={type} disabled={disabled} className={classesBotao(variant, className)} {...props}>
      {children}
    </button>
  )
}

