import { NavLink } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import logo from '../../assets/logo-instituto-lg.svg'

function classeNav({ isActive }) {
  return `text-sm font-bold no-underline ${isActive ? 'text-[var(--brand-accent)]' : 'text-[var(--brand-primary)]'}`
}

export default function AppShell({ children }) {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen">
      <header className="border-b border-[var(--brand-line)] bg-[var(--brand-surface)]">
        <div className="mx-auto flex max-w-4xl items-center justify-between gap-4 px-5 py-3">
          <nav aria-label="Principal" className="flex flex-wrap items-center gap-4">
            <NavLink to="/biblioteca" className="flex items-center gap-3 no-underline">
              <img src={logo} alt="Instituto LG" className="h-8 w-auto" />
            </NavLink>
            <NavLink to="/biblioteca" className={classeNav}>
              Biblioteca
            </NavLink>
            <NavLink to="/custos" className={classeNav}>
              Custos
            </NavLink>
            <NavLink to="/usuarios" className={classeNav}>
              Usuários
            </NavLink>
          </nav>
          <div className="flex items-center gap-3 text-sm">
            <span className="text-[var(--brand-muted)]">{user?.name}</span>
            <button
              type="button"
              onClick={logout}
              className="rounded-lg border border-[var(--brand-line)] px-3 py-1.5 font-semibold text-[var(--brand-primary)]"
            >
              Sair
            </button>
          </div>
        </div>
      </header>
      {children}
    </div>
  )
}
