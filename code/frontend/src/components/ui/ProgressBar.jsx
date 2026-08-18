export default function ProgressBar({ value }) {
  const pct = Math.max(0, Math.min(100, Number(value) || 0))

  return (
    <div
      className="h-2 w-full overflow-hidden rounded-full bg-[var(--brand-line)]"
      role="progressbar"
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={pct}
      data-testid="barra-upload"
    >
      <div
        className="h-full rounded-full bg-[var(--brand-accent)] transition-[width] duration-150"
        style={{ width: `${pct}%` }}
      />
    </div>
  )
}
