import type {NavigatorScreenParams} from '@react-navigation/native';

export type AuthStackParamList = {
  Login: undefined;
};

export type MainTabParamList = {
  Dashboard: undefined;
  Transactions: undefined;
  Pending: undefined;
  Cards: undefined;
  Settings: undefined;
};

export type RootStackParamList = {
  Auth: NavigatorScreenParams<AuthStackParamList>;
  Main: NavigatorScreenParams<MainTabParamList>;
  TransactionDetail: {id: number};
  NewTransaction: undefined;
  /** Lançamentos filtrados por categoria/subcategoria OU por estabelecimento — sempre um ou outro. */
  FilteredTransactions: {
    title: string;
    year: number;
    month: number;
    categoryId?: number;
    subcategoryId?: number;
    /** true = mostrar só lançamentos da categoria SEM subcategoria (bucket "Sem subcategoria" do Dashboard). */
    onlyUncategorizedSubcategory?: boolean;
    merchantId?: number;
  };
  Lapidacao: {capturedId: string};
  Categories: undefined;
  MonitoredApps: undefined;
  NotificationAccess: undefined;
  Diagnostics: undefined;
};

declare global {
   
  namespace ReactNavigation {
    interface RootParamList extends RootStackParamList {}
  }
}
