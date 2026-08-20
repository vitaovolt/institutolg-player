import { useEffect, useRef, useState } from 'react'
import { atualizarUsuario, criarUsuario, excluirUsuario, listarUsuarios } from '../api/usuarios'
import Button from '../components/ui/Button.jsx'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { formatarDataBR } from '../services/formatar'

const FORM_VAZIO = { name: '', email: '', password: '', ativo: true }

export default function UsuariosPage() {
  const { user: eu } = useAuth()
  const { show: mostrarToast } = useToast()
  const submittingRef = useRef(false)
  const [lista, setLista] = useState(null)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [editandoId, setEditandoId] = useState(null)
  const [form, setForm] = useState(FORM_VAZIO)

  async function carregar() {
    const payload = await listarUsuarios()
    setLista(payload.data)
    setError('')
    return payload.data
  }

  useEffect(() => {
    carregar().catch(() => setError('Não foi possível carregar os usuários.'))
  }, [])

  function campo(nome, valor) {
    setForm((prev) => ({ ...prev, [nome]: valor }))
  }

  function limpar() {
    setEditandoId(null)
    setForm(FORM_VAZIO)
  }

  function comecarEdicao(usuario) {
    setEditandoId(usuario.id)
    setForm({
      name: usuario.name,
      email: usuario.email,
      password: '',
      ativo: usuario.ativo,
    })
  }

  async function onSubmit(event) {
    event.preventDefault()
    if (submittingRef.current) return

    const name = form.name.trim()
    const email = form.email.trim().toLowerCase()
    if (!name || !email) return
    if (!editandoId && !form.password) return

    submittingRef.current = true
    setSubmitting(true)
    try {
      const payload = {
        name,
        email,
        ativo: form.ativo,
      }
      if (form.password) payload.password = form.password

      if (editandoId) {
        await atualizarUsuario(editandoId, payload)
        mostrarToast('Usuário atualizado.')
      } else {
        await criarUsuario({ ...payload, password: form.password })
        mostrarToast('Usuário criado.')
      }
      await carregar()
      limpar()
    } catch (err) {
      const msg =
        err.response?.data?.errors?.email?.[0] ||
        err.response?.data?.errors?.password?.[0] ||
        err.response?.data?.errors?.name?.[0] ||
        err.response?.data?.errors?.ativo?.[0] ||
        err.response?.data?.message ||
        'Não foi possível salvar. Tente de novo.'
      mostrarToast(msg, 'erro')
    } finally {
      submittingRef.current = false
      setSubmitting(false)
    }
  }

  async function excluir(usuario) {
    if (submittingRef.current) return
    if (!window.confirm(`Excluir a conta de ${usuario.name}? Essa pessoa não entra mais no painel.`)) return
    submittingRef.current = true
    setSubmitting(true)
    try {
      await excluirUsuario(usuario.id)
      await carregar()
      if (editandoId === usuario.id) limpar()
      mostrarToast('Usuário removido.')
    } catch (err) {
      const msg = err.response?.data?.message || 'Não foi possível excluir. Tente de novo.'
      mostrarToast(msg, 'erro')
    } finally {
      submittingRef.current = false
      setSubmitting(false)
    }
  }

  const ocupado = submitting

  return (
    <main className="mx-auto max-w-2xl px-5 py-10" data-testid="pagina-usuarios">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">Contas</p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">Usuários do painel</h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        Editar e Excluir ficam em cada linha. Você não pode apagar ou desativar a própria conta.
      </p>

      {error ? <p className="mt-8 font-semibold text-[var(--brand-danger)]">{error}</p> : null}
      {!error && !lista ? <p className="mt-8 text-[var(--brand-muted)]">Carregando…</p> : null}

      {lista ? (
        <>
          <form
            className="mt-8 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4"
            onSubmit={onSubmit}
          >
            <p className="m-0 text-sm font-extrabold text-[var(--brand-primary)]">
              {editandoId ? 'Editar usuário' : 'Novo usuário'}
            </p>
            <label className="mt-3 block text-sm font-bold text-[var(--brand-primary)]">
              Nome
              <input
                value={form.name}
                onChange={(e) => campo('name', e.target.value)}
                disabled={ocupado}
                required
                className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2"
              />
            </label>
            <label className="mt-3 block text-sm font-bold text-[var(--brand-primary)]">
              E-mail
              <input
                type="email"
                value={form.email}
                onChange={(e) => campo('email', e.target.value)}
                disabled={ocupado}
                required
                className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2"
              />
            </label>
            <label className="mt-3 block text-sm font-bold text-[var(--brand-primary)]">
              Senha{editandoId ? ' (deixe em branco para manter)' : ''}
              <input
                type="password"
                value={form.password}
                onChange={(e) => campo('password', e.target.value)}
                disabled={ocupado}
                required={!editandoId}
                minLength={editandoId && !form.password ? undefined : 8}
                className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2"
              />
            </label>
            <label className="mt-3 flex items-center gap-2 text-sm font-bold text-[var(--brand-primary)]">
              <input
                type="checkbox"
                checked={form.ativo}
                disabled={ocupado || (editandoId != null && String(editandoId) === String(eu?.id))}
                onChange={(e) => campo('ativo', e.target.checked)}
              />
              Conta ativa
            </label>
            <div className="mt-4 flex flex-wrap gap-2">
              <Button type="submit" disabled={ocupado}>
                {ocupado ? 'Processando…' : editandoId ? 'Salvar' : 'Criar usuário'}
              </Button>
              {editandoId ? (
                <Button type="button" variant="ghost" onClick={limpar}>
                  Cancelar
                </Button>
              ) : null}
            </div>
          </form>

          <ul className="mt-6 space-y-2">
            {lista.map((usuario) => {
              const souEu = String(usuario.id) === String(eu?.id)
              return (
                <li
                  key={usuario.id}
                  data-testid={`usuario-${usuario.email}`}
                  className="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] px-4 py-3"
                >
                  <div>
                    <p className="m-0 font-extrabold text-[var(--brand-ink)]">
                      {usuario.name}
                      {souEu ? <span className="ml-2 text-xs font-semibold text-[var(--brand-muted)]">você</span> : null}
                    </p>
                    <p className="m-0 text-sm text-[var(--brand-muted)]">
                      {usuario.email} · {usuario.ativo ? 'ativa' : 'inativa'} · desde {formatarDataBR(usuario.created_at)}
                    </p>
                  </div>
                  <div className="flex gap-2">
                    <Button variant="secondary" disabled={ocupado} onClick={() => comecarEdicao(usuario)}>
                      Editar
                    </Button>
                    <Button variant="danger" disabled={ocupado || souEu} onClick={() => excluir(usuario)}>
                      Excluir
                    </Button>
                  </div>
                </li>
              )
            })}
          </ul>
        </>
      ) : null}
    </main>
  )
}
