import React, {useCallback, useMemo, useRef, useState} from 'react';
import {RefreshControl, ScrollView, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import {useFocusEffect, useNavigation} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {getDashboard} from '../../api/dashboard';
import {getReportSummary} from '../../api/reports';
import {Button} from '../../components/Button';
import {Card, StatCard} from '../../components/Card';
import {Badge, CategoryBadge, statusTone} from '../../components/CategoryBadge';
import {DonutChart} from '../../components/DonutChart';
import {EmptyState} from '../../components/EmptyState';
import {Fab} from '../../components/Fab';
import {InsightsCard} from '../../components/InsightsCard';
import {Loading} from '../../components/Loading';
import {useAuth} from '../../hooks/useAuth';
import {theme} from '../../theme';
import {formatCurrency} from '../../utils/currency';
import {currentCompetence, formatMonthYear, formatRelativeShort, shiftCompetence} from '../../utils/date';
import {buildInsights} from '../../utils/insights';
import type {RootStackParamList} from '../../navigation/types';
import type {ApiError} from '../../types/api';
import type {DashboardData, DashboardGroup, DashboardUpcomingItem} from '../../types/dashboard';

type Nav = NativeStackNavigationProp<RootStackParamList>;
type Competence = {year: number; month: number};

export default function DashboardScreen() {
  const navigation = useNavigation<Nav>();
  const {user} = useAuth();
  const [period, setPeriod] = useState<Competence>(() => currentCompetence());
  const [data, setData] = useState<DashboardData | null>(null);
  const [merchants, setMerchants] = useState<DashboardGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [expandedCategory, setExpandedCategory] = useState<string | null>(null);
  const isFirstLoad = useRef(true);

  const load = useCallback(async (isRefresh: boolean) => {
    if (isRefresh) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }
    setError(null);
    try {
      const [dashboardResult, merchantsResult] = await Promise.all([
        getDashboard(period.year, period.month),
        getReportSummary({year: period.year, month: period.month, group_by: 'merchant'}).catch(() => null),
      ]);
      setData(dashboardResult);
      const topMerchants = merchantsResult
        ? merchantsResult.groups.filter(group => group.key !== 'none' && Number(group.expenses) > 0).slice(0, 10)
        : [];
      setMerchants(topMerchants);
    } catch (err) {
      setError((err as ApiError).message ?? 'Não foi possível carregar o painel.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [period.year, period.month]);

  // Recarrega ao ganhar foco (ex.: voltar do cadastro de uma movimentação) e
  // sempre que o período selecionado mudar — sem spinner de tela cheia depois
  // da primeira vez, só a atualização discreta do RefreshControl.
  useFocusEffect(
    useCallback(() => {
      const first = isFirstLoad.current;
      isFirstLoad.current = false;
      load(!first);
    }, [load]),
  );

  const changePeriod = useCallback((delta: number) => {
    setExpandedCategory(null);
    setPeriod(previous => shiftCompetence(previous.year, previous.month, delta));
  }, []);

  const firstName = user?.name?.split(' ')[0] ?? '';
  const categories = useMemo(
    () => (data?.by_category ?? []).filter(item => Number(item.expenses) > 0),
    [data],
  );
  const insights = useMemo(() => (data ? buildInsights(data) : []), [data]);

  const openFilteredTransactions = useCallback(
    (params: {title: string; categoryId?: number; subcategoryId?: number; onlyUncategorizedSubcategory?: boolean; merchantId?: number}) => {
      navigation.navigate('FilteredTransactions', {
        ...params,
        year: period.year,
        month: period.month,
      });
    },
    [navigation, period],
  );

  const openCategoryTransactions = useCallback(
    (category: DashboardGroup, subcategory?: DashboardGroup) => {
      const isUncategorizedBucket = subcategory?.key === 'none';
      openFilteredTransactions({
        title: subcategory
          ? `${category.label} › ${isUncategorizedBucket ? 'Sem subcategoria' : subcategory.label}`
          : category.label,
        categoryId: Number(category.key),
        subcategoryId: subcategory && !isUncategorizedBucket ? Number(subcategory.key) : undefined,
        onlyUncategorizedSubcategory: isUncategorizedBucket ? true : undefined,
      });
    },
    [openFilteredTransactions],
  );

  const handleCategoryPress = useCallback(
    (category: DashboardGroup) => {
      const subcategories = (category.subcategories ?? []).filter(sub => Number(sub.expenses) > 0);
      const hasBreakdown = subcategories.some(sub => sub.key !== 'none');
      if (!hasBreakdown) {
        openCategoryTransactions(category);
        return;
      }
      setExpandedCategory(previous => (previous === category.key ? null : category.key));
    },
    [openCategoryTransactions],
  );

  if (loading && !data) {
    return (
      <SafeAreaView style={styles.flex} edges={['top', 'bottom']}>
        <Loading label="Carregando seu painel..." />
      </SafeAreaView>
    );
  }

  if (error && !data) {
    return (
      <SafeAreaView style={styles.flex} edges={['top', 'bottom']}>
        <View style={styles.centered}>
          <EmptyState title="Não foi possível carregar o painel" description={error}>
            <Button label="Tentar novamente" onPress={() => load(false)} />
          </EmptyState>
        </View>
      </SafeAreaView>
    );
  }

  const upcoming = data?.upcoming ?? [];
  const donutData = categories.map((category, index) => ({
    key: category.key,
    value: Number(category.expenses),
    color: theme.categoricalColors[index % theme.categoricalColors.length],
  }));
  const merchantsTotal = merchants.reduce((sum, merchant) => sum + Number(merchant.expenses), 0);

  return (
    <SafeAreaView style={styles.flex} edges={['top', 'bottom']}>
      <ScrollView
        contentContainerStyle={styles.container}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => load(true)}
            colors={[theme.colors.green]}
            tintColor={theme.colors.green}
          />
        }>
        <Text style={styles.greeting}>Olá, {firstName}</Text>

        <View style={styles.periodPicker}>
          <TouchableOpacity
            accessibilityLabel="Mês anterior"
            style={styles.periodButton}
            onPress={() => changePeriod(-1)}>
            <Text style={styles.periodArrow}>‹</Text>
          </TouchableOpacity>
          <Text style={styles.periodLabel}>{formatMonthYear(period.year, period.month)}</Text>
          <TouchableOpacity accessibilityLabel="Próximo mês" style={styles.periodButton} onPress={() => changePeriod(1)}>
            <Text style={styles.periodArrow}>›</Text>
          </TouchableOpacity>
        </View>

        {error ? <Text style={styles.inlineError}>{error}</Text> : null}

        <View style={styles.statsRow}>
          <StatCard title="Receitas do mês" value={formatCurrency(data?.summary.income)} />
          <StatCard title="Despesas do mês" value={formatCurrency(data?.summary.expenses)} tone="expense" />
          <StatCard title="Resultado do mês" value={formatCurrency(data?.summary.balance)} tone="featured" />
        </View>

        {insights.length > 0 ? <InsightsCard insights={insights} /> : null}

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Para onde vai o seu dinheiro</Text>
          <Text style={styles.sectionSubtitle}>Toque numa categoria para ver as subcategorias e os lançamentos.</Text>
          <Card>
            {categories.length === 0 ? (
              <Text style={styles.emptyText}>Nenhum gasto registrado neste mês.</Text>
            ) : (
              <>
                <View style={styles.chartWrap}>
                  <DonutChart
                    data={donutData}
                    onSegmentPress={key => {
                      const category = categories.find(item => item.key === key);
                      if (category) {
                        handleCategoryPress(category);
                      }
                    }}
                  />
                </View>
                {categories.map((category, index) => (
                  <CategoryRow
                    key={category.key}
                    category={category}
                    color={theme.categoricalColors[index % theme.categoricalColors.length]}
                    isFirst={index === 0}
                    isExpanded={expandedCategory === category.key}
                    onPressCategory={() => handleCategoryPress(category)}
                    onPressSubcategory={subcategory => openCategoryTransactions(category, subcategory)}
                  />
                ))}
              </>
            )}
          </Card>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Top 10 estabelecimentos</Text>
          <Text style={styles.sectionSubtitle}>Onde você mais gastou em {formatMonthYear(period.year, period.month)}.</Text>
          {merchants.length === 0 ? (
            <Card>
              <EmptyState
                title="Nenhum estabelecimento neste mês"
                description="Lançamentos sem estabelecimento vinculado não aparecem aqui."
              />
            </Card>
          ) : (
            <Card style={styles.upcomingCard}>
              {merchants.map((merchant, index) => (
                <MerchantRow
                  key={merchant.key}
                  merchant={merchant}
                  rank={index + 1}
                  isFirst={index === 0}
                  share={merchantsTotal > 0 ? Number(merchant.expenses) / merchantsTotal : 0}
                  onPress={() => openFilteredTransactions({title: merchant.label, merchantId: Number(merchant.key)})}
                />
              ))}
            </Card>
          )}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Últimas movimentações</Text>
          {upcoming.length === 0 ? (
            <Card>
              <EmptyState title="Nenhum compromisso pendente." />
            </Card>
          ) : (
            <Card style={styles.upcomingCard}>
              {upcoming.map((item, index) => (
                <UpcomingRow key={item.id} item={item} isFirst={index === 0} />
              ))}
            </Card>
          )}
        </View>
      </ScrollView>
      <Fab onPress={() => navigation.navigate('NewTransaction')} />
    </SafeAreaView>
  );
}

function CategoryRow({
  category,
  color,
  isFirst,
  isExpanded,
  onPressCategory,
  onPressSubcategory,
}: {
  category: DashboardGroup;
  color: string;
  isFirst: boolean;
  isExpanded: boolean;
  onPressCategory: () => void;
  onPressSubcategory: (subcategory: DashboardGroup) => void;
}) {
  const subcategories = (category.subcategories ?? []).filter(sub => Number(sub.expenses) > 0);
  const hasBreakdown = subcategories.some(sub => sub.key !== 'none');
  const categoryTotal = Number(category.expenses);

  return (
    <View>
      <TouchableOpacity
        style={[styles.categoryRow, isFirst && styles.categoryRowFirst]}
        onPress={onPressCategory}
        activeOpacity={0.7}>
        <View style={[styles.categoryDot, {backgroundColor: color}]} />
        <Text style={styles.categoryLabel} numberOfLines={1}>
          {category.label}
        </Text>
        <Text style={styles.categoryValue}>{formatCurrency(category.expenses)}</Text>
        {hasBreakdown ? <Text style={styles.chevron}>{isExpanded ? '︿' : '﹀'}</Text> : null}
      </TouchableOpacity>

      {isExpanded && hasBreakdown ? (
        <View style={styles.subcategoryList}>
          {subcategories.map(subcategory => {
            const percent =
              categoryTotal > 0 ? Math.min(100, Math.round((Number(subcategory.expenses) / categoryTotal) * 100)) : 0;
            return (
              <TouchableOpacity
                key={subcategory.key}
                style={styles.subcategoryRow}
                onPress={() => onPressSubcategory(subcategory)}
                activeOpacity={0.7}>
                <View style={styles.subcategoryHeader}>
                  <Text style={styles.subcategoryLabel} numberOfLines={1}>
                    {subcategory.label}
                  </Text>
                  <Text style={styles.subcategoryValue}>{formatCurrency(subcategory.expenses)}</Text>
                </View>
                <View style={styles.progressTrack}>
                  <View style={[styles.progressFill, {width: `${percent}%`, backgroundColor: color}]} />
                </View>
              </TouchableOpacity>
            );
          })}
        </View>
      ) : null}
    </View>
  );
}

function MerchantRow({
  merchant,
  rank,
  isFirst,
  share,
  onPress,
}: {
  merchant: DashboardGroup;
  rank: number;
  isFirst: boolean;
  share: number;
  onPress: () => void;
}) {
  return (
    <TouchableOpacity
      style={[styles.merchantRow, isFirst && styles.upcomingRowFirst]}
      onPress={onPress}
      activeOpacity={0.7}>
      <View style={styles.merchantRank}>
        <Text style={styles.merchantRankText}>{rank}</Text>
      </View>
      <View style={styles.merchantInfo}>
        <Text style={styles.merchantLabel} numberOfLines={1}>
          {merchant.label}
        </Text>
        <View style={styles.progressTrack}>
          <View style={[styles.progressFill, {width: `${Math.round(share * 100)}%`, backgroundColor: theme.colors.amber}]} />
        </View>
      </View>
      <Text style={styles.merchantValue}>{formatCurrency(merchant.expenses)}</Text>
    </TouchableOpacity>
  );
}

function UpcomingRow({item, isFirst}: {item: DashboardUpcomingItem; isFirst: boolean}) {
  return (
    <View style={[styles.upcomingRow, isFirst && styles.upcomingRowFirst]}>
      <View style={styles.upcomingInfo}>
        <Text style={styles.upcomingDescription} numberOfLines={1}>
          {item.description}
        </Text>
        <Text style={styles.upcomingMeta}>
          {formatRelativeShort(item.due_date ?? item.transaction_date)}
        </Text>
        <View style={styles.badgeRow}>
          <CategoryBadge category={item.category?.name} subcategory={item.subcategory?.name} />
          <Badge label={item.status} tone={statusTone(item.status)} />
        </View>
      </View>
      <Text style={styles.upcomingAmount}>{formatCurrency(item.amount)}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: {flex: 1, backgroundColor: theme.colors.canvas},
  centered: {flex: 1, justifyContent: 'center'},
  container: {padding: theme.spacing.lg, paddingBottom: theme.spacing.xxl, gap: theme.spacing.lg},
  greeting: {
    fontSize: theme.font.size.xl,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.ink,
  },
  periodPicker: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: theme.spacing.md,
    backgroundColor: '#fff',
    borderRadius: theme.radius.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingVertical: 6,
    alignSelf: 'center',
  },
  periodButton: {paddingHorizontal: theme.spacing.md, paddingVertical: 4},
  periodArrow: {fontSize: theme.font.size.lg, fontWeight: '700', color: theme.colors.green},
  periodLabel: {fontSize: theme.font.size.sm, fontWeight: '700', color: theme.colors.ink, minWidth: 120, textAlign: 'center'},
  inlineError: {
    color: theme.colors.red,
    fontSize: theme.font.size.sm,
    backgroundColor: '#fdf0ed',
    borderRadius: theme.radius.sm,
    padding: theme.spacing.sm,
  },
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
  },
  section: {gap: theme.spacing.sm},
  sectionTitle: {
    fontSize: theme.font.size.md,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.ink,
  },
  sectionSubtitle: {fontSize: theme.font.size.xs, color: theme.colors.muted, marginTop: -6},
  emptyText: {fontSize: theme.font.size.sm, color: theme.colors.muted},
  chartWrap: {alignItems: 'center', paddingVertical: theme.spacing.md},
  categoryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: theme.spacing.sm,
    paddingVertical: theme.spacing.sm,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  categoryRowFirst: {borderTopWidth: 0, paddingTop: 0},
  categoryDot: {width: 10, height: 10, borderRadius: 5},
  categoryLabel: {flex: 1, fontSize: theme.font.size.md, color: theme.colors.ink},
  categoryValue: {
    fontSize: theme.font.size.md,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.ink,
  },
  chevron: {fontSize: theme.font.size.sm, color: theme.colors.muted, width: 16, textAlign: 'center'},
  subcategoryList: {paddingLeft: theme.spacing.lg + 10, paddingBottom: theme.spacing.sm, gap: theme.spacing.sm},
  subcategoryRow: {paddingTop: theme.spacing.xs},
  subcategoryHeader: {flexDirection: 'row', justifyContent: 'space-between', gap: theme.spacing.sm},
  subcategoryLabel: {flex: 1, fontSize: theme.font.size.sm, color: theme.colors.muted},
  subcategoryValue: {fontSize: theme.font.size.sm, fontWeight: '700', color: theme.colors.ink},
  progressTrack: {height: 5, borderRadius: 3, backgroundColor: theme.colors.mint, marginTop: 4, overflow: 'hidden'},
  progressFill: {height: '100%', borderRadius: 3},
  merchantRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.md,
    paddingVertical: theme.spacing.md,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  merchantRank: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: theme.colors.mint,
    alignItems: 'center',
    justifyContent: 'center',
  },
  merchantRankText: {fontSize: theme.font.size.xs, fontWeight: '800', color: theme.colors.green},
  merchantInfo: {flex: 1, gap: 4},
  merchantLabel: {fontSize: theme.font.size.md, fontWeight: '700', color: theme.colors.ink},
  merchantValue: {fontSize: theme.font.size.md, fontWeight: theme.font.weight.bold, color: theme.colors.ink},
  upcomingCard: {padding: 0, paddingHorizontal: theme.spacing.lg},
  upcomingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.md,
    paddingVertical: theme.spacing.md,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  upcomingRowFirst: {borderTopWidth: 0},
  upcomingInfo: {flex: 1, gap: 4},
  upcomingDescription: {
    fontSize: theme.font.size.md,
    fontWeight: '700',
    color: theme.colors.ink,
  },
  upcomingMeta: {fontSize: theme.font.size.xs, color: theme.colors.muted},
  badgeRow: {flexDirection: 'row', gap: 6, marginTop: 4, flexWrap: 'wrap'},
  upcomingAmount: {
    fontSize: theme.font.size.md,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.ink,
  },
});
