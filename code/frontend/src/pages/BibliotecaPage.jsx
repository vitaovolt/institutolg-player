import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { excluirAula } from '../api/aulas'
import {
  atualizarCurso,
  atualizarDisciplina,
  atualizarTurma,
  criarCurso,
  criarDisciplina,
  criarTurma,
  excluirCurso,
  excluirDisciplina,
  excluirTurma,
} from '../api/arvore'
import { fetchBiblioteca } from '../api/biblioteca'
import { DisciplinaFolha, LinhaNo } from '../components/arvore/LinhaNo.jsx'
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

export default function BibliotecaPage() {
  const [arvore, setArvore] = useState(null)
  const [aberto, setAberto] = useState({ cursos: {}, turmas: {} })
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [novoCurso, setNovoCurso] = useState('')
  const [novaTurma, setNovaTurma] = useState({})
  const [novaDisc, setNovaDisc] = useState({})
  const [excluindoId, setExcluindoId] = useState(null)
  const [searchParams] = useSearchParams()
  const location = useLocation()
  const navigate = useNavigate()
  const { show: mostrarToast } = useToast()
  const avisoAulaRef = useRef(false)
  const submittingRef = useRef(false)
  const turmaFoco = searchParams.get('turma')
  const disciplinaFoco = searchParams.get('disciplina')
  const aulaFoco = searchParams.get('aula')

  const aplicarArvore = useCallback(
    (dados, forcar = {}) => {
      setArvore(dados)
      setAberto((prev) => {
        const foco = caminhoDoFoco(dados, { turmaId: turmaFoco, disciplinaId: disciplinaFoco })
        return mesclarAberto(dados, prev, {
          expandirCurso: forcar.expandirCurso || foco?.cursoId,
          expandirTurma: forcar.expandirTurma || foco?.turmaId,
        })
      })
    },
    [disciplinaFoco, turmaFoco],
  )

  const recarregar = useCallback(
    async (forcar = {}) => {
      const bib = await fetchBiblioteca()
      aplicarArvore(bib.data, forcar)
      setError('')
      return bib.data
    },
    [aplicarArvore],
  )

  useEffect(() => {
    if (!location.state?.toast) return
    mostrarToast(location.state.toast, location.state.tone || 'ok')
    navigate(`${location.pathname}${location.search}`, { replace: true, state: {} })
  }, [location, mostrarToast, navigate])

  useEffect(() => {
    let cancelled = false
    recarregar().catch(() => {
      if (!cancelled) setError('Não foi possível falar com a API. Suba o backend em :8000 e rode o seed.')
    })
    return () => {
      cancelled = true
    }
  }, [recarregar])

  useEffect(() => {
    if (!aulaFoco) return undefined
    let cancelled = false
    let timer

    async function tick() {
      try {
        const dados = await recarregar()
        if (cancelled) return
        const status = statusDaAula(dados, aulaFoco)
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
  }, [aulaFoco, mostrarToast, recarregar])

  useEffect(() => {
    if (!arvore) return
    const alvo =
      (disciplinaFoco && document.querySelector(`[data-disciplina-id="${disciplinaFoco}"]`)) ||
      (turmaFoco && document.querySelector(`[data-turma-id="${turmaFoco}"]`))
    alvo?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }, [arvore, aberto, disciplinaFoco, turmaFoco])

  async function comSubmit(fn, toastOk) {
    if (submittingRef.current) return
    submittingRef.current = true
    setSubmitting(true)
    try {
      const extra = (await fn()) || {}
      await recarregar(extra)
      if (toastOk) mostrarToast(toastOk)
    } catch (err) {
      const msg =
        err.response?.data?.errors?.nome?.[0] ||
        err.response?.data?.message ||
        'Não foi possível salvar. Tente de novo.'
      mostrarToast(msg, 'erro')
    } finally {
      submittingRef.current = false
      setSubmitting(false)
    }
  }

  function confirmarExcluir(pergunta, fn) {
    if (!window.confirm(pergunta)) return
    comSubmit(fn, 'Removido.')
  }

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
      await recarregar()
      mostrarToast('Aula excluída.')
    } catch (err) {
      const msg = err.response?.data?.message || err.message || 'Não foi possível excluir. Tente de novo.'
      mostrarToast(msg, 'erro')
    } finally {
      submittingRef.current = false
      setExcluindoId(null)
    }
  }

  const ocupado = submitting || excluindoId != null

  return (
    <main className="mx-auto max-w-2xl px-5 py-10" data-testid="pagina-biblioteca">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Biblioteca de aulas
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">Árvore do acervo</h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        Curso → Turma → Disciplina. Cadastre e organize a estrutura aqui. Envie o export MP4 em cada disciplina.
      </p>

      {error ? (
        <p className="mt-8 font-semibold text-[var(--brand-danger)]">{error}</p>
      ) : !arvore ? (
        <p className="mt-8 text-[var(--brand-muted)]">Carregando…</p>
      ) : (
        <>
          <form
            className="mt-8 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4"
            onSubmit={(e) => {
              e.preventDefault()
              const nome = novoCurso.trim()
              if (!nome) return
              comSubmit(async () => {
                const criado = await criarCurso(nome)
                setNovoCurso('')
                return { expandirCurso: criado.data.id }
              }, 'Curso criado.')
            }}
          >
            <label className="text-sm font-bold text-[var(--brand-primary)]">
              Novo curso
              <input
                data-testid="input-novo-curso"
                value={novoCurso}
                onChange={(e) => setNovoCurso(e.target.value)}
                disabled={ocupado}
                className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2"
                placeholder="Ex.: Especialização em Enfermagem"
              />
            </label>
            <Button type="submit" className="mt-3" disabled={ocupado}>
              {ocupado ? 'Processando…' : 'Salvar curso'}
            </Button>
          </form>

          <ControlesArvore
            onRecolher={() => setAberto(recolherArvore(arvore))}
            onExpandir={() => setAberto(expandirArvore(arvore))}
          />

          <section className="mt-6 space-y-3">
            {arvore.length === 0 ? (
              <p className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4 text-[var(--brand-muted)]">
                Nenhum curso ainda. Cadastre o primeiro acima.
              </p>
            ) : (
              arvore.map((curso) => {
                const cursoAberto = !!aberto.cursos[curso.id]
                const nTurmas = (curso.turmas || []).length
                return (
                  <div
                    key={curso.id}
                    className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-2"
                  >
                    <LinhaNo
                      nome={curso.nome}
                      testId={`curso-${curso.nome}`}
                      ocupado={ocupado}
                      aberto={cursoAberto}
                      resumo={`${nTurmas} ${nTurmas === 1 ? 'turma' : 'turmas'}`}
                      onToggle={() =>
                        setAberto((prev) => ({
                          ...prev,
                          cursos: { ...prev.cursos, [curso.id]: !prev.cursos[curso.id] },
                        }))
                      }
                      onSalvar={(nome) => comSubmit(() => atualizarCurso(curso.id, nome), 'Curso atualizado.')}
                      onExcluir={() =>
                        confirmarExcluir(`Excluir o curso ${curso.nome}? Só vale se não tiver turmas.`, () =>
                          excluirCurso(curso.id),
                        )
                      }
                    />
                    {cursoAberto ? (
                      <div className="border-t border-[var(--brand-line)] px-2 pb-3 pt-2">
                        <form
                          className="mb-3 flex flex-wrap items-center gap-2"
                          onSubmit={(e) => {
                            e.preventDefault()
                            const nome = (novaTurma[curso.id] || '').trim()
                            if (!nome) return
                            comSubmit(async () => {
                              const criada = await criarTurma(curso.id, nome)
                              setNovaTurma((prev) => ({ ...prev, [curso.id]: '' }))
                              return { expandirCurso: curso.id, expandirTurma: criada.data.id }
                            }, 'Turma criada.')
                          }}
                        >
                          <input
                            data-testid={`input-turma-${curso.id}`}
                            value={novaTurma[curso.id] || ''}
                            onChange={(e) => setNovaTurma((prev) => ({ ...prev, [curso.id]: e.target.value }))}
                            disabled={ocupado}
                            placeholder="Nome da nova turma"
                            className="min-w-[12rem] flex-1 rounded-lg border border-[var(--brand-line)] px-3 py-2 text-sm"
                          />
                          <Button type="submit" disabled={ocupado}>
                            {ocupado ? 'Processando…' : 'Adicionar turma'}
                          </Button>
                        </form>
                        {(curso.turmas || []).map((turma) => {
                          const turmaAberta = !!aberto.turmas[turma.id]
                          const turmaOn = String(turma.id) === String(turmaFoco)
                          const nDisc = (turma.disciplinas || []).length
                          return (
                            <div
                              key={turma.id}
                              data-turma-id={turma.id}
                              className={`mt-1 rounded-lg bg-[var(--brand-bg)] p-1 ${turmaOn ? 'ring-2 ring-[var(--brand-accent)]' : ''}`}
                            >
                              <LinhaNo
                                nome={turma.nome}
                                testId={`turma-${turma.nome}`}
                                ocupado={ocupado}
                                aberto={turmaAberta}
                                nivel={1}
                                resumo={`${nDisc} ${nDisc === 1 ? 'disciplina' : 'disciplinas'}`}
                                onToggle={() =>
                                  setAberto((prev) => ({
                                    ...prev,
                                    turmas: { ...prev.turmas, [turma.id]: !prev.turmas[turma.id] },
                                  }))
                                }
                                onSalvar={(nome) => comSubmit(() => atualizarTurma(turma.id, nome), 'Turma atualizada.')}
                                onExcluir={() =>
                                  confirmarExcluir(
                                    `Excluir a turma ${turma.nome}? Só vale se não tiver disciplinas.`,
                                    () => excluirTurma(turma.id),
                                  )
                                }
                              />
                              {turmaAberta ? (
                                <div className="px-3 pb-2">
                                  <form
                                    className="mb-2 flex flex-wrap items-center gap-2"
                                    onSubmit={(e) => {
                                      e.preventDefault()
                                      const nome = (novaDisc[turma.id] || '').trim()
                                      if (!nome) return
                                      comSubmit(async () => {
                                        await criarDisciplina(turma.id, nome)
                                        setNovaDisc((prev) => ({ ...prev, [turma.id]: '' }))
                                        return { expandirCurso: curso.id, expandirTurma: turma.id }
                                      }, 'Disciplina criada.')
                                    }}
                                  >
                                    <input
                                      data-testid={`input-disciplina-${turma.id}`}
                                      value={novaDisc[turma.id] || ''}
                                      onChange={(e) => setNovaDisc((prev) => ({ ...prev, [turma.id]: e.target.value }))}
                                      disabled={ocupado}
                                      placeholder="Nome da nova disciplina"
                                      className="min-w-[12rem] flex-1 rounded-lg border border-[var(--brand-line)] px-3 py-2 text-sm"
                                    />
                                    <Button type="submit" variant="secondary" disabled={ocupado}>
                                      {ocupado ? 'Processando…' : 'Adicionar disciplina'}
                                    </Button>
                                  </form>
                                  {(turma.disciplinas || []).map((disciplina) => {
                                    const discOn = String(disciplina.id) === String(disciplinaFoco)
                                    return (
                                      <div
                                        key={disciplina.id}
                                        data-disciplina-id={disciplina.id}
                                        data-testid={`disciplina-${disciplina.nome}`}
                                        className={`mb-2 ${discOn ? 'rounded-lg ring-2 ring-[var(--brand-accent)]' : ''}`}
                                      >
                                        <DisciplinaFolha
                                          disciplina={disciplina}
                                          ocupado={ocupado}
                                          extra={
                                            <Link
                                              to={`/disciplinas/${disciplina.id}/enviar`}
                                              className={`${classesBotao('primary')} no-underline`}
                                            >
                                              Enviar aula
                                            </Link>
                                          }
                                          onSalvar={(nome) =>
                                            comSubmit(
                                              () => atualizarDisciplina(disciplina.id, nome),
                                              'Disciplina atualizada.',
                                            )
                                          }
                                          onExcluir={() =>
                                            confirmarExcluir(
                                              `Excluir a disciplina ${disciplina.nome}? Só vale se não tiver aulas.`,
                                              () => excluirDisciplina(disciplina.id),
                                            )
                                          }
                                        />
                                        <ul className="mt-1 ml-4 list-disc text-sm">
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
                                                  loading="lazy"
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
                                  })}
                                </div>
                              ) : null}
                            </div>
                          )
                        })}
                      </div>
                    ) : null}
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
