import client from './client'

export async function fetchBiblioteca() {
  const { data } = await client.get('/biblioteca')
  return data
}

export async function fetchImportarPasta() {
  const { data } = await client.get('/biblioteca/importar-pasta')
  return data
}

export async function importarPasta() {
  const { data } = await client.post('/biblioteca/importar-pasta')
  return data
}

export async function fetchResumoMes(mes) {
  const { data } = await client.get('/resumo-mes', { params: mes ? { mes } : {} })
  return data
}

export async function fetchCustoArmazenamento() {
  const { data } = await client.get('/ops/custo-armazenamento')
  return data
}
