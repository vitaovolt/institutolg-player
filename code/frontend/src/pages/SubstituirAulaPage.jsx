import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { fetchAula } from '../api/aulas'
import ProgressBar from '../components/ui/ProgressBar.jsx'
import { useToast } from '../context/ToastContext'
import { substituirAulaCompleto } from '../services/uploadAula'
import { validarArquivoNoCliente } from '../services/validarMp4'

export default function SubstituirAulaPage() {
  const { aulaId } = useParams()
  const navigate = useNavigate()
  const { show: mostrarToast } = useToast()
  const submittingRef = useRef(false)
  const abortRef = useRef(null)
  const [aula, setAula] = useState(null)
  const [file, setFile] = useState(null)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [progresso, setProgresso] = useState(0)

  useEffect(() => {
    let cancelled = false
    fetchAula(aulaId)
      .then((payload) => {
        if (!cancelled) setAula(payload.data)
      })
      .catch(() => {
        if (!cancelled) setError('Não foi possível carregar a aula.')
      })
    return () => {
      cancelled = true
    }
  }, [aulaId])

  function escolherArquivo(event) {
    const escolhido = event.target.files?.[0] || null
    const recusa = escolhido ? validarArquivoNoCliente(escolhido) : null
    if (recusa) {
      setFile(null)
      event.target.value = ''
      setError(recusa)
      mostrarToast(recusa, 'erro')
      return
    }
    setFile(escolhido)
    setError('')
  }

  async function onSubmit(event) {
    event.preventDefault()
    if (submittingRef.current) return
    const recusa = validarArquivoNoCliente(file)
    if (recusa) {
      setError(recusa)
      mostrarToast(recusa, 'erro')
      return
    }

    submittingRef.current = true
    setSubmitting(true)
    setError('')
    setProgresso(0)
    abortRef.current = new AbortController()

    try {
      await substituirAulaCompleto({
        aulaId,
        file,
        onProgress: setProgresso,
        signal: abortRef.current.signal,
      })
      navigate(`/aulas/${aulaId}`, {
        replace: true,
        state: { toast: 'Vídeo substituído. O HTML da Eduq continua o mesmo.', tone: 'ok' },
      })
    } catch (err) {
      if (err?.name === 'AbortError') {
        submittingRef.current = false
        setSubmitting(false)
        return
      }
      submittingRef.current = false
      setSubmitting(false)
      const msg = err.response?.data?.message || err.message || 'Não foi possível substituir o vídeo.'
      setError(msg)
      mostrarToast(msg, 'erro')
    }
  }

  if (!aula && !error) {
    return (
      <main className="mx-auto max-w-xl px-5 py-10">
        <p className="text-[var(--brand-muted)]">Carregando…</p>
      </main>
    )
  }

  return (
    <main className="mx-auto max-w-xl px-5 py-10" data-testid="pagina-substituir">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Substituir vídeo
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">{aula?.titulo || 'Aula'}</h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        O HTML colado na Eduq não muda. Envie o novo export MP4 da aula pronta (até 35 GB).
      </p>

      {error && !aula ? (
        <p className="mt-6 font-semibold text-[var(--brand-danger)]">{error}</p>
      ) : (
        <form
          onSubmit={onSubmit}
          className="mt-8 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5"
        >
          <label className="block text-sm font-bold text-[var(--brand-primary)]">
            Arquivo de vídeo (MP4)
            <input
              data-testid="arquivo-mp4"
              type="file"
              accept=".mp4,video/mp4"
              onChange={escolherArquivo}
              disabled={submitting}
              className="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--brand-accent)] file:px-3 file:py-2 file:font-bold"
            />
          </label>
          {submitting ? (
            <div className="mt-4">
              <ProgressBar value={progresso} />
              <p className="mt-2 text-sm font-semibold text-[var(--brand-muted)]">Enviando… {progresso}%</p>
            </div>
          ) : null}
          {error ? (
            <p className="mt-3 text-sm font-semibold text-[var(--brand-danger)]" role="alert">
              {error}
            </p>
          ) : null}
          <div className="mt-5 flex flex-wrap gap-3">
            <button
              type="submit"
              disabled={submitting}
              className="rounded-lg bg-[var(--brand-accent)] px-4 py-3 text-sm font-extrabold text-[var(--brand-ink)] disabled:opacity-70"
            >
              {submitting ? 'Processando…' : 'Substituir vídeo'}
            </button>
            <Link
              to={`/aulas/${aulaId}`}
              className="rounded-lg border border-[var(--brand-line)] px-4 py-3 text-sm font-bold text-[var(--brand-primary)] no-underline"
            >
              Cancelar
            </Link>
          </div>
        </form>
      )}
    </main>
  )
}
