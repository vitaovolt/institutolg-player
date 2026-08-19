const TZ = 'America/Sao_Paulo'

function partesData(iso) {
  if (!iso) return null
  const data = new Date(iso)
  if (Number.isNaN(data.getTime())) return null
  return new Intl.DateTimeFormat('pt-BR', {
    timeZone: TZ,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).formatToParts(data)
}

export function formatarDataBR(iso) {
  const partes = partesData(iso)
  if (!partes) return '—'
  const dia = partes.find((p) => p.type === 'day')?.value
  const mes = partes.find((p) => p.type === 'month')?.value
  const ano = partes.find((p) => p.type === 'year')?.value
  if (!dia || !mes || !ano) return '—'
  return `${dia}/${mes}/${ano}`
}

export function formatarDataHoraBR(iso) {
  if (!iso) return '—'
  const data = new Date(iso)
  if (Number.isNaN(data.getTime())) return '—'
  return data.toLocaleString('pt-BR', {
    timeZone: TZ,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function formatarCompetencia(ym) {
  if (!ym || !/^\d{4}-\d{2}$/.test(ym)) return '—'
  const [ano, mes] = ym.split('-')
  return `${mes}/${ano}`
}

export function formatarCompetenciaExtenso(ym) {
  if (!ym || !/^\d{4}-\d{2}$/.test(ym)) return '—'
  const [ano, mes] = ym.split('-')
  const data = new Date(Date.UTC(Number(ano), Number(mes) - 1, 1))
  const nomeMes = data.toLocaleDateString('pt-BR', { month: 'long', timeZone: 'UTC' })
  return `${nomeMes} de ${ano}`
}

export function competenciaAtual() {
  const partes = new Intl.DateTimeFormat('pt-BR', {
    timeZone: TZ,
    year: 'numeric',
    month: '2-digit',
  }).formatToParts(new Date())
  const ano = partes.find((p) => p.type === 'year')?.value
  const mes = partes.find((p) => p.type === 'month')?.value
  return `${ano}-${mes}`
}

export function formatarReais(valor) {
  return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
