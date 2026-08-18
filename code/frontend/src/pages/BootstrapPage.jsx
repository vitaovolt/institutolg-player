import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchHealth } from '../api/health'
import logo from '../assets/logo-instituto-lg.svg'

export default function BootstrapPage() {
  const [health, setHealth] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false
    fetchHealth()
      .then((data) => {
        if (!cancelled) setHealth(data)
      })
      .catch(() => {
        if (!cancelled) setError('Não foi possível falar com a API. Suba o backend em :8000.')
      })
    return () => {
      cancelled = true
    }
  }, [])

  return (
    <main className="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-5 py-12">
      <img src={logo} alt="Instituto LG" className="h-12 w-auto" />
      <p className="mt-6 m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Biblioteca de aulas
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">
        Painel da coordenação
      </h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        F1 — API da árvore Curso → Turma → Disciplina → Aula. Login e envio de MP4 entram nas próximas fases.
      </p>
      <p className="mt-3">
        <Link className="font-semibold text-[var(--brand-primary)] underline" to="/login">
          Ir para o login
        </Link>
      </p>

      <section className="mt-8 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4 shadow-[0_10px_30px_rgba(16,5,107,0.08)]">
        <p className="m-0 text-[0.7rem] font-extrabold tracking-wider uppercase text-[var(--brand-muted)]">
          Health da API
        </p>
        {error ? (
          <p className="mt-2 font-semibold text-[var(--brand-danger)]">{error}</p>
        ) : !health ? (
          <p className="mt-2 text-[var(--brand-muted)]">Consultando /api/v1/health…</p>
        ) : (
          <>
            <p className="mt-2 text-lg font-extrabold text-[var(--brand-primary)]">Bootstrap OK</p>
            <pre className="mt-2 overflow-auto rounded-lg bg-[var(--brand-primary)] p-3 text-xs text-[#E8FBFB]">
{JSON.stringify(health, null, 2)}
            </pre>
          </>
        )}
      </section>
    </main>
  )
}
