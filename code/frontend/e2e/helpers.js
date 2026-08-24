import { expect } from '@playwright/test'

export async function entrarComoCarolina(page) {
  await page.goto('/login')
  await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible()
  await page.getByLabel('E-mail').fill('carolina@institutolg.local')
  await page.getByLabel('Senha').fill('password')
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page.getByTestId('pagina-biblioteca')).toBeVisible()
}

export async function expandirBiblioteca(page) {
  await page.getByTestId('btn-expandir').click()
}

export function navPrincipal(page) {
  return page.getByRole('navigation', { name: 'Principal' })
}

export function amostraPng() {
  return Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
  )
}

export function amostraMp4() {
  const ftypSize = Buffer.alloc(4)
  ftypSize.writeUInt32BE(24)
  const mdatSize = Buffer.alloc(4)
  mdatSize.writeUInt32BE(8)
  return Buffer.concat([
    ftypSize,
    Buffer.from('ftypisom'),
    Buffer.alloc(4),
    Buffer.from('isomavc1'),
    mdatSize,
    Buffer.from('mdat'),
  ])
}
