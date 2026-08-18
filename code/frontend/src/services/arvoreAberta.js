export function estadoPadraoArvore(arvore) {
  const cursos = {}
  const turmas = {}
  ;(arvore || []).forEach((curso, i) => {
    cursos[curso.id] = i === 0
    ;(curso.turmas || []).forEach((turma, j) => {
      turmas[turma.id] = i === 0 && j === 0
    })
  })
  return { cursos, turmas }
}

export function recolherArvore(arvore) {
  const cursos = {}
  const turmas = {}
  ;(arvore || []).forEach((curso) => {
    cursos[curso.id] = false
    ;(curso.turmas || []).forEach((turma) => {
      turmas[turma.id] = false
    })
  })
  return { cursos, turmas }
}

export function expandirArvore(arvore) {
  const cursos = {}
  const turmas = {}
  ;(arvore || []).forEach((curso) => {
    cursos[curso.id] = true
    ;(curso.turmas || []).forEach((turma) => {
      turmas[turma.id] = true
    })
  })
  return { cursos, turmas }
}

export function mesclarAberto(arvore, prev = { cursos: {}, turmas: {} }, forcar = {}) {
  const padrao = estadoPadraoArvore(arvore)
  const cursos = { ...padrao.cursos, ...(prev.cursos || {}) }
  const turmas = { ...padrao.turmas, ...(prev.turmas || {}) }
  if (forcar.expandirCurso) cursos[forcar.expandirCurso] = true
  if (forcar.expandirTurma) turmas[forcar.expandirTurma] = true
  return { cursos, turmas }
}

export function caminhoDoFoco(arvore, { turmaId, disciplinaId } = {}) {
  for (const curso of arvore || []) {
    for (const turma of curso.turmas || []) {
      if (turmaId && String(turma.id) === String(turmaId)) {
        return { cursoId: curso.id, turmaId: turma.id }
      }
      if (disciplinaId) {
        const achou = (turma.disciplinas || []).some((d) => String(d.id) === String(disciplinaId))
        if (achou) return { cursoId: curso.id, turmaId: turma.id }
      }
    }
  }
  return null
}
