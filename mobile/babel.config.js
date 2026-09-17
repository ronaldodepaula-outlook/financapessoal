module.exports = {
  // babel-preset-expo estende o preset padrão do React Native com os
  // ajustes que módulos expo-* (expo-secure-store, expo aqui usado só para
  // o CLI/Expo Go) esperam — inclusive a inlining de process.env.EXPO_OS e o
  // plugin de "export * as ns" que o zod v4 precisa (já embutido aqui, não
  // precisa mais declarar @babel/plugin-transform-export-namespace-from à parte).
  presets: ['babel-preset-expo'],
};
