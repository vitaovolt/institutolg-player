import { expect, test } from '@playwright/test'
import { entrarComoCarolina, navPrincipal } from './helpers.js'

test.describe('Usuários do painel', () => {
  test('lista com Editar visível, cria e exclui outra conta', async ({ page }) => {
    await entrarComoCarolina(page)
    await navPrincipal(page).getByRole('link', { name: 'Usuários' }).click()
    await expect(page.getByTestId('pagina-usuarios')).toBeVisible()

    const carolina = page.getByTestId('usuario-carolina@institutolg.local')
    await expect(carolina.getByRole('button', { name: 'Editar' })).toBeVisible()
    await expect(carolina.getByRole('button', { name: 'Excluir' })).toBeDisabled()
    await expect(carolina).toContainText(/\d{2}\/\d{2}\/\d{4}/)

    const email = `e2e.${Date.now()}@institutolg.local`
    await page.getByLabel('Nome').fill('Conta E2E')
    await page.getByLabel('E-mail').fill(email)
    await page.getByLabel('Senha').fill('senha-segura')
    await page.getByRole('button', { name: 'Criar usuário' }).click()
    await expect(page.getByTestId('toast')).toContainText('Usuário criado')

    const linha = page.getByTestId(`usuario-${email}`)
    await expect(linha).toBeVisible()
    await expect(linha.getByRole('button', { name: 'Editar' })).toBeVisible()
    await expect(linha.getByRole('button', { name: 'Excluir' })).toBeEnabled()
    await expect(linha).toContainText(/\d{2}\/\d{2}\/\d{4}/)

    await linha.getByRole('button', { name: 'Editar' }).click()
    await page.getByLabel('Nome').fill('Conta E2E editada')
    await page.getByRole('button', { name: 'Salvar' }).click()
    await expect(page.getByTestId('toast')).toContainText('Usuário atualizado')
    await expect(page.getByTestId(`usuario-${email}`)).toContainText('Conta E2E editada')

    page.once('dialog', (dialog) => dialog.accept())
    await page.getByTestId(`usuario-${email}`).getByRole('button', { name: 'Excluir' }).click()
    await expect(page.getByTestId('toast')).toContainText('Usuário removido')
    await expect(page.getByTestId(`usuario-${email}`)).toHaveCount(0)
  })
})
