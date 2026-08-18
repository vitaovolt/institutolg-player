import { useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { fetchAula } from '../api/aulas'
import { useToast } from '../context/ToastContext'

export default function ColarEduqPage() {
  const { aulaId } = useParams()
  const { show: mostrarToast } = useToast()
  const [aula, setAula] = useState(null)
  const [error, setError] = useState('')
  const copiedRef = useRef(false)

  useEffect(() => {
    let cancelled = false
    fetchAula(aulaId)
      .then((payload) => {
        if (!cancelled) setAula(payload.data)
      })
      .catch(() => {
        if (!cancelled) setError('Não foi possível carregar a aula.')
      })
    return () => {
      cancelled = true
    }
  }, [aulaId])

  async function copiarHtml() {
    if (!aula?.html_iframe || copiedRef.current) return
    copiedRef.current = true
    try {
      await navigator.clipboard.writeText(aula.html_iframe)
      mostrarToast('HTML copiado. Cole no campo Iframe da Eduq.')
    } catch {
      mostrarToast('Selecione o HTML e copie com Ctrl+C.', 'erro')
    } finally {
      copiedRef.current = false
    }
  }

  if (error) {
    return (
      <main className="mx-auto max-w-3xl px-5 py-10">
        <p className="font-semibold text-[var(--brand-danger)]">{error}</p>
      </main>
    )
  }

  if (!aula) {
    return (
      <main className="mx-auto max-w-3xl px-5 py-10">
        <p className="text-[var(--brand-muted)]">Carregando…</p>
      </main>
    )
  }

  return (
    <main className="mx-auto max-w-3xl px-5 py-10" data-testid="pagina-colar-eduq">
      <p className="m-0 text-xs font-extrabold tracking-[0.14em] uppercase text-[var(--brand-primary)]">
        Colar na Eduq
      </p>
      <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-[var(--brand-ink)]">{aula.titulo}</h1>
      <p className="mt-2 text-[var(--brand-muted)]">
        Na Eduq, no bloco <strong>Vídeo</strong>, escolha o modo <strong>Iframe</strong> (ícone do monitor com &lt; &gt;).
        O painel Instituto LG não envia nada sozinho.
      </p>

      <div className="mt-6 grid gap-4 md:grid-cols-2">
        <section className="rounded-[10px] border border-[var(--brand-line)] bg-[var(--brand-surface)] p-4">
          <label className="text-sm font-bold text-[var(--brand-primary)]">
            HTML do player
            <textarea
              data-testid="html-iframe"
              readOnly
              value={aula.html_iframe || ''}
              className="mt-2 w-full rounded-lg border border-[var(--brand-line)] px-3 py-2 font-mono text-xs"
              rows={5}
            />
          </label>
          <button
            type="button"
            onClick={copiarHtml}
            disabled={!aula.html_iframe}
            className="mt-3 rounded-lg bg-[var(--brand-accent)] px-4 py-3 text-sm font-extrabold text-[var(--brand-ink)] disabled:opacity-50"
          >
            Copiar HTML
          </button>
        </section>

        <section className="rounded-[10px] border border-[var(--brand-line)] bg-[#F4FBFC] p-4" data-testid="mock-widget-eduq">
          <p className="m-0 text-sm font-extrabold text-[#2bb0c7]">Vídeo</p>
          <p className="mt-2 text-xs font-bold uppercase tracking-wide text-[#2bb0c7]">Iframe — usar este</p>
          <label className="mt-3 block text-sm font-bold">
            Vídeo*
            <input
              readOnly
              className="mt-1 w-full rounded border border-[#c5e4ea] bg-white px-2 py-2 font-mono text-xs"
              value={aula.html_iframe || ''}
            />
          </label>
          <p className="mt-3 text-sm text-[var(--brand-muted)]">
            Depois de colar, a coordenação salva a aula <strong>na Eduq</strong>.
          </p>
        </section>
      </div>

      <p className="mt-5 flex flex-wrap gap-4 text-sm">
        {aula.url_demonstracao_eduq ? (
          <a href={aula.url_demonstracao_eduq} target="_blank" rel="noreferrer" className="font-semibold text-[var(--brand-primary)]">
            Ver como o aluno vê
          </a>
        ) : null}
        <Link to={`/aulas/${aula.id}`} className="font-semibold text-[var(--brand-primary)]">
          Voltar ao detalhe
        </Link>
      </p>
    </main>
  )
}
