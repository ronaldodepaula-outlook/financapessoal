import React from 'react';
import {StyleSheet, Text} from 'react-native';
import {NavigationContainer} from '@react-navigation/native';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {useNotificationStore} from '../store/notificationStore';
import {selectPending} from '../store/notificationStore';
import {theme} from '../theme';
import {AuthNavigator} from './AuthNavigator';
import type {MainTabParamList, RootStackParamList} from './types';
import {useAuth} from '../hooks/useAuth';

import DashboardScreen from '../screens/Dashboard';
import TransactionsScreen from '../screens/Transactions';
import PendingScreen from '../screens/Notifications/PendingScreen';
import CardsScreen from '../screens/Cards';
import SettingsScreen from '../screens/Settings';
import TransactionDetailScreen from '../screens/TransactionDetail';
import NewTransactionScreen from '../screens/Transactions/NewTransaction';
import FilteredTransactionsScreen from '../screens/Transactions/FilteredTransactions';
import LapidacaoScreen from '../screens/Notifications/LapidacaoScreen';
import CategoriesScreen from '../screens/Categories';
import MonitoredAppsScreen from '../screens/Settings/MonitoredApps';
import NotificationAccessScreen from '../screens/Settings/NotificationAccess';
import DiagnosticsScreen from '../screens/Settings/Diagnostics';

const Tab = createBottomTabNavigator<MainTabParamList>();
const RootStack = createNativeStackNavigator<RootStackParamList>();

const TAB_ICONS: Record<keyof MainTabParamList, string> = {
  Dashboard: '🏠',
  Transactions: '💳',
  Pending: '🔔',
  Cards: '🗂️',
  Settings: '⚙️',
};

const TAB_LABELS: Record<keyof MainTabParamList, string> = {
  Dashboard: 'Início',
  Transactions: 'Movimentações',
  Pending: 'Pendências',
  Cards: 'Cartões',
  Settings: 'Mais',
};

function TabIcon({name, color}: {name: keyof MainTabParamList; color: string}) {
  return <Text style={[styles.tabIcon, {color}]}>{TAB_ICONS[name]}</Text>;
}

function MainTabs() {
  const pendingCount = useNotificationStore(state => selectPending(state.captured).length);
  return (
    <Tab.Navigator
      screenOptions={({route}) => ({
        headerShown: false,
        tabBarActiveTintColor: theme.colors.green,
        tabBarInactiveTintColor: theme.colors.muted,
        tabBarLabel: TAB_LABELS[route.name as keyof MainTabParamList],
        tabBarIcon: ({color}) => <TabIcon name={route.name as keyof MainTabParamList} color={color} />,
      })}>
      <Tab.Screen name="Dashboard" component={DashboardScreen} />
      <Tab.Screen name="Transactions" component={TransactionsScreen} />
      <Tab.Screen
        name="Pending"
        component={PendingScreen}
        options={{tabBarBadge: pendingCount > 0 ? pendingCount : undefined}}
      />
      <Tab.Screen name="Cards" component={CardsScreen} />
      <Tab.Screen name="Settings" component={SettingsScreen} />
    </Tab.Navigator>
  );
}

export function AppNavigator() {
  const {isAuthenticated} = useAuth();

  return (
    <NavigationContainer>
      <RootStack.Navigator screenOptions={{headerShown: false}}>
        {isAuthenticated ? (
          <>
            <RootStack.Screen name="Main" component={MainTabs} />
            <RootStack.Screen
              name="TransactionDetail"
              component={TransactionDetailScreen}
              options={{headerShown: true, title: 'Movimentação'}}
            />
            <RootStack.Screen
              name="NewTransaction"
              component={NewTransactionScreen}
              options={{headerShown: true, title: 'Nova movimentação', presentation: 'modal'}}
            />
            <RootStack.Screen
              name="FilteredTransactions"
              component={FilteredTransactionsScreen}
              options={({route}) => ({headerShown: true, title: route.params.title})}
            />
            <RootStack.Screen
              name="Lapidacao"
              component={LapidacaoScreen}
              options={{headerShown: true, title: 'Lapidar movimentação'}}
            />
            <RootStack.Screen
              name="Categories"
              component={CategoriesScreen}
              options={{headerShown: true, title: 'Categorias'}}
            />
            <RootStack.Screen
              name="MonitoredApps"
              component={MonitoredAppsScreen}
              options={{headerShown: true, title: 'Aplicativos monitorados'}}
            />
            <RootStack.Screen
              name="NotificationAccess"
              component={NotificationAccessScreen}
              options={{headerShown: true, title: 'Captura de notificações'}}
            />
            <RootStack.Screen
              name="Diagnostics"
              component={DiagnosticsScreen}
              options={{headerShown: true, title: 'Diagnóstico'}}
            />
          </>
        ) : (
          <RootStack.Screen name="Auth" component={AuthNavigator} />
        )}
      </RootStack.Navigator>
    </NavigationContainer>
  );
}

const styles = StyleSheet.create({
  tabIcon: {fontSize: 18},
});
