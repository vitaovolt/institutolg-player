import { expect, test } from '@playwright/test'
import { amostraMp4, entrarComoCarolina, navPrincipal } from './helpers.js'

test('login → enviar MP4 → volta à biblioteca da turma → Pronta', async ({ page }) => {
  await entrarComoCarolina(page)
  await expect(navPrincipal(page).getByRole('link', { name: 'Biblioteca' })).toBeVisible()

  await page.getByTestId('disciplina-Cardiologia').getByRole('link', { name: 'Enviar aula' }).click()
  await expect(page.getByRole('heading', { name: 'Cardiologia' })).toBeVisible()
  await expect(page.getByTestId('arquivo-capa')).toBeVisible()

  await page.getByLabel('Título da aula').fill('Aula F3 Playwright')
  await page.getByTestId('arquivo-mp4').setInputFiles({
    name: 'aula-f3.mp4',
    mimeType: 'video/mp4',
    buffer: amostraMp4(),
  })
  await page.getByRole('button', { name: 'Enviar aula' }).click()
  await expect(page.getByRole('button', { name: 'Processando…' })).toBeDisabled()
  await expect(page.getByTestId('pagina-biblioteca')).toBeVisible({ timeout: 45_000 })
  await expect(page).toHaveURL(/turma=/)
  await expect(page.getByTestId('turma-Turma 2026-A')).toBeVisible()
  await expect(page.getByTestId('disciplina-Cardiologia')).toContainText('Aula F3 Playwright')
  await expect(page.getByTestId('disciplina-Cardiologia')).toContainText('Aula F3 Playwright · Pronta · publicada', { timeout: 45_000 })
  await expect(page.getByTestId('toast')).toContainText('Aula pronta')
})

test('recusa arquivo que não é MP4 e o toast fecha no X', async ({ page }) => {
  await entrarComoCarolina(page)
  await page.getByTestId('disciplina-Cardiologia').getByRole('link', { name: 'Enviar aula' }).click()

  await page.getByLabel('Título da aula').fill('Arquivo errado')
  await page.getByTestId('arquivo-mp4').setInputFiles({
    name: 'edicao.mov',
    mimeType: 'video/quicktime',
    buffer: Buffer.from('nao-e-mp4'),
  })
  await expect(page.getByTestId('toast')).toContainText('Tipo de arquivo não permitido')
  await page.getByTestId('toast-fechar').click()
  await expect(page.getByTestId('toast')).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Enviar aula' })).toBeEnabled()
  await expect(page.getByTestId('status-preparo')).toHaveCount(0)
})
