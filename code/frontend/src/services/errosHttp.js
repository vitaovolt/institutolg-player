export function statusDeFalha(err) {
  return Number(err?.response?.status || 0)
}

export function ehErroDeProxy(err) {
  return [520, 522, 524, 499].includes(statusDeFalha(err))
}

export function mensagemDaFalha(err, fallback = 'Não foi possível concluir. Tente de novo.') {
  if (ehErroDeProxy(err)) {
    return 'A página demorou a responder. Se o arquivo já aparece no destino, atualize a tela.'
  }
  return (
    err?.response?.data?.errors?.email?.[0] ||
    err?.response?.data?.message ||
    err?.message ||
    fallback
  )
}

export function esperar(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}
