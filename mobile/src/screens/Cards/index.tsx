import React, {useCallback, useEffect, useState} from 'react';
import {FlatList, RefreshControl, StyleSheet, Text, View} from 'react-native';
import {listAllCards} from '../../api/cards';
import {Badge} from '../../components/CategoryBadge';
import {Card} from '../../components/Card';
import {Button} from '../../components/Button';
import {EmptyState} from '../../components/EmptyState';
import {Loading} from '../../components/Loading';
import {theme} from '../../theme';
import {formatCurrency} from '../../utils/currency';
import type {Card as CardModel} from '../../types/card';

function maskedDigits(lastDigits: string | null): string | null {
  return lastDigits ? `•••• ${lastDigits}` : null;
}

function CardRow({item}: {item: CardModel}) {
  const digits = maskedDigits(item.last_digits);
  return (
    <Card style={styles.cardRow}>
      <View style={styles.cardHeader}>
        <View style={styles.cardTitleWrap}>
          <Text style={styles.cardName}>{item.name}</Text>
          {item.institution ? <Text style={styles.cardInstitution}>{item.institution}</Text> : null}
        </View>
        {item.status === 'INATIVO' ? <Badge label="Inativo" tone="danger" /> : null}
      </View>
      {digits ? <Text style={styles.cardDigits}>{digits}</Text> : null}
      <View style={styles.statsRow}>
        <View style={styles.statItem}>
          <Text style={styles.statLabel}>Disponível</Text>
          <Text style={styles.statValue}>{formatCurrency(item.available_limit)}</Text>
        </View>
        <View style={styles.statItem}>
          <Text style={styles.statLabel}>Usado</Text>
          <Text style={styles.statValue}>{formatCurrency(item.used_limit)}</Text>
        </View>
        <View style={styles.statItem}>
          <Text style={styles.statLabel}>Limite total</Text>
          <Text style={styles.statValue}>{formatCurrency(item.credit_limit)}</Text>
        </View>
      </View>
    </Card>
  );
}

export default function CardsScreen() {
  const [cards, setCards] = useState<CardModel[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (silent = false) => {
    if (!silent) {
      setIsLoading(true);
    }
    setError(null);
    try {
      const items = await listAllCards();
      setCards(items);
    } catch {
      setError('Não foi possível carregar seus cartões. Verifique sua conexão e tente novamente.');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    load(true);
  }, [load]);

  return (
    <View style={styles.screen}>
      <Text style={styles.title}>Cartões</Text>
      {isLoading ? (
        <Loading label="Carregando cartões..." />
      ) : error ? (
        <EmptyState title="Não foi possível carregar" description={error}>
          <Button label="Tentar novamente" onPress={() => load()} />
        </EmptyState>
      ) : (
        <FlatList
          data={cards}
          keyExtractor={item => String(item.id)}
          renderItem={({item}) => <CardRow item={item} />}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl
              refreshing={isRefreshing}
              onRefresh={handleRefresh}
              colors={[theme.colors.green]}
              tintColor={theme.colors.green}
            />
          }
          ListEmptyComponent={
            <EmptyState
              title="Nenhum cartão cadastrado"
              description="Cadastre seus cartões pelo aplicativo web para acompanhar limites e faturas por aqui."
            />
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas, paddingHorizontal: theme.spacing.lg},
  title: {
    fontSize: theme.font.size.xxl,
    fontWeight: theme.font.weight.bold,
    color: theme.colors.ink,
    marginTop: theme.spacing.lg,
    marginBottom: theme.spacing.md,
  },
  listContent: {paddingBottom: theme.spacing.xxl, flexGrow: 1},
  cardRow: {marginBottom: theme.spacing.md},
  cardHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start'},
  cardTitleWrap: {flex: 1, paddingRight: theme.spacing.sm},
  cardName: {fontSize: theme.font.size.lg, fontWeight: theme.font.weight.bold, color: theme.colors.ink},
  cardInstitution: {fontSize: theme.font.size.sm, color: theme.colors.muted, marginTop: 2},
  cardDigits: {fontSize: theme.font.size.sm, color: theme.colors.muted, marginTop: 6, letterSpacing: 1},
  statsRow: {flexDirection: 'row', marginTop: theme.spacing.md, gap: theme.spacing.sm},
  statItem: {flex: 1},
  statLabel: {fontSize: theme.font.size.xs, color: theme.colors.muted, fontWeight: '600'},
  statValue: {fontSize: theme.font.size.md, fontWeight: theme.font.weight.bold, color: theme.colors.ink, marginTop: 2},
});
