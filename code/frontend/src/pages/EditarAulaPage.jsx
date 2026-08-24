import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { atualizarAula, fetchAula, moverAula } from '../api/aulas'
import { fetchBiblioteca } from '../api/biblioteca'
import Button from '../components/ui/Button.jsx'
import { useToast } from '../context/ToastContext'

function mensagemAoSalvar(err) {
  const status = err.response?.status
  const data = err.response?.data
  if (data?.errors?.titulo?.[0]) {
    return data.errors.titulo[0]
  }
  const msg = data?.message
  if (!status || status >= 500 || msg === 'Server Error') {
    return 'Não foi possível salvar o nome. Tente de novo.'
  }

  return msg || err.message || 'Não foi possível salvar. Tente de novo.'
}

function mensagemAoMover(err) {
  const data = err.response?.data
  if (data?.errors?.disciplina_id?.[0]) {
    return data.errors.disciplina_id[0]
  }
  const status = err.response?.status
  const msg = data?.message
  if (!status || status >= 500 || msg === 'Server Error') {
    return 'Não foi possível mover a aula. Tente de novo.'
  }

  return msg || err.message || 'Não foi possível mover a aula. Tente de novo.'
}

function disciplinasDaArvore(arvore) {
  const itens = []
  for (const curso of arvore || []) {
    for (const turma of curso.turmas || []) {
      for (const disciplina of turma.disciplinas || []) {
        itens.push({
          id: disciplina.id,
          rotulo: `${curso.nome} → ${turma.nome} → ${disciplina.nome}`,
        })
      }
    }
  }
  return itens
}

export default function EditarAulaPage() {
  const { aulaId } = useParams()
  const navigate = useNavigate()
  const { show: mostrarToast } = useToast()
  const submittingRef = useRef(false)
  const [aula, setAula] = useState(null)
  const [titulo, setTitulo] = useState('')
  const [destinoId, setDestinoId] = useState('')
  const [disciplinas, setDisciplinas] = useState([])
  const [error, setError] = useState('')
  const [erroMover, setErroMover] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [acao, setAcao] = useState('')

  useEffect(() => {
    let cancelled = false
    Promise.all([fetchAula(aulaId), fetchBiblioteca()])
      .then(([payload, bib]) => {
        if (cancelled) return
        setAula(payload.data)
        setTitulo(payload.data.titulo || '')
        setDisciplinas(disciplinasDaArvore(bib.data))
      })
      .catch(() => {
        if (!cancelled) setError('Não foi possível carregar a aula.')
      })
    return () => {
      cancelled = true
    }
  }, [aulaId])

  async function onSubmitNome(event) {
    event.preventDefault()
    if (submittingRef.current) return

    const nome = titulo.trim()
    if (!nome) {
      setError('Informe o nome da aula.')
      return
    }

    submittingRef.current = true
    setSubmitting(true)
    setAcao('nome')
    setError('')

    try {
      await atualizarAula(aulaId, { titulo: nome })
      navigate(`/aulas/${aulaId}`, {
        replace: true,
        state: { toast: 'Nome da aula atualizado.', tone: 'ok' },
      })
    } catch (err) {
      const msg = mensagemAoSalvar(err)
      setError(msg)
      mostrarToast(msg, 'erro')
      submittingRef.current = false
      setSubmitting(false)
      setAcao('')
    }
  }

  async function onSubmitMover(event) {
    event.preventDefault()
    if (submittingRef.current) return

    const destino = Number(destinoId)
    if (!destino) {
      setErroMover('Escolha a disciplina de destino.')
      return
    }

    const rotulo = disciplinas.find((item) => item.id === destino)?.rotulo || 'esta disciplina'
    const ok = window.confirm(
      `Mover esta aula para ${rotulo}? O vídeo e o HTML da Eduq continuam os mesmos. A pasta compartilhada é atualizada em seguida.`,
    )
    if (!ok) return

    submittingRef.current = true
    setSubmitting(true)
    setAcao('mover')
    setErroMover('')

    try {
      await moverAula(aulaId, destino)
      navigate(`/aulas/${aulaId}`, {
        replace: true,
        state: { toast: 'Aula movida para outra disciplina.', tone: 'ok' },
      })
    } catch (err) {
      const msg = mensagemAoMover(err)
      setErroMover(msg)
      mostrarToast(msg, 'erro')
      submittingRef.current = false
      setSubmitting(false)
      setAcao('')
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

  const destinos = disciplinas.filter((item) => item.id !== aula.disciplina_id)
  const envioEmAndamento = aula.status_preparo === 'enviando' || aula.status_preparo === 'preparando'

  return (
    <main className="mx-auto max-w-xl px-5 py-10" data-testid="pagina-editar-aula">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Editar aula
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">{aula.titulo}</h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        Este nome é o que o aluno vê no player. A pasta compartilhada é atualizada em seguida (só o
        nome do arquivo). Não renomeie no Drive: isso não muda o player e, numa importação, pode
        criar outra aula.
      </p>

      <form
        onSubmit={onSubmitNome}
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
            {submitting && acao === 'nome' ? 'Processando…' : 'Salvar'}
          </Button>
          <Link
            to={`/aulas/${aulaId}`}
            className="rounded-lg border border-[var(--brand-line)] px-4 py-3 text-sm font-bold text-[var(--brand-primary)] no-underline"
          >
            Cancelar
          </Link>
        </div>
      </form>

      <form
        onSubmit={onSubmitMover}
        className="mt-6 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5"
        data-testid="form-mover-aula"
      >
        <h2 className="m-0 text-lg font-extrabold text-[var(--brand-primary)]">Mover de disciplina</h2>
        <p className="mt-2 text-sm text-[var(--brand-muted)]">
          O vídeo e o HTML da Eduq não mudam. Só a pasta na biblioteca. Não arraste o arquivo no
          Drive: isso não atualiza a plataforma.
        </p>
        {destinos.length === 0 ? (
          <p className="mt-3 text-sm font-semibold text-[var(--brand-muted)]">
            Crie outra disciplina na Biblioteca para mover esta aula.
          </p>
        ) : envioEmAndamento ? (
          <p className="mt-3 text-sm font-semibold text-[var(--brand-muted)]">
            Espere o envio terminar para mover a aula.
          </p>
        ) : (
          <>
            <label className="mt-4 block text-sm font-bold text-[var(--brand-primary)]">
              Disciplina de destino
              <select
                data-testid="select-disciplina-destino"
                value={destinoId}
                onChange={(e) => setDestinoId(e.target.value)}
                disabled={submitting}
                className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2 text-sm font-semibold text-[var(--brand-ink)]"
              >
                <option value="">Escolha…</option>
                {destinos.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.rotulo}
                  </option>
                ))}
              </select>
            </label>
            {erroMover ? (
              <p className="mt-3 text-sm font-semibold text-[var(--brand-danger)]" role="alert">
                {erroMover}
              </p>
            ) : null}
            <div className="mt-5">
              <Button type="submit" data-testid="btn-mover-aula" disabled={submitting}>
                {submitting && acao === 'mover' ? 'Processando…' : 'Mover aula'}
              </Button>
            </div>
          </>
        )}
      </form>
    </main>
  )
}
