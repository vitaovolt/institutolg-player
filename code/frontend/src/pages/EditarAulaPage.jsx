import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { atualizarAula, fetchAula } from '../api/aulas'
import Button from '../components/ui/Button.jsx'
import { useToast } from '../context/ToastContext'

export default function EditarAulaPage() {
  const { aulaId } = useParams()
  const navigate = useNavigate()
  const { show: mostrarToast } = useToast()
  const submittingRef = useRef(false)
  const [aula, setAula] = useState(null)
  const [titulo, setTitulo] = useState('')
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    let cancelled = false
    fetchAula(aulaId)
      .then((payload) => {
        if (cancelled) return
        setAula(payload.data)
        setTitulo(payload.data.titulo || '')
      })
      .catch(() => {
        if (!cancelled) setError('Não foi possível carregar a aula.')
      })
    return () => {
      cancelled = true
    }
  }, [aulaId])

  async function onSubmit(event) {
    event.preventDefault()
    if (submittingRef.current) return

    const nome = titulo.trim()
    if (!nome) {
      setError('Informe o nome da aula.')
      return
    }

    submittingRef.current = true
    setSubmitting(true)
    setError('')

    try {
      await atualizarAula(aulaId, { titulo: nome })
      navigate(`/aulas/${aulaId}`, {
        replace: true,
        state: { toast: 'Nome da aula atualizado.', tone: 'ok' },
      })
    } catch (err) {
      const msg = err.response?.data?.message || err.message || 'Não foi possível salvar. Tente de novo.'
      setError(msg)
      mostrarToast(msg, 'erro')
      submittingRef.current = false
      setSubmitting(false)
    }
  }

  if (error && !aula) {
    return (
      <main className="mx-auto max-w-xl px-5 py-10">
        <p className="font-semibold text-[var(--brand-danger)]">{error}</p>
        <Link to="/biblioteca" className="mt-4 inline-block font-semibold text-[var(--brand-primary)]">
          Voltar à biblioteca
        </Link>
      </main>
    )
  }

  if (!aula) {
    return (
      <main className="mx-auto max-w-xl px-5 py-10">
        <p className="text-[var(--brand-muted)]">Carregando…</p>
      </main>
    )
  }

  return (
    <main className="mx-auto max-w-xl px-5 py-10" data-testid="pagina-editar-aula">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Editar aula
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">{aula.titulo}</h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        O nome no play é atualizado. Se a cópia na pasta compartilhada estiver ligada, o arquivo de lá também é
        renomeado.
      </p>

      <form
        onSubmit={onSubmit}
        className="mt-8 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5"
      >
        <label className="block text-sm font-bold text-[var(--brand-primary)]">
          Nome da aula
          <input
            data-testid="input-titulo-aula"
            type="text"
            value={titulo}
            onChange={(e) => setTitulo(e.target.value)}
            disabled={submitting}
            maxLength={255}
            className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2 text-sm font-semibold text-[var(--brand-ink)]"
          />
        </label>
        {error ? (
          <p className="mt-3 text-sm font-semibold text-[var(--brand-danger)]" role="alert">
            {error}
          </p>
        ) : null}
        <div className="mt-5 flex flex-wrap gap-3">
          <Button type="submit" data-testid="btn-salvar-aula" disabled={submitting}>
            {submitting ? 'Processando…' : 'Salvar'}
          </Button>
          <Link
            to={`/aulas/${aulaId}`}
            className="rounded-lg border border-[var(--brand-line)] px-4 py-3 text-sm font-bold text-[var(--brand-primary)] no-underline"
          >
            Cancelar
          </Link>
        </div>
      </form>
    </main>
  )
}
