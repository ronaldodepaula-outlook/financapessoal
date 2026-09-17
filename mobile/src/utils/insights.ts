import type {DashboardData} from '../types/dashboard';

export type InsightTone = 'positive' | 'warning' | 'neutral';

export interface FinancialInsight {
  id: string;
  text: string;
  tone: InsightTone;
}

/**
 * Observações computadas a partir dos dados já carregados do Dashboard —
 * NÃO é um assistente conversacional (não há chamada a nenhuma IA externa).
 * Uma IA de verdade exigiria uma chave de API e um proxy no backend que não
 * existem neste projeto; expor uma chave assim dentro do app mobile também
 * não seria seguro. Isso aqui é só heurística simples sobre os totais do mês.
 */
export function buildInsights(data: DashboardData): FinancialInsight[] {
  const insights: FinancialInsight[] = [];
  const categories = data.by_category.filter(category => Number(category.expenses) > 0);
  const totalExpenses = Number(data.summary.expenses);

  if (categories.length > 0 && totalExpenses > 0) {
    const top = categories[0];
    const share = Math.round((Number(top.expenses) / totalExpenses) * 100);
    insights.push({
      id: 'top-category',
      text: `"${top.label}" é sua maior categoria de gastos este mês, ${share}% do total de despesas.`,
      tone: share >= 40 ? 'warning' : 'neutral',
    });
  }

  const income = Number(data.summary.income);
  const expenses = Number(data.summary.expenses);
  if (income > 0) {
    if (expenses > income) {
      insights.push({
        id: 'over-income',
        text: 'Suas despesas já ultrapassaram as receitas registradas neste mês.',
        tone: 'warning',
      });
    } else if (expenses > 0) {
      const savedPercent = Math.round(((income - expenses) / income) * 100);
      if (savedPercent > 0) {
        insights.push({
          id: 'balance',
          text: `Você guardou ${savedPercent}% da sua renda até agora neste mês.`,
          tone: 'positive',
        });
      }
    }
  }

  const usage = data.summary.budget_usage_percent !== null ? Number(data.summary.budget_usage_percent) : null;
  if (usage !== null && !Number.isNaN(usage)) {
    insights.push({
      id: 'budget',
      text:
        usage >= 90
          ? `Você já usou ${usage}% do orçamento planejado para o mês.`
          : `${usage}% do orçamento planejado já foi utilizado.`,
      tone: usage >= 90 ? 'warning' : 'neutral',
    });
  }

  if (insights.length === 0) {
    insights.push({
      id: 'empty',
      text: 'Assim que houver mais lançamentos, vamos mostrar observações sobre seus gastos aqui.',
      tone: 'neutral',
    });
  }

  return insights.slice(0, 4);
}
