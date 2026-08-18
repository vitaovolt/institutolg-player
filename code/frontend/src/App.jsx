import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './context/AuthContext.jsx'
import { ToastProvider } from './context/ToastContext.jsx'
import AppShell from './components/layout/AppShell.jsx'
import ProtectedRoute from './components/layout/ProtectedRoute.jsx'
import BibliotecaPage from './pages/BibliotecaPage.jsx'
import BootstrapPage from './pages/BootstrapPage.jsx'
import EnviarAulaPage from './pages/EnviarAulaPage.jsx'
import AulaDetalhePage from './pages/AulaDetalhePage.jsx'
import ColarEduqPage from './pages/ColarEduqPage.jsx'
import LoginPage from './pages/LoginPage.jsx'
import OrganizarPage from './pages/OrganizarPage.jsx'
import SubstituirAulaPage from './pages/SubstituirAulaPage.jsx'

export default function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/health" element={<BootstrapPage />} />
            <Route
              path="/biblioteca"
              element={
                <ProtectedRoute>
                  <AppShell>
                    <BibliotecaPage />
                  </AppShell>
                </ProtectedRoute>
              }
            />
            <Route
              path="/organizar"
              element={
                <ProtectedRoute>
                  <AppShell>
                    <OrganizarPage />
                  </AppShell>
                </ProtectedRoute>
              }
            />
            <Route
              path="/aulas/:aulaId"
              element={
                <ProtectedRoute>
                  <AppShell>
                    <AulaDetalhePage />
                  </AppShell>
                </ProtectedRoute>
              }
            />
            <Route
              path="/aulas/:aulaId/substituir"
              element={
                <ProtectedRoute>
                  <AppShell>
                    <SubstituirAulaPage />
                  </AppShell>
                </ProtectedRoute>
              }
            />
            <Route
              path="/aulas/:aulaId/colar"
              element={
                <ProtectedRoute>
                  <AppShell>
                    <ColarEduqPage />
                  </AppShell>
                </ProtectedRoute>
              }
            />
            <Route
              path="/disciplinas/:disciplinaId/enviar"
              element={
                <ProtectedRoute>
                  <AppShell>
                    <EnviarAulaPage />
                  </AppShell>
                </ProtectedRoute>
              }
            />
            <Route path="/" element={<Navigate to="/biblioteca" replace />} />
          </Routes>
        </BrowserRouter>
      </ToastProvider>
    </AuthProvider>
  )
}
