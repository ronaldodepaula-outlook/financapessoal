/* eslint-env jest */
import 'react-native-gesture-handler/jestSetup';

// O mock oficial do pacote é distribuído em ESM puro e o preset do RN não
// transforma arquivos dentro de node_modules por padrão; um mock local
// minimalista evita configurar transformIgnorePatterns só para os testes.
jest.mock('@react-native-async-storage/async-storage', () => {
  let store = {};
  return {
    __esModule: true,
    default: {
      getItem: jest.fn(key => Promise.resolve(Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null)),
      setItem: jest.fn((key, value) => {
        store[key] = value;
        return Promise.resolve();
      }),
      removeItem: jest.fn(key => {
        delete store[key];
        return Promise.resolve();
      }),
      clear: jest.fn(() => {
        store = {};
        return Promise.resolve();
      }),
      getAllKeys: jest.fn(() => Promise.resolve(Object.keys(store))),
    },
  };
});

jest.mock('expo-secure-store', () => {
  let store = {};
  return {
    setItemAsync: jest.fn((key, value) => {
      store[key] = value;
      return Promise.resolve();
    }),
    getItemAsync: jest.fn(key => Promise.resolve(Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null)),
    deleteItemAsync: jest.fn(key => {
      delete store[key];
      return Promise.resolve();
    }),
  };
});

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => {}),
  fetch: jest.fn(() => Promise.resolve({isConnected: true, isInternetReachable: true})),
}));
