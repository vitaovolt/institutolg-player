import { expect, test } from '@playwright/test'
import { entrarComoCarolina, navPrincipal, amostraMp4, amostraPng } from './helpers.js'

const API = 'http://127.0.0.1:8000'

test.describe('F4 módulos', () => {
  test('detalhe: copiar HTML, despublicar e publicar', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write'])
    await entrarComoCarolina(page)
    await expect(navPrincipal(page).getByRole('link', { name: 'Custos' })).toBeVisible()
    await expect(navPrincipal(page).getByRole('link', { name: 'Usuários' })).toBeVisible()

    await page.getByTestId('aula-Introdução').getByRole('link', { name: 'Introdução' }).click()
    await expect(page.getByTestId('pagina-detalhe-aula')).toBeVisible()
    await expect(page.getByTestId('btn-sync-drive')).toBeVisible()
    await expect(page.getByTestId('btn-sync-drive')).toHaveText('Sincronizar com Google Drive')
    await expect(page.getByTestId('html-iframe')).toHaveValue(/iframe src=".*\/assistir\//)
    await expect(page.getByTestId('status-publicada')).toHaveText('Sim')

    await page.getByTestId('btn-copiar-html').click()
    await expect(page.getByTestId('toast')).toContainText('HTML copiado')
    const html = await page.getByTestId('html-iframe').inputValue()
    expect(html).toContain('<iframe')
    expect(html).toContain('/assistir/')

    await page.getByTestId('btn-despublicar').click()
    await expect(page.getByTestId('status-publicada')).toHaveText('Não', { timeout: 15_000 })
    await expect(page.getByTestId('toast')).toContainText('despublicada')
    await expect(page.getByTestId('status-cobranca')).toHaveText('R$ 3,80/mês')

    const src = html.match(/src="([^"]+)"/)?.[1]
    expect(src).toBeTruthy()
    const player = await context.newPage()
    await player.goto(src.replace('localhost', '127.0.0.1'))
    await expect(player.getByTestId('player-indisponivel')).toBeVisible()
    await player.close()

    await page.getByTestId('btn-publicar').click()
    await expect(page.getByTestId('status-publicada')).toHaveText('Sim', { timeout: 15_000 })
  })

  test('aluno assiste no embed da Eduq sem download', async ({ page, context }) => {
    await entrarComoCarolina(page)
    await page.getByTestId('aula-Introdução').getByRole('link', { name: 'Introdução' }).click()
    const html = await page.getByTestId('html-iframe').inputValue()
    const src = html.match(/src="([^"]+)"/)?.[1]?.replace('localhost', '127.0.0.1')
    expect(src).toBeTruthy()

    const demo = page.getByTestId('link-ver-aluno')
    await expect(demo).toBeVisible()
    const eduqUrl = (await demo.getAttribute('href'))?.replace('localhost', '127.0.0.1')
    expect(eduqUrl).toContain('/eduq/')

    const eduq = await context.newPage()
    await eduq.goto(eduqUrl)
    await expect(eduq.getByTestId('mock-eduq')).toBeVisible()
    await expect(eduq.getByTestId('iframe-player')).toHaveAttribute('src', /\/assistir\//)

    const player = await context.newPage()
    await player.goto(src)
    const video = player.getByTestId('player-video')
    await expect(video).toBeVisible()
    await expect(video).toHaveAttribute('controlslist', /nodownload/)
    await expect(player.getByTestId('player-speeds')).toBeAttached()
    await player.getByTestId('player-wrap').hover()
    await expect(player.getByTestId('player-speeds')).toBeVisible()
    await player.getByTestId('player-speed-2').click()
    await expect(video).toHaveJSProperty('playbackRate', 2)
    await player.getByTestId('player-speed-4').click()
    await expect(video).toHaveJSProperty('playbackRate', 4)
    await expect(player.locator('[download]')).toHaveCount(0)
    await expect(player.getByRole('link', { name: /download/i })).toHaveCount(0)
  })

  test('colar na Eduq mostra o widget Iframe', async ({ page }) => {
    await entrarComoCarolina(page)
    await page.getByTestId('aula-Casos clínicos').getByRole('link', { name: 'Casos clínicos' }).click()
    await page.getByRole('link', { name: 'Colar na Eduq' }).click()
    await expect(page.getByTestId('pagina-colar-eduq')).toBeVisible()
    await expect(page.getByTestId('mock-widget-eduq')).toContainText('Iframe')
    await expect(page.getByTestId('html-iframe')).toHaveValue(/iframe/)
  })

  test('biblioteca: criar turma com Editar e Excluir visíveis', async ({ page }) => {
    await entrarComoCarolina(page)
    await expect(page.getByTestId('pagina-biblioteca')).toBeVisible()
    await expect(page.getByText('Resumo do mês')).toHaveCount(0)
    await expect(page.getByTestId('curso-Pós-graduação em Saúde').getByRole('button', { name: 'Editar' })).toBeVisible()
    await expect(page.getByTestId('curso-Pós-graduação em Saúde').getByRole('button', { name: 'Excluir' })).toBeVisible()

    const cursoId = await page.evaluate(async () => {
      const token = localStorage.getItem('ilg_token')
      const res = await fetch('/api/v1/biblioteca', { headers: { Authorization: `Bearer ${token}` } })
      const json = await res.json()
      return json.data[0].id
    })

    await page.getByTestId(`input-turma-${cursoId}`).fill('Turma 2026-B')
    await page.getByRole('button', { name: 'Adicionar turma' }).click()
    await expect(page.getByTestId('toast')).toContainText('Turma criada')
    await expect(page.getByTestId('turma-Turma 2026-B').getByRole('button', { name: 'Editar' })).toBeVisible()
    await expect(page.getByTestId('turma-Turma 2026-B').getByRole('button', { name: 'Excluir' })).toBeVisible()
    await expect(page.getByTestId('turma-Turma 2026-B-toggle')).toHaveAttribute('aria-expanded', 'true')

    const turmaNovaId = await page.evaluate(async () => {
      const token = localStorage.getItem('ilg_token')
      const res = await fetch('/api/v1/biblioteca', { headers: { Authorization: `Bearer ${token}` } })
      const json = await res.json()
      return json.data[0].turmas.find((item) => item.nome === 'Turma 2026-B').id
    })
    await expect(page.getByTestId(`input-disciplina-${turmaNovaId}`)).toBeVisible()

    await page.getByTestId(`input-disciplina-${turmaNovaId}`).fill('Pediatria')
    await page
      .getByTestId(`input-disciplina-${turmaNovaId}`)
      .locator('..')
      .getByRole('button', { name: 'Adicionar disciplina' })
      .click()
    await expect(page.getByTestId('toast')).toContainText('Disciplina criada')
    await expect(page.getByTestId('disciplina-Pediatria')).toBeVisible()
    await expect(page.getByTestId('turma-Turma 2026-B-toggle')).toHaveAttribute('aria-expanded', 'true')

    await page.getByTestId('input-novo-curso').fill('Treinamento E2E')
    await page.getByRole('button', { name: 'Salvar curso' }).click()
    await expect(page.getByTestId('toast')).toContainText('Curso criado')
    await expect(page.getByTestId('curso-Treinamento E2E-toggle')).toHaveAttribute('aria-expanded', 'true')

    await page.getByTestId('btn-recolher').click()
    await expect(page.getByTestId(`input-turma-${cursoId}`)).toHaveCount(0)
    await page.getByTestId('btn-expandir').click()
    await expect(page.getByTestId(`input-turma-${cursoId}`)).toBeVisible()
  })

  test('biblioteca: recolher/expandir e enviar tem capa opcional', async ({ page }) => {
    await entrarComoCarolina(page)
    await expect(page.getByTestId('disciplina-Cardiologia').getByRole('link', { name: 'Enviar aula' })).toBeVisible()
    await page.getByTestId('btn-recolher').click()
    await expect(page.getByTestId('disciplina-Cardiologia')).toHaveCount(0)
    await page.getByTestId('btn-expandir').click()
    await expect(page.getByTestId('disciplina-Cardiologia').getByRole('link', { name: 'Enviar aula' })).toBeVisible()

    await page.getByTestId('disciplina-Cardiologia').getByRole('link', { name: 'Enviar aula' }).click()
    await expect(page.getByTestId('arquivo-capa')).toBeVisible()
    await expect(page.getByTestId('arquivo-mp4')).toBeVisible()
  })

  test('enviar MP4 já com capa opcional', async ({ page }) => {
    await entrarComoCarolina(page)
    await page.getByTestId('disciplina-Cardiologia').getByRole('link', { name: 'Enviar aula' }).click()
    await page.getByLabel('Título da aula').fill('Aula com capa F4')
    await page.getByTestId('arquivo-mp4').setInputFiles({
      name: 'aula-capa.mp4',
      mimeType: 'video/mp4',
      buffer: amostraMp4(),
    })
    await page.getByTestId('arquivo-capa').setInputFiles({
      name: 'capa.png',
      mimeType: 'image/png',
      buffer: amostraPng(),
    })
    await page.getByRole('button', { name: 'Enviar aula' }).click()
    await expect(page.getByTestId('pagina-biblioteca')).toBeVisible({ timeout: 45_000 })
    await expect(page.getByTestId('aula-Aula com capa F4').locator('img')).toBeVisible()
  })

  test('detalhe: envia foto de capa', async ({ page }) => {
    await entrarComoCarolina(page)
    await page.getByTestId('aula-Introdução').getByRole('link', { name: 'Introdução' }).click()
    await page.getByTestId('arquivo-capa').setInputFiles({
      name: 'capa.png',
      mimeType: 'image/png',
      buffer: amostraPng(),
    })
    await page.getByTestId('btn-salvar-capa').click()
    await expect(page.getByTestId('toast')).toContainText('Capa da aula salva')
    await expect(page.getByTestId('preview-capa')).toBeVisible()
  })

  test('player público responde na API sem login do painel', async ({ request }) => {
    const health = await request.get(`${API}/api/v1/health`)
    expect(health.ok()).toBeTruthy()

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
    expect(await pagina.text()).toContain('nodownload')
  })

  test('detalhe: edita o nome de uma aula de teste', async ({ page }) => {
    await entrarComoCarolina(page)
    const criado = await page.evaluate(async () => {
      const token = localStorage.getItem('ilg_token')
      const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json' }
      const bib = await fetch('/api/v1/biblioteca', { headers })
      const json = await bib.json()
      const disciplinaId = json.data[0].turmas[0].disciplinas[0].id
      const res = await fetch(`/api/v1/disciplinas/${disciplinaId}/aulas`, {
        method: 'POST',
        headers,
        body: JSON.stringify({ titulo: `Aula editar ${Date.now()}` }),
      })
      const aula = await res.json()
      return aula.data.id
    })

    await page.goto(`/aulas/${criado}`)
    await expect(page.getByTestId('pagina-detalhe-aula')).toBeVisible()
    await page.getByTestId('btn-editar-aula').click()
    await expect(page.getByTestId('pagina-editar-aula')).toBeVisible()
    await expect(page.getByTestId('pagina-editar-aula')).toContainText('pasta compartilhada')
    await expect(page.getByTestId('pagina-editar-aula')).not.toContainText(/R2|AWS|S3|Cloudflare/i)

    const novoNome = `Aula editada E2E ${Date.now()}`
    await page.getByLabel('Nome da aula').fill(novoNome)
    await page.getByTestId('btn-salvar-aula').click()
    await expect(page.getByTestId('pagina-detalhe-aula')).toBeVisible()
    await expect(page.getByRole('heading', { name: novoNome })).toBeVisible()
    await expect(page.getByTestId('toast')).toContainText('Nome da aula atualizado')
  })

  test('detalhe: exclui a aula Revisão e volta à biblioteca', async ({ page }) => {
    await entrarComoCarolina(page)
    await page.getByTestId('aula-Revisão').getByRole('link', { name: 'Revisão' }).click()
    await expect(page.getByTestId('pagina-detalhe-aula')).toBeVisible()

    page.once('dialog', async (dialog) => {
      expect(dialog.message()).toContain('Revisão')
      expect(dialog.message()).toContain('pasta compartilhada')
      expect(dialog.message()).not.toMatch(/R2|AWS|S3|Cloudflare/i)
      await dialog.accept()
    })

    await page.getByTestId('btn-excluir-aula').click()
    await expect(page.getByTestId('pagina-biblioteca')).toBeVisible()
    await expect(page.getByTestId('aula-Revisão')).toHaveCount(0)
    await expect(page.getByTestId('aula-Introdução')).toBeVisible()
    await expect(page.getByTestId('aula-Casos clínicos')).toBeVisible()
    await expect(page.getByTestId('toast')).toContainText('Aula excluída')
  })

  test('detalhe: sincroniza aula de teste com a pasta compartilhada', async ({ page }) => {
    await entrarComoCarolina(page)
    const titulo = `Aula sync Drive ${Date.now()}`
    await page.getByTestId('disciplina-Cardiologia').getByRole('link', { name: 'Enviar aula' }).click()
    await page.getByLabel('Título da aula').fill(titulo)
    await page.getByTestId('arquivo-mp4').setInputFiles({
      name: 'aula-sync.mp4',
      mimeType: 'video/mp4',
      buffer: amostraMp4(),
    })
    await page.getByRole('button', { name: 'Enviar aula' }).click()
    await expect(page.getByTestId('pagina-biblioteca')).toBeVisible({ timeout: 45_000 })
    await page.getByTestId(`aula-${titulo}`).getByRole('link', { name: titulo }).click()
    await expect(page.getByTestId('pagina-detalhe-aula')).toBeVisible()
    await expect(page.getByTestId('btn-sync-drive')).toBeVisible({ timeout: 45_000 })
    await expect(page.getByTestId('btn-sync-drive')).toHaveText('Sincronizar com Google Drive')
    await expect(page.getByTestId('ajuda-sync-drive')).toContainText('Não apaga outros arquivos da pasta')
    await expect(page.getByTestId('pagina-detalhe-aula')).not.toContainText(/R2|AWS|S3|Cloudflare/i)

    await page.getByTestId('btn-sync-drive').click()
    await expect(page.getByTestId('toast')).toContainText('pasta compartilhada')
  })
})
