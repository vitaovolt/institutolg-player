import { concluirEnvio, fetchAula, iniciarEnvio, iniciarSubstituicao, putArquivoEnvio } from '../api/aulas'

function esperar(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

export async function enviarAulaCompleto({
  disciplinaId,
  titulo,
  file,
  chaveIdempotencia,
  onProgress,
  signal,
}) {
  const iniciado = await iniciarEnvio(
    disciplinaId,
    {
      titulo,
      chave_idempotencia: chaveIdempotencia,
      tamanho_bytes: file.size,
    },
    chaveIdempotencia,
  )

  const aulaId = iniciado.data.aula.id
  const uploadPath = iniciado.data.upload_path

  if (uploadPath) {
    await putArquivoEnvio(uploadPath, file, onProgress, signal)
  }

  const concluido = await concluirEnvio(aulaId)
  return { aulaId, aula: concluido.data }
}

export async function substituirAulaCompleto({ aulaId, file, onProgress, signal }) {
  const iniciado = await iniciarSubstituicao(aulaId)
  const uploadPath = iniciado.data.upload_path
  const id = iniciado.data.aula.id

  if (uploadPath) {
    await putArquivoEnvio(uploadPath, file, onProgress, signal)
  }

  const concluido = await concluirEnvio(id)
  return { aulaId: id, aula: concluido.data }
}

export async function esperarAulaPronta(aulaId, { signal, intervaloMs = 2000, tentativas = 60 } = {}) {
  for (let i = 0; i < tentativas; i += 1) {
    if (signal?.aborted) {
      throw new DOMException('Envio cancelado.', 'AbortError')
    }
    const payload = await fetchAula(aulaId)
    const aula = payload.data
    if (aula.status_preparo === 'pronta') {
      return aula
    }
    if (aula.status_preparo === 'erro') {
      const err = new Error(aula.mensagem_erro || 'A preparação falhou. Tente de novo.')
      err.aula = aula
      throw err
    }
    await esperar(intervaloMs)
  }
  throw new Error('A preparação está demorando. Atualize a página em instantes.')
}
