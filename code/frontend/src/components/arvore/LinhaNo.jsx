import { useEffect, useRef, useState } from 'react'
import Button from '../ui/Button.jsx'
import Chevron from './Chevron.jsx'

export function LinhaNo({ nome, testId, ocupado, onSalvar, onExcluir, aberto, onToggle, resumo, nivel = 0 }) {
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

export function DisciplinaFolha({ disciplina, ocupado, onSalvar, onExcluir, extra = null }) {
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
    <div className="mt-1 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-[var(--brand-surface)] px-3 py-2">
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
          <div className="flex flex-wrap gap-2">
            {extra}
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
