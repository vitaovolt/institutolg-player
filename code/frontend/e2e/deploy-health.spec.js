import { expect, test } from '@playwright/test'

const API = 'http://127.0.0.1:8000'

test.describe('F6 deploy health', () => {
  test('health local responde ok com check de database', async ({ request }) => {
    const res = await request.get(`${API}/api/v1/health`)
    expect(res.ok()).toBeTruthy()
    const body = await res.json()
    expect(body.success).toBeTruthy()
    expect(body.data.status).toBe('ok')
    expect(body.data.service).toBe('institutolg-player-api')
    expect(body.data.checks.database).toBe('ok')
    expect(res.headers()['x-frame-options']).toBe('DENY')
    expect(res.headers()['x-content-type-options']).toBe('nosniff')
  })

  test('player publico segue embeddable apos o vhost de producao', async ({ request }) => {
    const login = await request.post(`${API}/api/v1/auth/login`, {
      data: { email: 'carolina@institutolg.local', password: 'password' },
    })
    expect(login.ok()).toBeTruthy()
    const token = (await login.json()).data.token
    const bib = await request.get(`${API}/api/v1/biblioteca`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    const aula = (await bib.json()).data[0].turmas[0].disciplinas[0].aulas.find(
      (item) => item.titulo === 'Introdução',
    )
    expect(aula?.token_publico).toBeTruthy()
    const pagina = await request.get(`${API}/assistir/${aula.token_publico}`)
    expect(pagina.ok()).toBeTruthy()
    expect(pagina.headers()['x-frame-options'] || '').not.toBe('DENY')
    expect(pagina.headers()['content-security-policy'] || '').toContain('frame-ancestors')
  })
})
