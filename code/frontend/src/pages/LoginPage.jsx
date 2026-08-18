import { useRef, useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import logo from '../assets/logo-instituto-lg.svg'

function normalizeEmail(value) {
  return String(value || '').trim().toLowerCase()
}

function emailValido(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
}

export default function LoginPage() {
  const { login, isAuthenticated, loading } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const submittingRef = useRef(false)
  const [email, setEmail] = useState('carolina@institutolg.local')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  if (!loading && isAuthenticated) {
    return <Navigate to="/biblioteca" replace />
  }

  async function onSubmit(event) {
    event.preventDefault()
    if (submittingRef.current) return

    const emailTrim = normalizeEmail(email)
    if (!emailTrim || !password) {
      setError('Informe e-mail e senha.')
      return
    }
    if (!emailValido(emailTrim)) {
      setError('Informe um e-mail válido.')
      return
    }

    submittingRef.current = true
    setSubmitting(true)
    setError('')

    try {
      await login(emailTrim, password)
      const destino = location.state?.from?.pathname || '/biblioteca'
      navigate(destino, { replace: true })
    } catch (err) {
      submittingRef.current = false
      setSubmitting(false)
      const msg =
        err.response?.data?.errors?.email?.[0] ||
        err.response?.data?.message ||
        'Não foi possível entrar. Verifique e-mail e senha.'
      setError(msg)
    }
  }

  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-12">
      <img src={logo} alt="Instituto LG" className="h-12 w-auto" />
      <p className="mt-6 m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Biblioteca de aulas
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">
        Painel da coordenação
      </h1>
      <p className="mt-2 text-[var(--brand-muted)]">Até 5 logins. Aluno não entra aqui.</p>

      <form
        onSubmit={onSubmit}
        className="mt-8 rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-5 shadow-[0_10px_30px_rgba(16,5,107,0.08)]"
      >
        <label className="block text-sm font-bold text-[var(--brand-primary)]">
          E-mail
          <input
            type="email"
            inputMode="email"
            autoComplete="username"
            autoCapitalize="none"
            autoCorrect="off"
            spellCheck={false}
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2.5 font-medium text-[var(--brand-ink)] outline-none focus:border-[var(--brand-primary)]"
            required
          />
        </label>

        <label className="mt-4 block text-sm font-bold text-[var(--brand-primary)]">
          Senha
          <input
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="mt-1 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2.5 font-medium text-[var(--brand-ink)] outline-none focus:border-[var(--brand-primary)]"
            required
          />
        </label>

        {error ? (
          <p className="mt-3 text-sm font-semibold text-[var(--brand-danger)]" role="alert">
            {error}
          </p>
        ) : null}

        <button
          type="submit"
          disabled={submitting}
          className="mt-5 w-full rounded-lg bg-[var(--brand-accent)] px-4 py-3 text-sm font-extrabold text-[var(--brand-ink)] hover:brightness-110 disabled:opacity-70"
        >
          {submitting ? 'Entrando…' : 'Entrar'}
        </button>
      </form>
    </main>
  )
}
