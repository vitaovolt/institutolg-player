import { useEffect, useState } from 'react'
import { fetchResumoMes } from '../api/biblioteca'
import {
  competenciaAtual,
  formatarCompetencia,
  formatarCompetenciaExtenso,
  formatarReais,
} from '../services/formatar'

function CartaoKpi({ rotulo, valor, detalhe, testId }) {
  return (
    <article
      data-testid={testId}
      className="rounded-2xl border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5 shadow-[0_10px_30px_rgba(16,5,107,0.06)]"
    >
      <p className="m-0 text-[0.7rem] font-extrabold tracking-[0.16em] uppercase text-[var(--brand-muted)]">{rotulo}</p>
      <p className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-primary)]">{valor}</p>
      {detalhe ? <p className="mt-1 text-sm text-[var(--brand-muted)]">{detalhe}</p> : null}
    </article>
  )
}

export default function CustosPage() {
  const [mes, setMes] = useState(competenciaAtual)
  const [resumo, setResumo] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false
    fetchResumoMes(mes)
      .then((payload) => {
        if (cancelled) return
        setResumo(payload.data)
        setError('')
      })
      .catch(() => {
        if (!cancelled) setError('Não foi possível carregar os custos. Tente de novo.')
      })
    return () => {
      cancelled = true
    }
  }, [mes])

  const maxBarra = Math.max(1, ...(resumo?.aulas_por_mes || []).map((item) => item.enviadas))

  return (
    <main className="mx-auto max-w-4xl px-5 py-10" data-testid="pagina-custos">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">Custos</p>
          <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">Painel da mensalidade</h1>
          <p className="mt-2 max-w-xl text-[var(--brand-muted)]">
            R$ 287 do painel + R$ 3,80 por vídeo enviado. Capa não entra na cobrança. Publicar só libera o
            aluno; não muda a cobrança.
          </p>
        </div>
        <label className="text-sm font-bold text-[var(--brand-primary)]">
          Mês
          <input
            data-testid="filtro-mes"
            type="month"
            value={mes}
            onChange={(e) => setMes(e.target.value)}
            className="mt-1 block rounded-lg border border-[var(--brand-line)] bg-[var(--brand-surface)] px-3 py-2 font-semibold text-[var(--brand-ink)]"
          />
        </label>
      </div>

      {error ? <p className="mt-8 font-semibold text-[var(--brand-danger)]">{error}</p> : null}
      {!error && !resumo ? <p className="mt-8 text-[var(--brand-muted)]">Carregando…</p> : null}

      {resumo ? (
        <>
          <section className="relative mt-8 overflow-hidden rounded-3xl bg-[var(--brand-primary)] p-6 text-white shadow-[0_18px_40px_rgba(16,5,107,0.28)]">
            <div className="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[var(--brand-accent)]/30 blur-2xl" />
            <p className="m-0 text-xs font-extrabold tracking-[0.16em] uppercase text-[var(--brand-accent)]">
              {formatarCompetenciaExtenso(resumo.competencia)}
            </p>
            <p className="mt-2 text-sm text-white/80">Custo estimado com os vídeos enviados agora</p>
            <p className="mt-1 text-5xl font-extrabold tracking-tight text-[var(--brand-accent)]" data-testid="custo-total">
              {formatarReais(resumo.total)}
            </p>
            <p className="mt-3 text-sm text-white/85">
              {formatarReais(resumo.mensalidade_painel)} do painel + {resumo.total_importadas} ×{' '}
              {formatarReais(resumo.preco_aula_publicada)}
            </p>
          </section>

          <section className="mt-6 grid gap-4 sm:grid-cols-3">
            <CartaoKpi
              testId="kpi-enviadas"
              rotulo={`Enviadas em ${formatarCompetencia(resumo.competencia)}`}
              valor={resumo.enviadas}
              detalhe={`${resumo.enviadas_nao_publicadas} ainda sem publicar`}
            />
            <CartaoKpi
              testId="kpi-publicadas"
              rotulo="Publicadas agora"
              valor={resumo.publicadas}
              detalhe="Aluno assiste na Eduq"
            />
            <CartaoKpi
              testId="kpi-importadas"
              rotulo="Na cobrança"
              valor={resumo.total_importadas}
              detalhe={`${formatarReais(resumo.valor_aulas_publicadas)} na linha de vídeos`}
            />
          </section>

          <section className="mt-8 rounded-2xl border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5">
            <h2 className="m-0 text-lg font-extrabold text-[var(--brand-primary)]">Vídeos enviados por mês</h2>
            <p className="mt-1 text-sm text-[var(--brand-muted)]">Últimos 12 meses até {formatarCompetencia(resumo.competencia)}.</p>
            <div className="mt-6 flex h-48 items-end gap-2 overflow-x-auto pb-2" data-testid="grafico-aulas-mes">
              {(resumo.aulas_por_mes || []).map((item) => {
                const altura = Math.max(6, Math.round((item.enviadas / maxBarra) * 140))
                const atual = item.competencia === resumo.competencia
                return (
                  <div key={item.competencia} className="flex min-w-[2.4rem] flex-1 flex-col items-center gap-2">
                    <span className="text-xs font-extrabold text-[var(--brand-ink)]">{item.enviadas}</span>
                    <div
                      className={`w-full max-w-8 rounded-t-lg ${atual ? 'bg-[var(--brand-accent)]' : 'bg-[var(--brand-primary)]/80'}`}
                      style={{ height: `${altura}px` }}
                      title={`${formatarCompetencia(item.competencia)}: ${item.enviadas}`}
                    />
                    <span className="text-[0.65rem] font-bold text-[var(--brand-muted)]">
                      {formatarCompetencia(item.competencia)}
                    </span>
                  </div>
                )
              })}
            </div>
          </section>
        </>
      ) : null}
    </main>
  )
}
