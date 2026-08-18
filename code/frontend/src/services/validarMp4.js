const MAX_BYTES = 2 * 1024 * 1024 * 1024
const EXTENSOES = ['.mp4']
const MIMES_OK = ['', 'video/mp4']

export const MSG_MP4 = 'Tipo de arquivo não permitido, são permitidos somente arquivos MP4. Envie o export MP4 da aula pronta já editada.'
export const MSG_GRANDE = 'O arquivo é grande demais. Envie o export MP4 da aula pronta (máximo 2 GB).'

export function validarArquivoNoCliente(file) {
  if (!file) {
    return 'Escolha o arquivo MP4 da aula.'
  }
  if (file.size <= 0) {
    return MSG_MP4
  }
  if (file.size > MAX_BYTES) {
    return MSG_GRANDE
  }
  const nome = String(file.name || '').toLowerCase()
  const okExt = EXTENSOES.some((ext) => nome.endsWith(ext))
  if (!okExt) {
    return MSG_MP4
  }
  const mime = String(file.type || '').toLowerCase()
  if (mime && !MIMES_OK.includes(mime)) {
    return MSG_MP4
  }
  return null
}

export function rotuloStatusDrive(status) {
  switch (status) {
    case 'enviando':
      return 'Enviando…'
    case 'ok':
      return 'Ok'
    case 'erro':
      return 'Erro'
    default:
      return 'Pendente'
  }
}

export function rotuloStatusPreparo(status) {
  switch (status) {
    case 'enviando':
      return 'Enviando…'
    case 'preparando':
      return 'Preparando aula'
    case 'pronta':
      return 'Pronta'
    case 'erro':
      return 'Erro'
    default:
      return status || 'Rascunho'
  }
}
