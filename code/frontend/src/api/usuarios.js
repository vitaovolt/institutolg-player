import client from './client'

export async function listarUsuarios() {
  const { data } = await client.get('/usuarios')
  return data
}

export async function criarUsuario(payload) {
  const { data } = await client.post('/usuarios', payload)
  return data
}

export async function atualizarUsuario(id, payload) {
  const { data } = await client.put(`/usuarios/${id}`, payload)
  return data
}

export async function excluirUsuario(id) {
  const { data } = await client.delete(`/usuarios/${id}`)
  return data
}
