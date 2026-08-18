import client from './client'

export async function fetchBiblioteca() {
  const { data } = await client.get('/biblioteca')
  return data
}

export async function fetchResumoMes() {
  const { data } = await client.get('/resumo-mes')
  return data
}
