import { expect, test } from '@playwright/test'
import { entrarComoCarolina } from './helpers.js'

const API = 'http://127.0.0.1:8000'

test.describe('F5 hardening', () => {
  test('health da API expõe headers de segurança', async ({ request }) => {
    const res = await request.get(`${API}/api/v1/health`)
    expect(res.ok()).toBeTruthy()
    expect(res.headers()['x-content-type-options']).toBe('nosniff')
    expect(res.headers()['x-frame-options']).toBe('DENY')
    expect(res.headers()['content-security-policy'] || '').toContain("default-src 'none'")
  })

  test('biblioteca sem token responde 401', async ({ request }) => {
    const res = await request.get(`${API}/api/v1/biblioteca`, {
      headers: { Accept: 'application/json' },
    })
    expect(res.status()).toBe(401)
  })

  test('player embed segue sem X-Frame-Options DENY', async ({ request }) => {
    const login = await request.post(`${API}/api/v1/auth/login`, {
      data: { email: 'carolina@institutolg.local', password: 'password' },
    })
    const token = (await login.json()).data.token
    const bib = await request.get(`${API}/api/v1/biblioteca`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    const aula = (await bib.json()).data[0].turmas[0].disciplinas[0].aulas.find((item) => item.titulo === 'Introdução')
    const pagina = await request.get(`${API}/assistir/${aula.token_publico}`)
    expect(pagina.ok()).toBeTruthy()
    expect(pagina.headers()['x-frame-options'] || '').not.toBe('DENY')
    expect(pagina.headers()['content-security-policy'] || '').toContain('frame-ancestors')
  })

  test('coordenação ativa entra no painel', async ({ page }) => {
    await entrarComoCarolina(page)
    await expect(page.getByTestId('pagina-biblioteca')).toBeVisible()
  })
})
