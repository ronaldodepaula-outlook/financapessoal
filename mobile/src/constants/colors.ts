/** Paleta alinhada ao frontend web (frontend/styles/app.css) para manter identidade visual. */
export const colors = {
  green: '#205b4e',
  greenDark: '#163f37',
  mint: '#eaf2ec',
  ink: '#243b35',
  muted: '#7b8983',
  border: '#e5eae6',
  canvas: '#f6f8f6',
  red: '#b64b49',
  yellow: '#e7bc65',
  white: '#ffffff',
  // Tons extras para dar mais variedade a cartões/indicadores sem fugir da identidade visual.
  blue: '#4a7bab',
  purple: '#8c6bab',
  amber: '#c98a3b',
} as const;

/**
 * Paleta categórica (gráfico de rosca + pontos de categoria no Dashboard).
 * Mesma família de cores do gráfico do app web (frontend/public/assets/js/charts.js),
 * estendida para mais variedade — cicla via índice % length quando há mais
 * categorias do que cores.
 */
export const categoricalColors = [
  '#3e7561',
  '#e0bd75',
  '#7c9f98',
  '#cab69f',
  '#a7be85',
  '#d17b6b',
  '#6b8cae',
  '#b98cce',
  '#e0a458',
  '#698455',
] as const;
