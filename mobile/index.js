/**
 * @format
 */

import 'react-native-gesture-handler';
import { AppRegistry } from 'react-native';
import App from './App';
import { name as appName } from './app.json';

AppRegistry.registerComponent(appName, () => App);
// O Expo Go sempre carrega o componente raiz registrado sob a chave "main",
// independente do nome do app nativo — registramos os dois para o mesmo
// bundle funcionar tanto via `react-native run-android` (usa `appName`,
// "MobileApp") quanto via Expo Go/`expo start` (usa "main").
if (appName !== 'main') {
  AppRegistry.registerComponent('main', () => App);
}
