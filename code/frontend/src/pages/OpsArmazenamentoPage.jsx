import { useEffect, useState } from 'react'
import { fetchCustoArmazenamento } from '../api/biblioteca'

function formatarUsd(valor) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
  }).format(Number(valor) || 0)
}

function formatarGb(valor) {
  return `${Number(valor || 0).toLocaleString('pt-BR', { maximumFractionDigits: 4 })} GB`
}

export default function OpsArmazenamentoPage() {
  const [custo, setCusto] = useState(null)
  const [error, setError] = useState('')
  const [forbidden, setForbidden] = useState(false)

  useEffect(() => {
    let cancelled = false
    fetchCustoArmazenamento()
      .then((payload) => {
        if (cancelled) return
        setCusto(payload.data)
        setError('')
        setForbidden(false)
      })
      .catch((err) => {
        if (cancelled) return
        if (err.response?.status === 403) {
          setForbidden(true)
          setError('Sem permissão.')
          setCusto(null)
          return
        }
        setForbidden(false)
        setError('Não foi possível carregar o custo de armazenamento.')
        setCusto(null)
      })
    return () => {
      cancelled = true
    }
  }, [])

  return (
    <main className="mx-auto max-w-4xl px-5 py-10" data-testid="pagina-ops-armazenamento">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Ops Educraft
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">
        Custo estimado R2
      </h1>
      <p className="mt-2 max-w-xl text-[var(--brand-muted)]">
        Visão interna do storage dos vídeos. Capas ficam de fora. Não aparece no menu do Instituto.
      </p>

      {forbidden ? (
        <p className="mt-8 font-semibold text-[var(--brand-danger)]" data-testid="ops-sem-permissao">
          Sem permissão.
        </p>
      ) : null}
      {!forbidden && error ? <p className="mt-8 font-semibold text-[var(--brand-danger)]">{error}</p> : null}
      {!forbidden && !error && !custo ? <p className="mt-8 text-[var(--brand-muted)]">Carregando…</p> : null}

      {custo ? (
        <>
          <section className="relative mt-8 overflow-hidden rounded-3xl bg-[var(--brand-primary)] p-6 text-white shadow-[0_18px_40px_rgba(16,5,107,0.28)]">
            <p className="m-0 text-xs font-extrabold tracking-[0.16em] uppercase text-[var(--brand-accent)]">
              Storage Standard (estimado)
            </p>
            <p
              className="mt-2 text-5xl font-extrabold tracking-tight text-[var(--brand-accent)]"
              data-testid="ops-usd-storage"
            >
              {formatarUsd(custo.usd_storage_estimado)}
              <span className="ml-2 text-lg font-bold text-white/80">/ mês</span>
            </p>
            <p className="mt-3 text-sm text-white/85">
              {formatarGb(custo.gb_videos)} de vídeo − {formatarGb(custo.free_tier_gb)} free tier ×{' '}
              {formatarUsd(custo.usd_por_gb)}/GB
            </p>
          </section>

          <section className="mt-6 grid gap-4 sm:grid-cols-3">
            <article
              data-testid="ops-kpi-videos"
              className="rounded-2xl border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5"
            >
              <p className="m-0 text-[0.7rem] font-extrabold tracking-[0.16em] uppercase text-[var(--brand-muted)]">
                Vídeos
              </p>
              <p className="mt-2 text-3xl font-extrabold text-[var(--brand-primary)]">{custo.videos}</p>
            </article>
            <article
              data-testid="ops-kpi-gb"
              className="rounded-2xl border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5"
            >
              <p className="m-0 text-[0.7rem] font-extrabold tracking-[0.16em] uppercase text-[var(--brand-muted)]">
                Volume
              </p>
              <p className="mt-2 text-3xl font-extrabold text-[var(--brand-primary)]">
                {formatarGb(custo.gb_videos)}
              </p>
            </article>
            <article
              data-testid="ops-kpi-bytes"
              className="rounded-2xl border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5"
            >
              <p className="m-0 text-[0.7rem] font-extrabold tracking-[0.16em] uppercase text-[var(--brand-muted)]">
                Bytes (MP4)
              </p>
              <p className="mt-2 text-3xl font-extrabold text-[var(--brand-primary)]">
                {Number(custo.bytes_videos || 0).toLocaleString('pt-BR')}
              </p>
            </article>
          </section>

          <p className="mt-6 text-sm text-[var(--brand-muted)]" data-testid="ops-aviso">
            {custo.aviso}
          </p>
        </>
      ) : null}
    </main>
  )
}
