import client from './client'

export async function criarCurso(nome) {
  const { data } = await client.post('/cursos', { nome })
  return data
}

export async function atualizarCurso(id, nome) {
  const { data } = await client.put(`/cursos/${id}`, { nome })
  return data
}

export async function excluirCurso(id) {
  const { data } = await client.delete(`/cursos/${id}`)
  return data
}

export async function criarTurma(cursoId, nome) {
  const { data } = await client.post(`/cursos/${cursoId}/turmas`, { nome })
  return data
}

export async function atualizarTurma(id, nome) {
  const { data } = await client.put(`/turmas/${id}`, { nome })
  return data
}

export async function excluirTurma(id) {
  const { data } = await client.delete(`/turmas/${id}`)
  return data
}

export async function criarDisciplina(turmaId, nome) {
  const { data } = await client.post(`/turmas/${turmaId}/disciplinas`, { nome })
  return data
}

export async function atualizarDisciplina(id, nome) {
  const { data } = await client.put(`/disciplinas/${id}`, { nome })
  return data
}

export async function excluirDisciplina(id) {
  const { data } = await client.delete(`/disciplinas/${id}`)
  return data
}
