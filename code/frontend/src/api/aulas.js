import client from './client'

export async function fetchDisciplina(id) {
  const { data } = await client.get(`/disciplinas/${id}`)
  return data
}

export async function fetchAula(id) {
  const { data } = await client.get(`/aulas/${id}`)
  return data
}

export async function atualizarAula(id, payload) {
  const { data } = await client.put(`/aulas/${id}`, payload)
  return data
}

export async function moverAula(id, disciplinaId) {
  const { data } = await client.post(`/aulas/${id}/mover`, { disciplina_id: disciplinaId })
  return data
}

export async function iniciarEnvio(disciplinaId, payload, chaveIdempotencia) {
  const { data } = await client.post(`/disciplinas/${disciplinaId}/envios`, payload, {
    headers: { 'Idempotency-Key': chaveIdempotencia },
  })
  return data
}

export async function putArquivoEnvio(uploadPath, file, onProgress, signal) {
  const { data } = await client.put(String(uploadPath).replace(/^\//, ''), file, {
    headers: { 'Content-Type': file.type || 'video/mp4' },
    timeout: 12 * 60 * 60 * 1000,
    signal,
    onUploadProgress: (event) => {
      if (!event.total || !onProgress) return
      onProgress(Math.round((event.loaded / event.total) * 100))
    },
    transformRequest: [(body) => body],
  })
  return data
}

export async function pedirUrlDaParte(token, partNumber) {
  const { data } = await client.post(`envios/${token}/partes`, { part_number: partNumber })
  return data
}

export async function completarPartesEnvio(token, parts) {
  const { data } = await client.post(`envios/${token}/completar-multipart`, { parts }, {
    timeout: 5 * 60 * 1000,
  })
  return data
}

export async function concluirEnvio(aulaId) {
  const { data } = await client.post(`/aulas/${aulaId}/envios/concluir`)
  return data
}

export async function reprocessarEnvio(aulaId) {
  const { data } = await client.post(`/aulas/${aulaId}/envios/reprocessar`)
  return data
}

export async function iniciarSubstituicao(aulaId) {
  const { data } = await client.post(`/aulas/${aulaId}/envios/substituir`)
  return data
}

export async function publicarAula(aulaId) {
  const { data } = await client.post(`/aulas/${aulaId}/publicar`)
  return data
}

export async function despublicarAula(aulaId) {
  const { data } = await client.post(`/aulas/${aulaId}/despublicar`)
  return data
}

export async function sincronizarDrive(aulaId) {
  const { data } = await client.post(`/aulas/${aulaId}/drive/sincronizar`)
  return data
}

export async function salvarCapa(aulaId, file) {
  const form = new FormData()
  form.append('capa', file)
  const { data } = await client.post(`/aulas/${aulaId}/capa`, form, {
    headers: { 'Content-Type': undefined },
  })
  return data
}

export async function removerCapa(aulaId) {
  const { data } = await client.delete(`/aulas/${aulaId}/capa`)
  return data
}

export async function excluirAula(id) {
  const { data } = await client.delete(`/aulas/${id}`)
  return data
}
