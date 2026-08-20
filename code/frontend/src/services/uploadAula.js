import { ehErroDeProxy, esperar } from './errosHttp'
import {
  concluirEnvio,
  completarPartesEnvio,
  fetchAula,
  iniciarEnvio,
  iniciarSubstituicao,
  pedirUrlDaParte,
  putArquivoEnvio,
} from '../api/aulas'

function tokenDoUploadPath(uploadPath) {
  const trecho = String(uploadPath || '').replace(/^\//, '')
  const match = trecho.match(/^envios\/([^/]+)/)
  return match ? match[1] : ''
}

async function completarPartesComRetry(token, parts, signal) {
  let ultimoErro = null
  for (let tentativa = 1; tentativa <= 4; tentativa += 1) {
    if (signal?.aborted) {
      throw new DOMException('Envio cancelado.', 'AbortError')
    }
    try {
      return await completarPartesEnvio(token, parts)
    } catch (err) {
      ultimoErro = err
      if (!ehErroDeProxy(err) && statusDeFalhaSafe(err) < 500) {
        throw err
      }
      await esperar(1500 * tentativa)
    }
  }
  throw ultimoErro
}

function statusDeFalhaSafe(err) {
  return Number(err?.response?.status || 0)
}

async function esperarObjetoPronto(aulaId, { signal, intervaloMs = 2000, tentativas = 90 } = {}) {
  for (let i = 0; i < tentativas; i += 1) {
    if (signal?.aborted) {
      throw new DOMException('Envio cancelado.', 'AbortError')
    }
    const payload = await fetchAula(aulaId)
    const aula = payload.data
    if (aula.tamanho_bytes && !aula.aguardando_objeto) {
      return aula
    }
    if (aula.status_preparo === 'erro' && aula.mensagem_erro) {
      throw new Error(aula.mensagem_erro)
    }
    await esperar(intervaloMs)
  }
  throw new Error('O arquivo ainda está sendo fechado no armazenamento. Atualize a página em instantes.')
}

async function enviarPorPartes(uploadPath, file, partSize, onProgress, signal) {
  const token = tokenDoUploadPath(uploadPath)
  if (!token) {
    throw new Error('Não foi possível iniciar o envio do arquivo.')
  }
  const tamanhoParte = Math.max(5 * 1024 * 1024, Number(partSize) || 100 * 1024 * 1024)
  const total = file.size
  const nPartes = Math.max(1, Math.ceil(total / tamanhoParte))
  const parts = []
  let enviado = 0

  for (let n = 1; n <= nPartes; n += 1) {
    if (signal?.aborted) {
      throw new DOMException('Envio cancelado.', 'AbortError')
    }
    const inicio = (n - 1) * tamanhoParte
    const blob = file.slice(inicio, Math.min(inicio + tamanhoParte, total))
    const pedido = await pedirUrlDaParte(token, n)
    const url = pedido.data?.url
    if (!url) {
      throw new Error('Não foi possível obter o destino da parte do arquivo.')
    }
    const resposta = await fetch(url, {
      method: 'PUT',
      body: blob,
      signal,
    })
    if (!resposta.ok) {
      throw new Error('Falha ao enviar uma parte do arquivo. Tente de novo.')
    }
    const etag = resposta.headers.get('ETag') || resposta.headers.get('etag')
    if (!etag) {
      throw new Error('O destino do arquivo não confirmou a parte. Tente de novo.')
    }
    parts.push({ part_number: n, etag })
    enviado += blob.size
    if (onProgress) {
      onProgress(Math.round((enviado / total) * 100))
    }
  }

  await completarPartesComRetry(token, parts, signal)
}

async function enviarArquivo(inicio, file, onProgress, signal) {
  const uploadPath = inicio.data.upload_path
  if (!uploadPath) return
  if (inicio.data.upload_method === 'multipart') {
    await enviarPorPartes(uploadPath, file, inicio.data.part_size, onProgress, signal)
    return
  }
  await putArquivoEnvio(uploadPath, file, onProgress, signal)
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
  await enviarArquivo(iniciado, file, onProgress, signal)
  await esperarObjetoPronto(aulaId, { signal })

  const concluido = await concluirEnvio(aulaId)
  return { aulaId, aula: concluido.data }
}

export async function substituirAulaCompleto({ aulaId, file, onProgress, signal }) {
  const iniciado = await iniciarSubstituicao(aulaId)
  const id = iniciado.data.aula.id
  await enviarArquivo(iniciado, file, onProgress, signal)
  await esperarObjetoPronto(id, { signal })

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
