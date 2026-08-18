import Button from '../ui/Button.jsx'

export default function ControlesArvore({ onRecolher, onExpandir }) {
  return (
    <div className="mt-4 flex flex-wrap gap-2">
      <Button variant="secondary" data-testid="btn-recolher" onClick={onRecolher}>
        Recolher tudo
      </Button>
      <Button variant="secondary" data-testid="btn-expandir" onClick={onExpandir}>
        Expandir tudo
      </Button>
    </div>
  )
}
