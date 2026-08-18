const MAX_BYTES = 2 * 1024 * 1024
const EXTENSOES = ['.jpg', '.jpeg', '.png', '.webp']
const MIMES_OK = ['', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp']

export const MSG_CAPA = 'Tipo de arquivo não permitido. Envie uma foto JPG ou PNG para a capa da aula.'
export const MSG_CAPA_GRANDE = 'A foto é grande demais. Envie um JPG ou PNG de até 2 MB.'

export function validarCapaNoCliente(file) {
  if (!file) return null
  if (file.size <= 0) return MSG_CAPA
  if (file.size > MAX_BYTES) return MSG_CAPA_GRANDE
  const nome = String(file.name || '').toLowerCase()
  const okExt = EXTENSOES.some((ext) => nome.endsWith(ext))
  if (!okExt) return MSG_CAPA
  const mime = String(file.type || '').toLowerCase()
  if (mime && !MIMES_OK.includes(mime)) return MSG_CAPA
  return null
}
