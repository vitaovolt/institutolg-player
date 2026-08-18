import { useEffect, useRef, useState } from 'react'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import { despublicarAula, fetchAula, publicarAula, removerCapa, reprocessarDrive, salvarCapa } from '../api/aulas'
import { fetchResumoMes } from '../api/biblioteca'
import Button from '../components/ui/Button.jsx'
import { useToast } from '../context/ToastContext'
import { rotuloStatusDrive, rotuloStatusPreparo } from '../services/validarMp4'

function formatarReais(valor) {
  return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function pill(ok, texto) {
  return (
    <span className={`rounded-full px-2 py-0.5 text-xs font-extrabold ${ok ? 'bg-[#E7F6F2] text-[var(--brand-ok)]' : 'bg-[#F4F1FA] text-[var(--brand-muted)]'}`}>
      {texto}
    </span>
  )
}

export default function AulaDetalhePage() {
  const { aulaId } = useParams()
  const navigate = useNavigate()
  const location = useLocation()
  const { show: mostrarToast } = useToast()
  const submittingRef = useRef(false)
  const [aula, setAula] = useState(null)
  const [resumo, setResumo] = useState(null)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [acao, setAcao] = useState('')
  const [arquivoCapa, setArquivoCapa] = useState(null)

  async function carregar() {
    const [aulaRes, mes] = await Promise.all([fetchAula(aulaId), fetchResumoMes()])
    setAula(aulaRes.data)
    setResumo(mes.data)
    setError('')
    return aulaRes.data
  }

  useEffect(() => {
    if (!location.state?.toast) return
    mostrarToast(location.state.toast, location.state.tone || 'ok')
    navigate(`${location.pathname}${location.search}`, { replace: true, state: {} })
  }, [location, mostrarToast, navigate])

  useEffect(() => {
    let cancelled = false
    carregar().catch(() => {
      if (!cancelled) setError('Não foi possível carregar a aula.')
    })
    return () => {
      cancelled = true
    }
  }, [aulaId])

  useEffect(() => {
    if (!aula || (aula.status_drive !== 'enviando' && aula.status_preparo !== 'preparando')) return undefined
    let cancelled = false
    const timer = setInterval(async () => {
      try {
        const atual = await carregar()
        if (cancelled) return
        if (atual.status_drive !== 'enviando' && atual.status_preparo !== 'preparando') {
          clearInterval(timer)
        }
      } catch {
        // tenta de novo
      }
    }, 2000)
    return () => {
      cancelled = true
      clearInterval(timer)
    }
  }, [aula?.status_drive, aula?.status_preparo, aulaId])

  async function comSubmit(nome, fn, toastOk) {
    if (submittingRef.current) return
    submittingRef.current = true
    setSubmitting(true)
    setAcao(nome)
    try {
      await fn()
      await carregar()
      if (toastOk) mostrarToast(toastOk)
    } catch (err) {
      const msg = err.response?.data?.message || err.message || 'Não foi possível concluir. Tente de novo.'
      mostrarToast(msg, 'erro')
    } finally {
      submittingRef.current = false
      setSubmitting(false)
      setAcao('')
    }
  }

  async function copiarHtml() {
    if (!aula?.html_iframe) return
    try {
      await navigator.clipboard.writeText(aula.html_iframe)
      mostrarToast('HTML copiado. Cole no bloco Vídeo → Iframe da Eduq.')
    } catch {
      mostrarToast('Não foi possível copiar. Selecione o texto e copie com Ctrl+C.', 'erro')
    }
  }

  if (error) {
    return (
      <main className="mx-auto max-w-2xl px-5 py-10">
        <p className="font-semibold text-[var(--brand-danger)]">{error}</p>
        <Link to="/biblioteca" className="mt-4 inline-block font-semibold text-[var(--brand-primary)]">
          Voltar à biblioteca
        </Link>
      </main>
    )
  }

  if (!aula) {
    return (
      <main className="mx-auto max-w-2xl px-5 py-10">
        <p className="text-[var(--brand-muted)]">Carregando…</p>
      </main>
    )
  }

  const cursoNome = aula.disciplina?.turma?.curso?.nome || ''
  const turmaNome = aula.disciplina?.turma?.nome || ''
  const discNome = aula.disciplina?.nome || ''
  const pronta = aula.pronta_para_assistir
  const ocupado = submitting
  const copiaPendente = pronta && aula.status_drive !== 'ok'

  return (
    <main className="mx-auto max-w-2xl px-5 py-10" data-testid="pagina-detalhe-aula">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Detalhe da aula
      </p>
      <div className="mt-2 flex flex-wrap items-start justify-between gap-3">
        <h1 className="m-0 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">{aula.titulo}</h1>
        {pill(pronta, rotuloStatusPreparo(aula.status_preparo))}
      </div>
      <p className="mt-2 text-[var(--brand-muted)]">
        {cursoNome} → {turmaNome} → {discNome}
      </p>

      <section className="mt-6 grid grid-cols-2 gap-3">
        <div className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-3">
          <p className="m-0 text-xs font-extrabold uppercase tracking-wider text-[var(--brand-muted)]">Assistir</p>
          <p className="mt-1 font-bold">{rotuloStatusPreparo(aula.status_preparo)}</p>
        </div>
        <div className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-3">
          <p className="m-0 text-xs font-extrabold uppercase tracking-wider text-[var(--brand-muted)]">Publicada</p>
          <p className="mt-1 font-bold" data-testid="status-publicada">
            {aula.publicada ? 'Sim' : 'Não'}
          </p>
        </div>
        <div className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-3">
          <p className="m-0 text-xs font-extrabold uppercase tracking-wider text-[var(--brand-muted)]">Cópia Drive</p>
          <p className="mt-1 font-bold" data-testid="status-drive">
            {rotuloStatusDrive(aula.status_drive)}
          </p>
        </div>
        <div className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-3">
          <p className="m-0 text-xs font-extrabold uppercase tracking-wider text-[var(--brand-muted)]">Cobrança</p>
          <p className="mt-1 font-bold">{aula.publicada ? 'R$ 3,80/mês' : 'Fora da linha'}</p>
        </div>
      </section>

      {resumo ? (
        <p className="mt-3 text-sm text-[var(--brand-muted)]" data-testid="resumo-detalhe">
          Mês {resumo.competencia}: {resumo.publicadas} publicadas · {formatarReais(resumo.total)}
        </p>
      ) : null}

      {copiaPendente ? (
        <p className="mt-4 rounded-lg border border-[#F0D48A] bg-[#FFF8E8] px-3 py-2 text-sm font-semibold text-[#8A5A00]">
          Cópia na pasta compartilhada em andamento. O aluno já pode assistir se o HTML estiver na Eduq.
        </p>
      ) : null}

      {aula.status_drive === 'erro' ? (
        <p className="mt-4 rounded-lg border border-[var(--brand-danger)] bg-[#FDECEC] px-3 py-2 text-sm font-semibold text-[var(--brand-danger)]">
          {aula.mensagem_erro || 'A cópia para a pasta compartilhada falhou.'} A aula pode continuar assistível.
        </p>
      ) : null}

      <section className="mt-6 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4">
        <h2 className="m-0 text-lg font-extrabold text-[var(--brand-primary)]">Foto de capa</h2>
        <p className="mt-1 text-sm text-[var(--brand-muted)]">
          Aparece no player antes de assistir. Envie um JPG ou PNG em paisagem (até 2 MB).
        </p>
        {aula.url_capa ? (
          <img
            data-testid="preview-capa"
            src={aula.url_capa}
            alt={`Capa de ${aula.titulo}`}
            className="mt-3 aspect-video w-full max-w-md rounded-lg object-cover bg-[#120F24]"
          />
        ) : (
          <p className="mt-3 text-sm text-[var(--brand-muted)]">Ainda sem capa — o player começa em tela preta.</p>
        )}
        <label className="mt-3 block text-sm font-bold text-[var(--brand-primary)]">
          Arquivo da capa
          <input
            data-testid="arquivo-capa"
            type="file"
            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
            disabled={ocupado}
            onChange={(e) => setArquivoCapa(e.target.files?.[0] || null)}
            className="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--brand-accent)] file:px-3 file:py-2 file:font-bold"
          />
        </label>
        <div className="mt-3 flex flex-wrap gap-2">
          <Button
            data-testid="btn-salvar-capa"
            disabled={ocupado || !arquivoCapa}
            onClick={() =>
              comSubmit(
                'capa',
                async () => {
                  await salvarCapa(aula.id, arquivoCapa)
                  setArquivoCapa(null)
                },
                'Capa da aula salva.',
              )
            }
          >
            {ocupado && acao === 'capa' ? 'Processando…' : 'Salvar capa'}
          </Button>
          {aula.tem_capa ? (
            <Button
              variant="danger"
              data-testid="btn-remover-capa"
              disabled={ocupado}
              onClick={() => comSubmit('capa-off', () => removerCapa(aula.id), 'Capa removida.')}
            >
              {ocupado && acao === 'capa-off' ? 'Processando…' : 'Remover capa'}
            </Button>
          ) : null}
        </div>
      </section>

      <section className="mt-6 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4">
        <h2 className="m-0 text-lg font-extrabold text-[var(--brand-primary)]">HTML para a Eduq</h2>
        <p className="mt-1 text-sm text-[var(--brand-muted)]">
          Cole no bloco <strong>Vídeo → Iframe</strong>. Não use YouTube, Vimeo, pasta compartilhada nem o upload nativo.
        </p>
        <textarea
          data-testid="html-iframe"
          readOnly
          value={aula.html_iframe || 'A aula ainda não está pronta. O HTML aparece quando o status for Pronta.'}
          className="mt-3 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2 font-mono text-xs"
          rows={3}
        />
        <div className="mt-4 flex flex-wrap gap-3">
          <button
            type="button"
            data-testid="btn-copiar-html"
            onClick={copiarHtml}
            disabled={!aula.html_iframe}
            className="rounded-lg bg-[var(--brand-accent)] px-4 py-3 text-sm font-extrabold text-[var(--brand-ink)] hover:brightness-110 disabled:opacity-50"
          >
            Copiar HTML
          </button>
          {pronta ? (
            <Link
              to={`/aulas/${aula.id}/substituir`}
              className="rounded-lg border border-[var(--brand-line)] px-4 py-3 text-sm font-bold text-[var(--brand-primary)] no-underline"
            >
              Substituir vídeo
            </Link>
          ) : null}
          {pronta && !aula.publicada ? (
            <button
              type="button"
              data-testid="btn-publicar"
              disabled={ocupado}
              onClick={() => comSubmit('publicar', () => publicarAula(aula.id), 'Aula publicada. Entra na linha de R$ 3,80.')}
              className="rounded-lg border border-[var(--brand-line)] px-4 py-3 text-sm font-bold text-[var(--brand-primary)] disabled:opacity-70"
            >
              {ocupado && acao === 'publicar' ? 'Processando…' : 'Publicar'}
            </button>
          ) : null}
          {aula.publicada ? (
            <button
              type="button"
              data-testid="btn-despublicar"
              disabled={ocupado}
              onClick={() => comSubmit('despublicar', () => despublicarAula(aula.id), 'Aula despublicada. Saiu da cobrança.')}
              className="rounded-lg border border-[var(--brand-line)] px-4 py-3 text-sm font-bold text-[var(--brand-primary)] disabled:opacity-70"
            >
              {ocupado && acao === 'despublicar' ? 'Processando…' : 'Despublicar'}
            </button>
          ) : null}
          {pronta && aula.status_drive !== 'ok' ? (
            <button
              type="button"
              data-testid="btn-retry-drive"
              disabled={ocupado}
              onClick={() => comSubmit('drive', () => reprocessarDrive(aula.id), 'Enviando a cópia de novo.')}
              className="rounded-lg border border-[var(--brand-line)] px-4 py-3 text-sm font-bold text-[var(--brand-primary)] disabled:opacity-70"
            >
              {ocupado && acao === 'drive' ? 'Processando…' : 'Tentar cópia de novo'}
            </button>
          ) : null}
        </div>
      </section>

      <p className="mt-5 flex flex-wrap gap-4 text-sm">
        {aula.url_demonstracao_eduq ? (
          <a
            href={aula.url_demonstracao_eduq}
            target="_blank"
            rel="noreferrer"
            data-testid="link-ver-aluno"
            className="font-semibold text-[var(--brand-primary)]"
          >
            Ver como o aluno vê
          </a>
        ) : null}
        <Link to={`/aulas/${aula.id}/colar`} className="font-semibold text-[var(--brand-primary)]">
          Colar na Eduq
        </Link>
        <button type="button" onClick={() => navigate('/biblioteca')} className="font-semibold text-[var(--brand-primary)]">
          Voltar à biblioteca
        </button>
      </p>
    </main>
  )
}
