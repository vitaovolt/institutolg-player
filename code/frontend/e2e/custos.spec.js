import { expect, test } from '@playwright/test'
import { entrarComoCarolina, navPrincipal } from './helpers.js'

test.describe('Painel de custos', () => {
  test('mostra totais, filtro do mês e aulas por mês', async ({ page }) => {
    await entrarComoCarolina(page)
    await navPrincipal(page).getByRole('link', { name: 'Custos' }).click()
    await expect(page.getByTestId('pagina-custos')).toBeVisible()
    await expect(page.getByTestId('custo-total')).toContainText('R$')
    await expect(page.getByTestId('kpi-importadas')).toContainText('3')
    await expect(page.getByTestId('kpi-publicadas')).toContainText('2')
    await expect(page.getByTestId('grafico-aulas-mes')).toBeVisible()
    await expect(page.getByTestId('grafico-aulas-mes')).toContainText(/\d{2}\/\d{4}/)

    await page.getByTestId('filtro-mes').fill('2026-07')
    await expect(page.getByTestId('pagina-custos')).toContainText('julho de 2026')
    await expect(page.getByTestId('kpi-enviadas')).toContainText('Enviadas em 07/2026')
    await expect(page.getByTestId('kpi-importadas')).toContainText('3')
  })
})
