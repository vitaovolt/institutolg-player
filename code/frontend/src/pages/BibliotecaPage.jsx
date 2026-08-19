import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { excluirAula } from '../api/aulas'
import { fetchBiblioteca, fetchResumoMes } from '../api/biblioteca'
import Chevron from '../components/arvore/Chevron.jsx'
import ControlesArvore from '../components/arvore/ControlesArvore.jsx'
import Button, { classesBotao } from '../components/ui/Button.jsx'
import { useToast } from '../context/ToastContext'
import {
  caminhoDoFoco,
  expandirArvore,
  mesclarAberto,
  recolherArvore,
} from '../services/arvoreAberta'
import { rotuloStatusPreparo } from '../services/validarMp4'

function formatarReais(valor) {
  return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function statusDaAula(arvore, aulaId) {
  if (!aulaId) return null
  for (const curso of arvore || []) {
    for (const turma of curso.turmas || []) {
      for (const disciplina of turma.disciplinas || []) {
        const aula = (disciplina.aulas || []).find((item) => String(item.id) === String(aulaId))
        if (aula) return aula.status_preparo
      }
    }
  }
  return null
}

function LinhaBiblioteca({ nome, testId, aberto, onToggle, resumo }) {
  return (
    <button
      type="button"
      data-testid={`${testId}-toggle`}
      aria-expanded={aberto}
      aria-label={aberto ? `Recolher ${nome}` : `Expandir ${nome}`}
      onClick={onToggle}
      className="flex w-full min-w-0 items-center gap-2 rounded-lg px-2 py-2 text-left hover:bg-[var(--brand-bg)]"
    >
      <Chevron aberto={aberto} />
      <span className="truncate font-extrabold text-[var(--brand-ink)]">{nome}</span>
      {resumo ? <span className="shrink-0 text-xs font-semibold text-[var(--brand-muted)]">{resumo}</span> : null}
    </button>
  )
}

export default function BibliotecaPage() {
  const [arvore, setArvore] = useState(null)
  const [aberto, setAberto] = useState({ cursos: {}, turmas: {} })
  const [resumo, setResumo] = useState(null)
  const [error, setError] = useState('')
  const [searchParams] = useSearchParams()
  const location = useLocation()
  const navigate = useNavigate()
  const { show: mostrarToast } = useToast()
  const avisoAulaRef = useRef(false)
  const submittingRef = useRef(false)
  const [excluindoId, setExcluindoId] = useState(null)
  const turmaFoco = searchParams.get('turma')
  const disciplinaFoco = searchParams.get('disciplina')
  const aulaFoco = searchParams.get('aula')

  const aplicarArvore = useCallback((dados) => {
    setArvore(dados)
    setAberto((prev) => {
      const foco = caminhoDoFoco(dados, { turmaId: turmaFoco, disciplinaId: disciplinaFoco })
      return mesclarAberto(dados, prev, {
        expandirCurso: foco?.cursoId,
        expandirTurma: foco?.turmaId,
      })
    })
  }, [disciplinaFoco, turmaFoco])

  useEffect(() => {
    if (!location.state?.toast) return
    mostrarToast(location.state.toast, location.state.tone || 'ok')
    navigate(`${location.pathname}${location.search}`, { replace: true, state: {} })
  }, [location, mostrarToast, navigate])

  useEffect(() => {
    let cancelled = false

    async function carregar() {
      try {
        const [bib, mes] = await Promise.all([fetchBiblioteca(), fetchResumoMes()])
        if (cancelled) return
        aplicarArvore(bib.data)
        setResumo(mes.data)
        setError('')
      } catch {
        if (!cancelled) setError('Não foi possível falar com a API. Suba o backend em :8000 e rode o seed.')
      }
    }

    carregar()
    return () => {
      cancelled = true
    }
  }, [aplicarArvore])

  useEffect(() => {
    if (!aulaFoco) return undefined
    let cancelled = false
    let timer

    async function tick() {
      try {
        const [bib, mes] = await Promise.all([fetchBiblioteca(), fetchResumoMes()])
        if (cancelled) return
        aplicarArvore(bib.data)
        setResumo(mes.data)
        const status = statusDaAula(bib.data, aulaFoco)
        if (status === 'pronta' || status === 'erro') {
          if (!avisoAulaRef.current) {
            avisoAulaRef.current = true
            mostrarToast(
              status === 'pronta' ? 'Aula pronta para a biblioteca.' : 'A preparação falhou. Envie o MP4 de novo.',
              status === 'pronta' ? 'ok' : 'erro',
            )
          }
          return
        }
      } catch {
        // tenta de novo
      }
      if (!cancelled) timer = setTimeout(tick, 2000)
    }

    tick()
    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [aplicarArvore, aulaFoco, mostrarToast])

  useEffect(() => {
    if (!arvore) return
    const alvo =
      (disciplinaFoco && document.querySelector(`[data-disciplina-id="${disciplinaFoco}"]`)) ||
      (turmaFoco && document.querySelector(`[data-turma-id="${turmaFoco}"]`))
    alvo?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }, [arvore, aberto, disciplinaFoco, turmaFoco])

  async function excluirDaLista(aula) {
    if (submittingRef.current) return
    const ok = window.confirm(
      `Excluir a aula ${aula.titulo}? O vídeo deixa de tocar. A cópia na pasta compartilhada, se existir, permanece por enquanto.`,
    )
    if (!ok) return
    submittingRef.current = true
    setExcluindoId(aula.id)
    try {
      await excluirAula(aula.id)
      const [bib, mes] = await Promise.all([fetchBiblioteca(), fetchResumoMes()])
      aplicarArvore(bib.data)
      setResumo(mes.data)
      mostrarToast('Aula excluída.')
    } catch (err) {
      const msg = err.response?.data?.message || err.message || 'Não foi possível excluir. Tente de novo.'
      mostrarToast(msg, 'erro')
    } finally {
      submittingRef.current = false
      setExcluindoId(null)
    }
  }

  const ocupado = excluindoId != null

  return (
    <main className="mx-auto max-w-2xl px-5 py-10" data-testid="pagina-biblioteca">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Biblioteca de aulas
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">
        Árvore do acervo
      </h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        Curso → Turma → Disciplina. Recolha os ramos que não está usando. Envie o export MP4 da aula pronta.
      </p>

      {error ? (
        <p className="mt-8 font-semibold text-[var(--brand-danger)]">{error}</p>
      ) : !arvore || !resumo ? (
        <p className="mt-8 text-[var(--brand-muted)]">Carregando…</p>
      ) : (
        <>
          <section className="mt-8 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4">
            <p className="m-0 text-[0.7rem] font-extrabold tracking-wider uppercase text-[var(--brand-muted)]">
              Resumo do mês {resumo.competencia}
            </p>
            <p className="mt-2 text-lg font-extrabold text-[var(--brand-primary)]">
              {resumo.enviadas} enviadas · {resumo.publicadas} publicadas · {formatarReais(resumo.total)}
            </p>
          </section>

          <ControlesArvore
            onRecolher={() => setAberto(recolherArvore(arvore))}
            onExpandir={() => setAberto(expandirArvore(arvore))}
          />

          <section className="mt-6 space-y-3">
            {arvore.length === 0 ? (
              <p className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4 text-[var(--brand-muted)]">
                Nenhum curso. Rode php artisan db:seed.
              </p>
            ) : (
              arvore.map((curso) => {
                const cursoAberto = !!aberto.cursos[curso.id]
                const nTurmas = (curso.turmas || []).length
                return (
                  <div
                    key={curso.id}
                    data-testid={`curso-${curso.nome}`}
                    className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-2"
                  >
                    <LinhaBiblioteca
                      nome={curso.nome}
                      testId={`curso-${curso.nome}`}
                      aberto={cursoAberto}
                      resumo={`${nTurmas} ${nTurmas === 1 ? 'turma' : 'turmas'}`}
                      onToggle={() =>
                        setAberto((prev) => ({
                          ...prev,
                          cursos: { ...prev.cursos, [curso.id]: !prev.cursos[curso.id] },
                        }))
                      }
                    />
                    {cursoAberto
                      ? (curso.turmas || []).map((turma) => {
                          const turmaAberta = !!aberto.turmas[turma.id]
                          const turmaOn = String(turma.id) === String(turmaFoco)
                          const nDisc = (turma.disciplinas || []).length
                          return (
                            <div
                              key={turma.id}
                              data-turma-id={turma.id}
                              data-testid={`turma-${turma.nome}`}
                              className={`mt-1 rounded-lg bg-[var(--brand-bg)] p-1 ${turmaOn ? 'ring-2 ring-[var(--brand-accent)]' : ''}`}
                            >
                              <LinhaBiblioteca
                                nome={turma.nome}
                                testId={`turma-${turma.nome}`}
                                aberto={turmaAberta}
                                resumo={`${nDisc} ${nDisc === 1 ? 'disciplina' : 'disciplinas'}`}
                                onToggle={() =>
                                  setAberto((prev) => ({
                                    ...prev,
                                    turmas: { ...prev.turmas, [turma.id]: !prev.turmas[turma.id] },
                                  }))
                                }
                              />
                              {turmaAberta
                                ? (turma.disciplinas || []).map((disciplina) => {
                                    const discOn = String(disciplina.id) === String(disciplinaFoco)
                                    return (
                                      <div
                                        key={disciplina.id}
                                        data-disciplina-id={disciplina.id}
                                        data-testid={`disciplina-${disciplina.nome}`}
                                        className={`mx-2 mb-2 rounded-lg bg-[var(--brand-surface)] p-3 ${discOn ? 'ring-2 ring-[var(--brand-accent)]' : ''}`}
                                      >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                          <p className="m-0 text-sm font-semibold text-[var(--brand-ink)]">
                                            {disciplina.nome}
                                          </p>
                                          <Link
                                            to={`/disciplinas/${disciplina.id}/enviar`}
                                            className={`${classesBotao('primary')} no-underline`}
                                          >
                                            Enviar aula
                                          </Link>
                                        </div>
                                        <ul className="mt-2 ml-4 list-disc text-sm">
                                          {(disciplina.aulas || []).map((aula) => (
                                            <li
                                              key={aula.id}
                                              data-testid={`aula-${aula.titulo}`}
                                              className="flex items-center gap-2"
                                            >
                                              {aula.url_capa ? (
                                                <img
                                                  src={aula.url_capa}
                                                  alt=""
                                                  className="h-8 w-14 rounded object-cover bg-[#120F24]"
                                                />
                                              ) : null}
                                              <span>
                                                <Link
                                                  to={`/aulas/${aula.id}`}
                                                  className="font-semibold text-[var(--brand-primary)]"
                                                >
                                                  {aula.titulo}
                                                </Link>
                                                {' · '}
                                                {rotuloStatusPreparo(aula.status_preparo)}
                                                {aula.publicada ? ' · publicada' : ''}
                                              </span>
                                              <Button
                                                variant="danger"
                                                disabled={ocupado}
                                                onClick={() => excluirDaLista(aula)}
                                              >
                                                {ocupado && excluindoId === aula.id ? 'Processando…' : 'Excluir'}
                                              </Button>
                                            </li>
                                          ))}
                                        </ul>
                                      </div>
                                    )
                                  })
                                : null}
                            </div>
                          )
                        })
                      : null}
                  </div>
                )
              })
            )}
          </section>
        </>
      )}
    </main>
  )
}
