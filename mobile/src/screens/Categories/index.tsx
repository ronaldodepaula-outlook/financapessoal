import React, {useCallback, useEffect, useState} from 'react';
import {ScrollView, StyleSheet, Text, View} from 'react-native';
import {listAllCategories} from '../../api/categories';
import {Badge} from '../../components/CategoryBadge';
import {Button} from '../../components/Button';
import {EmptyState} from '../../components/EmptyState';
import {Loading} from '../../components/Loading';
import {theme} from '../../theme';
import type {ApiError} from '../../types/api';
import type {Category, CategoryType} from '../../types/category';

function sortByName(list: Category[]): Category[] {
  return [...list].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
}

function Section({title, categories}: {title: string; categories: Category[]}) {
  const roots = sortByName(categories.filter(category => category.parent_id === null));

  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {roots.length === 0 ? (
        <Text style={styles.empty}>Nenhuma categoria cadastrada.</Text>
      ) : (
        roots.map(root => {
          const children = sortByName(categories.filter(category => category.parent_id === root.id));
          return (
            <View key={root.id} style={styles.node}>
              <View style={styles.row}>
                <Text style={styles.rootName}>{root.name}</Text>
                {root.status === 'INATIVO' ? <Badge label="Inativo" tone="danger" /> : null}
              </View>
              {children.map(child => (
                <View key={child.id} style={[styles.row, styles.childRow]}>
                  <Text style={styles.childName}>{child.name}</Text>
                  {child.status === 'INATIVO' ? <Badge label="Inativo" tone="danger" /> : null}
                </View>
              ))}
            </View>
          );
        })
      )}
    </View>
  );
}

export default function CategoriesScreen() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [expenses, incomes] = await Promise.all([
        listAllCategories('DESPESA' as CategoryType),
        listAllCategories('RECEITA' as CategoryType),
      ]);
      setCategories([...expenses, ...incomes]);
    } catch (err) {
      setError((err as ApiError).message ?? 'Não foi possível carregar as categorias.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  if (isLoading) {
    return <Loading label="Carregando categorias..." />;
  }

  if (error) {
    return (
      <View style={styles.centered}>
        <EmptyState title="Não foi possível carregar" description={error}>
          <Button label="Tentar novamente" onPress={load} />
        </EmptyState>
      </View>
    );
  }

  const expenseCategories = categories.filter(category => category.type === 'DESPESA');
  const incomeCategories = categories.filter(category => category.type === 'RECEITA');

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.container}>
      <Text style={styles.note}>
        Consulta das categorias já cadastradas. Para criar ou editar, use o aplicativo web.
      </Text>
      <Section title="Despesas" categories={expenseCategories} />
      <Section title="Receitas" categories={incomeCategories} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: {flex: 1, backgroundColor: theme.colors.canvas},
  centered: {flex: 1, justifyContent: 'center', backgroundColor: theme.colors.canvas},
  container: {padding: theme.spacing.lg, gap: theme.spacing.lg, paddingBottom: theme.spacing.xxl},
  note: {fontSize: theme.font.size.xs, color: theme.colors.muted},
  section: {gap: theme.spacing.sm},
  sectionTitle: {fontSize: theme.font.size.lg, fontWeight: theme.font.weight.bold, color: theme.colors.ink},
  empty: {fontSize: theme.font.size.sm, color: theme.colors.muted},
  node: {backgroundColor: '#fff', borderRadius: theme.radius.md, borderWidth: 1, borderColor: theme.colors.border, padding: theme.spacing.md},
  row: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 6},
  childRow: {paddingLeft: theme.spacing.lg, borderTopWidth: 1, borderTopColor: theme.colors.border},
  rootName: {fontSize: theme.font.size.md, fontWeight: '700', color: theme.colors.ink},
  childName: {fontSize: theme.font.size.sm, color: theme.colors.ink},
});
