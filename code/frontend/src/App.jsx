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
import CustosPage from './pages/CustosPage.jsx'
import OpsArmazenamentoPage from './pages/OpsArmazenamentoPage.jsx'
import LoginPage from './pages/LoginPage.jsx'
import UsuariosPage from './pages/UsuariosPage.jsx'
import EditarAulaPage from './pages/EditarAulaPage.jsx'
import SubstituirAulaPage from './pages/SubstituirAulaPage.jsx'

function Painel({ children }) {
  return (
    <ProtectedRoute>
      <AppShell>{children}</AppShell>
    </ProtectedRoute>
  )
}

export default function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/health" element={<BootstrapPage />} />
            <Route path="/biblioteca" element={<Painel><BibliotecaPage /></Painel>} />
            <Route path="/organizar" element={<Navigate to="/biblioteca" replace />} />
            <Route path="/custos" element={<Painel><CustosPage /></Painel>} />
            <Route path="/ops/armazenamento" element={<Painel><OpsArmazenamentoPage /></Painel>} />
            <Route path="/usuarios" element={<Painel><UsuariosPage /></Painel>} />
            <Route path="/aulas/:aulaId" element={<Painel><AulaDetalhePage /></Painel>} />
            <Route path="/aulas/:aulaId/editar" element={<Painel><EditarAulaPage /></Painel>} />
            <Route path="/aulas/:aulaId/substituir" element={<Painel><SubstituirAulaPage /></Painel>} />
            <Route path="/aulas/:aulaId/colar" element={<Painel><ColarEduqPage /></Painel>} />
            <Route path="/disciplinas/:disciplinaId/enviar" element={<Painel><EnviarAulaPage /></Painel>} />
            <Route path="/" element={<Navigate to="/biblioteca" replace />} />
          </Routes>
        </BrowserRouter>
      </ToastProvider>
    </AuthProvider>
  )
}
