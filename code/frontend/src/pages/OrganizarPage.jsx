import { useEffect, useRef, useState } from 'react'
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
import Chevron from '../components/arvore/Chevron.jsx'
import ControlesArvore from '../components/arvore/ControlesArvore.jsx'
import Button from '../components/ui/Button.jsx'
import { useToast } from '../context/ToastContext'
import { expandirArvore, mesclarAberto, recolherArvore } from '../services/arvoreAberta'

function LinhaNo({ nome, testId, ocupado, onSalvar, onExcluir, aberto, onToggle, resumo, nivel = 0 }) {
  const [editando, setEditando] = useState(false)
  const [valor, setValor] = useState(nome)
  const savingRef = useRef(false)

  useEffect(() => {
    setValor(nome)
  }, [nome])

  async function salvar() {
    if (savingRef.current) return
    const trim = valor.trim()
    if (!trim) return
    savingRef.current = true
    try {
      await onSalvar(trim)
      setEditando(false)
    } finally {
      savingRef.current = false
    }
  }

  return (
    <div
      data-testid={testId}
      className="flex flex-wrap items-center gap-2 rounded-lg px-2 py-2 hover:bg-[var(--brand-bg)]"
      style={{ marginLeft: nivel * 12 }}
    >
      <button
        type="button"
        data-testid={`${testId}-toggle`}
        aria-expanded={aberto}
        aria-label={aberto ? `Recolher ${nome}` : `Expandir ${nome}`}
        onClick={onToggle}
        className="flex min-w-0 flex-1 items-center gap-2 text-left"
      >
        <Chevron aberto={aberto} />
        {editando ? null : (
          <>
            <span className="truncate font-extrabold text-[var(--brand-ink)]">{nome}</span>
            {resumo ? <span className="shrink-0 text-xs font-semibold text-[var(--brand-muted)]">{resumo}</span> : null}
          </>
        )}
      </button>
      {editando ? (
        <div className="flex flex-wrap items-center gap-2">
          <input
            value={valor}
            onChange={(e) => setValor(e.target.value)}
            disabled={ocupado}
            className="rounded-lg border border-[var(--brand-line)] px-2 py-1.5 text-sm"
          />
          <Button disabled={ocupado} onClick={salvar}>
            {ocupado ? 'Processando…' : 'Salvar'}
          </Button>
          <Button variant="ghost" onClick={() => setEditando(false)}>
            Cancelar
          </Button>
        </div>
      ) : (
        <div className="flex shrink-0 gap-2">
          <Button variant="secondary" onClick={() => setEditando(true)}>
            Editar
          </Button>
          <Button variant="danger" onClick={onExcluir}>
            Excluir
          </Button>
        </div>
      )}
    </div>
  )
}

function DisciplinaFolha({ disciplina, ocupado, onSalvar, onExcluir }) {
  const [editando, setEditando] = useState(false)
  const [valor, setValor] = useState(disciplina.nome)
  const savingRef = useRef(false)

  useEffect(() => {
    setValor(disciplina.nome)
  }, [disciplina.nome])

  async function salvar() {
    if (savingRef.current) return
    const trim = valor.trim()
    if (!trim) return
    savingRef.current = true
    try {
      await onSalvar(trim)
      setEditando(false)
    } finally {
      savingRef.current = false
    }
  }

  return (
    <div
      data-testid={`disciplina-${disciplina.nome}`}
      className="mt-1 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-[var(--brand-surface)] px-3 py-2"
    >
      {editando ? (
        <div className="flex flex-wrap items-center gap-2">
          <input
            value={valor}
            onChange={(e) => setValor(e.target.value)}
            disabled={ocupado}
            className="rounded-lg border border-[var(--brand-line)] px-2 py-1.5 text-sm"
          />
          <Button disabled={ocupado} onClick={salvar}>
            {ocupado ? 'Processando…' : 'Salvar'}
          </Button>
          <Button variant="ghost" onClick={() => setEditando(false)}>
            Cancelar
          </Button>
        </div>
      ) : (
        <>
          <span className="font-semibold">{disciplina.nome}</span>
          <div className="flex gap-2">
            <Button variant="secondary" onClick={() => setEditando(true)}>
              Editar
            </Button>
            <Button variant="danger" onClick={onExcluir}>
              Excluir
            </Button>
          </div>
        </>
      )}
    </div>
  )
}

export default function OrganizarPage() {
  const { show: mostrarToast } = useToast()
  const submittingRef = useRef(false)
  const [arvore, setArvore] = useState(null)
  const [aberto, setAberto] = useState({ cursos: {}, turmas: {} })
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [novoCurso, setNovoCurso] = useState('')
  const [novaTurma, setNovaTurma] = useState({})
  const [novaDisc, setNovaDisc] = useState({})

  async function carregar(forcar = {}) {
    const bib = await fetchBiblioteca()
    setArvore(bib.data)
    setAberto((prev) => mesclarAberto(bib.data, prev, forcar))
    setError('')
  }

  useEffect(() => {
    carregar().catch(() => setError('Não foi possível carregar a árvore.'))
  }, [])

  async function comSubmit(fn, toastOk) {
    if (submittingRef.current) return
    submittingRef.current = true
    setSubmitting(true)
    try {
      const extra = (await fn()) || {}
      await carregar(extra)
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

  function recolherTudo() {
    if (!arvore) return
    setAberto(recolherArvore(arvore))
  }

  function expandirTudo() {
    if (!arvore) return
    setAberto(expandirArvore(arvore))
  }

  if (error) {
    return (
      <main className="mx-auto max-w-2xl px-5 py-10">
        <p className="font-semibold text-[var(--brand-danger)]">{error}</p>
      </main>
    )
  }

  if (!arvore) {
    return (
      <main className="mx-auto max-w-2xl px-5 py-10">
        <p className="text-[var(--brand-muted)]">Carregando…</p>
      </main>
    )
  }

  return (
    <main className="mx-auto max-w-2xl px-5 py-10" data-testid="pagina-organizar">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Organizar acervo
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">
        Curso → Turma → Disciplina
      </h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        Recolha os ramos que não está usando. Editar e Excluir ficam em cada linha. Só apaga o que estiver vazio.
      </p>

      <ControlesArvore onRecolher={recolherTudo} onExpandir={expandirTudo} />

      <form
        className="mt-6 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4"
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
            disabled={submitting}
            className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2"
            placeholder="Ex.: Especialização em Enfermagem"
          />
        </label>
        <Button type="submit" className="mt-3" disabled={submitting}>
          {submitting ? 'Processando…' : 'Salvar curso'}
        </Button>
      </form>

      <section className="mt-6 space-y-3">
        {arvore.map((curso) => {
          const cursoAberto = !!aberto.cursos[curso.id]
          const nTurmas = (curso.turmas || []).length
          return (
            <div key={curso.id} className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-2">
              <LinhaNo
                nome={curso.nome}
                testId={`curso-${curso.nome}`}
                ocupado={submitting}
                aberto={cursoAberto}
                resumo={`${nTurmas} ${nTurmas === 1 ? 'turma' : 'turmas'}`}
                onToggle={() =>
                  setAberto((prev) => ({ ...prev, cursos: { ...prev.cursos, [curso.id]: !prev.cursos[curso.id] } }))
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
                      disabled={submitting}
                      placeholder="Nome da nova turma"
                      className="min-w-[12rem] flex-1 rounded-lg border border-[var(--brand-line)] px-3 py-2 text-sm"
                    />
                    <Button type="submit" disabled={submitting}>
                      {submitting ? 'Processando…' : 'Adicionar turma'}
                    </Button>
                  </form>
                  {(curso.turmas || []).map((turma) => {
                    const turmaAberta = !!aberto.turmas[turma.id]
                    const nDisc = (turma.disciplinas || []).length
                    return (
                      <div key={turma.id} className="mt-1 rounded-lg bg-[var(--brand-bg)] p-1">
                        <LinhaNo
                          nome={turma.nome}
                          testId={`turma-${turma.nome}`}
                          ocupado={submitting}
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
                            confirmarExcluir(`Excluir a turma ${turma.nome}? Só vale se não tiver disciplinas.`, () =>
                              excluirTurma(turma.id),
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
                                disabled={submitting}
                                placeholder="Nome da nova disciplina"
                                className="min-w-[12rem] flex-1 rounded-lg border border-[var(--brand-line)] px-3 py-2 text-sm"
                              />
                              <Button type="submit" variant="secondary" disabled={submitting}>
                                {submitting ? 'Processando…' : 'Adicionar disciplina'}
                              </Button>
                            </form>
                            {(turma.disciplinas || []).map((disciplina) => (
                              <DisciplinaFolha
                                key={disciplina.id}
                                disciplina={disciplina}
                                ocupado={submitting}
                                onSalvar={(nome) =>
                                  comSubmit(() => atualizarDisciplina(disciplina.id, nome), 'Disciplina atualizada.')
                                }
                                onExcluir={() =>
                                  confirmarExcluir(
                                    `Excluir a disciplina ${disciplina.nome}? Só vale se não tiver aulas.`,
                                    () => excluirDisciplina(disciplina.id),
                                  )
                                }
                              />
                            ))}
                          </div>
                        ) : null}
                      </div>
                    )
                  })}
                </div>
              ) : null}
            </div>
          )
        })}
      </section>
    </main>
  )
}
