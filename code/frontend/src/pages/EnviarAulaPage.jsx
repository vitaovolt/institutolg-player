import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { fetchDisciplina, reprocessarEnvio, salvarCapa } from '../api/aulas'
import ProgressBar from '../components/ui/ProgressBar.jsx'
import Button from '../components/ui/Button.jsx'
import { useToast } from '../context/ToastContext'
import { enviarAulaCompleto, esperarAulaPronta } from '../services/uploadAula'
import { validarCapaNoCliente } from '../services/validarCapa'
import { rotuloStatusPreparo, validarArquivoNoCliente } from '../services/validarMp4'

export default function EnviarAulaPage() {
  const { disciplinaId } = useParams()
  const navigate = useNavigate()
  const { show: mostrarToast } = useToast()
  const submittingRef = useRef(false)
  const abortRef = useRef(null)
  const chaveRef = useRef(crypto.randomUUID())

  const [disciplina, setDisciplina] = useState(null)
  const [loadError, setLoadError] = useState('')
  const [titulo, setTitulo] = useState('')
  const [file, setFile] = useState(null)
  const [arquivoCapa, setArquivoCapa] = useState(null)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [progresso, setProgresso] = useState(0)
  const [fase, setFase] = useState('form')
  const [statusPreparo, setStatusPreparo] = useState('')
  const [aulaId, setAulaId] = useState(null)

  useEffect(() => {
    let cancelled = false
    fetchDisciplina(disciplinaId)
      .then((payload) => {
        if (!cancelled) setDisciplina(payload.data)
      })
      .catch(() => {
        if (!cancelled) setLoadError('Não foi possível carregar a disciplina. Volte à biblioteca e tente de novo.')
      })
    return () => {
      cancelled = true
    }
  }, [disciplinaId])

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

  function escolherCapa(event) {
    const escolhido = event.target.files?.[0] || null
    if (!escolhido) {
      setArquivoCapa(null)
      return
    }
    const recusa = validarCapaNoCliente(escolhido)
    if (recusa) {
      setArquivoCapa(null)
      event.target.value = ''
      setError(recusa)
      mostrarToast(recusa, 'erro')
      return
    }
    setArquivoCapa(escolhido)
    setError('')
  }

  function irParaBibliotecaDaTurma(aulaEnviadaId, toastMsg = 'Aula enviada. Acompanhe o status na turma.') {
    const turmaId = disciplina?.turma?.id
    const params = new URLSearchParams()
    if (turmaId) params.set('turma', String(turmaId))
    params.set('disciplina', String(disciplinaId))
    if (aulaEnviadaId) params.set('aula', String(aulaEnviadaId))
    navigate(`/biblioteca?${params.toString()}`, {
      replace: true,
      state: { toast: toastMsg, tone: 'ok' },
    })
  }

  async function onSubmit(event) {
    event.preventDefault()
    if (submittingRef.current) return

    const tituloTrim = titulo.trim()
    if (!tituloTrim) {
      setError('Informe o título da aula.')
      return
    }
    const recusa = validarArquivoNoCliente(file)
    if (recusa) {
      setError(recusa)
      mostrarToast(recusa, 'erro')
      return
    }
    const recusaCapa = validarCapaNoCliente(arquivoCapa)
    if (recusaCapa) {
      setError(recusaCapa)
      mostrarToast(recusaCapa, 'erro')
      return
    }

    submittingRef.current = true
    setSubmitting(true)
    setError('')
    setFase('enviando')
    setStatusPreparo('enviando')
    setProgresso(0)
    abortRef.current = new AbortController()

    try {
      const { aulaId: id } = await enviarAulaCompleto({
        disciplinaId,
        titulo: tituloTrim,
        file,
        chaveIdempotencia: chaveRef.current,
        onProgress: setProgresso,
        signal: abortRef.current.signal,
      })
      setAulaId(id)
      setProgresso(100)
      setFase('preparando')
      setStatusPreparo('preparando')
      let toastMsg = 'Aula enviada. Acompanhe o status na turma.'
      if (arquivoCapa) {
        try {
          await salvarCapa(id, arquivoCapa)
        } catch {
          toastMsg = 'Aula enviada. A capa não foi salva — você pode adicionar no detalhe da aula.'
        }
      }
      irParaBibliotecaDaTurma(id, toastMsg)
    } catch (err) {
      if (err?.name === 'AbortError') {
        submittingRef.current = false
        setSubmitting(false)
        setFase('form')
        return
      }
      submittingRef.current = false
      setSubmitting(false)
      setFase(err?.aula?.status_preparo === 'erro' ? 'erro' : 'form')
      setStatusPreparo(err?.aula?.status_preparo || '')
      const msg =
        err?.aula?.mensagem_erro ||
        err.response?.data?.errors?.titulo?.[0] ||
        err.response?.data?.message ||
        err.message ||
        'Não foi possível enviar a aula. Tente de novo.'
      setError(msg)
      mostrarToast(msg, 'erro')
      chaveRef.current = crypto.randomUUID()
    }
  }

  async function tentarDeNovo() {
    if (submittingRef.current) return
    submittingRef.current = true
    setSubmitting(true)
    setError('')
    setFase('preparando')
    setStatusPreparo('preparando')
    try {
      if (!aulaId) throw new Error('Não encontramos a aula para tentar de novo.')
      const payload = await reprocessarEnvio(aulaId)
      await esperarAulaPronta(payload.data.id)
      irParaBibliotecaDaTurma(payload.data.id, 'Aula pronta para a biblioteca.')
    } catch (err) {
      submittingRef.current = false
      setSubmitting(false)
      setFase('erro')
      const msg = err.response?.data?.message || err.message || 'Não foi possível tentar de novo.'
      setError(msg)
      mostrarToast(msg, 'erro')
    }
  }

  function onCancelar() {
    abortRef.current?.abort()
    navigate('/biblioteca')
  }

  const cursoNome = disciplina?.turma?.curso?.nome || ''
  const turmaNome = disciplina?.turma?.nome || ''
  const ocupado = submitting
  const textoBotao = ocupado ? 'Processando…' : 'Enviar aula'

  if (loadError) {
    return (
      <main className="mx-auto max-w-xl px-5 py-10">
        <p className="font-semibold text-[var(--brand-danger)]">{loadError}</p>
        <Link to="/biblioteca" className="mt-4 inline-block font-semibold text-[var(--brand-primary)]">
          Voltar à biblioteca
        </Link>
      </main>
    )
  }

  if (!disciplina) {
    return (
      <main className="mx-auto max-w-xl px-5 py-10">
        <p className="text-[var(--brand-muted)]">Carregando…</p>
      </main>
    )
  }

  return (
    <main className="mx-auto max-w-xl px-5 py-10">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Enviar aula
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">
        {disciplina.nome}
      </h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        {cursoNome} · {turmaNome}. Só o export MP4 da aula pronta — até 35 GB. Não envie o arquivo de edição do programa.
        Depois do envio você volta à biblioteca desta turma.
      </p>

      <form
        onSubmit={onSubmit}
        className="mt-8 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5 shadow-[0_10px_30px_rgba(16,5,107,0.08)]"
      >
        <label className="block text-sm font-bold text-[var(--brand-primary)]">
          Título da aula
          <input
            type="text"
            value={titulo}
            onChange={(e) => setTitulo(e.target.value)}
            disabled={ocupado}
            className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2.5 font-medium text-[var(--brand-ink)] outline-none focus:border-[var(--brand-primary)]"
            placeholder="Ex.: Aula 04 — Novo tema"
            required
          />
        </label>

        <label className="mt-4 block text-sm font-bold text-[var(--brand-primary)]">
          Arquivo de vídeo (MP4)
          <input
            data-testid="arquivo-mp4"
            type="file"
            accept=".mp4,video/mp4"
            onChange={escolherArquivo}
            disabled={ocupado}
            className="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--brand-accent)] file:px-3 file:py-2 file:font-bold"
          />
          <span className="mt-1 block text-xs font-medium text-[var(--brand-muted)]">
            {file ? `${file.name} · ${(file.size / (1024 * 1024)).toFixed(1)} MB` : 'Arraste ou clique para escolher o MP4.'}
          </span>
        </label>

        <label className="mt-4 block text-sm font-bold text-[var(--brand-primary)]">
          Foto de capa (opcional)
          <input
            data-testid="arquivo-capa"
            type="file"
            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
            onChange={escolherCapa}
            disabled={ocupado}
            className="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--brand-accent)] file:px-3 file:py-2 file:font-bold"
          />
          <span className="mt-1 block text-xs font-medium text-[var(--brand-muted)]">
            {arquivoCapa
              ? `${arquivoCapa.name} · ${(arquivoCapa.size / 1024).toFixed(0)} KB`
              : 'JPG ou PNG em paisagem, até 2 MB. Aparece no player antes de assistir.'}
          </span>
        </label>

        {(fase === 'enviando' || fase === 'preparando') && (
          <div className="mt-4">
            <ProgressBar value={fase === 'enviando' ? progresso : 100} />
            <p className="mt-2 text-sm font-semibold text-[var(--brand-muted)]" data-testid="status-preparo">
              {fase === 'enviando' ? `Enviando… ${progresso}%` : rotuloStatusPreparo(statusPreparo || fase)}
            </p>
          </div>
        )}

        {error ? (
          <p className="mt-3 text-sm font-semibold text-[var(--brand-danger)]" role="alert">
            {error}
          </p>
        ) : null}

        <div className="mt-5 flex flex-wrap gap-3">
          {fase === 'erro' ? (
            <Button type="button" onClick={tentarDeNovo} disabled={submitting}>
              {submitting ? 'Processando…' : 'Tentar de novo'}
            </Button>
          ) : (
            <Button type="submit" disabled={ocupado}>
              {textoBotao}
            </Button>
          )}
          <Button type="button" variant="secondary" onClick={onCancelar}>
            Cancelar
          </Button>
        </div>
      </form>

      <p className="mt-4 text-sm">
        <Link to="/biblioteca" className="font-semibold text-[var(--brand-primary)]">
          Voltar à biblioteca
        </Link>
      </p>
    </main>
  )
}
