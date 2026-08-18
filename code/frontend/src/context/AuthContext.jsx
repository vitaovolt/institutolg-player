import { createContext, useContext, useEffect, useState } from 'react'
import { fetchMe, login as apiLogin, logout as apiLogout } from '../api/auth'
import { getAuthToken, setAuthToken, USER_KEY } from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem(USER_KEY) || 'null')
    } catch {
      return null
    }
  })
  const [loading, setLoading] = useState(Boolean(getAuthToken()))

  useEffect(() => {
    let alive = true
    if (!getAuthToken()) {
      setLoading(false)
      return undefined
    }

    fetchMe()
      .then((payload) => {
        if (!alive) return
        setUser(payload.data)
        localStorage.setItem(USER_KEY, JSON.stringify(payload.data))
      })
      .catch(() => {
        if (!alive) return
        setAuthToken(null)
        setUser(null)
        localStorage.removeItem(USER_KEY)
      })
      .finally(() => {
        if (alive) setLoading(false)
      })

    return () => {
      alive = false
    }
  }, [])

  async function login(email, password) {
    const payload = await apiLogin(email, password)
    aplicarSessao(payload.data.token, payload.data.user)
    return payload.data.user
  }

  function aplicarSessao(token, userData) {
    setAuthToken(token)
    setUser(userData)
    localStorage.setItem(USER_KEY, JSON.stringify(userData))
  }

  async function logout() {
    const tokenAntes = getAuthToken()
    try {
      if (tokenAntes) await apiLogout()
    } catch {
      // token já inválido
    } finally {
      if (getAuthToken() === tokenAntes) {
        setAuthToken(null)
        setUser(null)
        localStorage.removeItem(USER_KEY)
      }
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, isAuthenticated: Boolean(user) }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth deve ser usado dentro de AuthProvider')
  return ctx
}
