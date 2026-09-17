import {categoricalColors, colors} from '../constants/colors';

export const theme = {
  colors,
  categoricalColors,
  spacing: {xs: 4, sm: 8, md: 12, lg: 16, xl: 24, xxl: 32},
  radius: {sm: 8, md: 12, lg: 16, pill: 999},
  font: {
    size: {xs: 11, sm: 12, md: 14, lg: 16, xl: 20, xxl: 26},
    weight: {regular: '400', medium: '600', bold: '800'} as const,
  },
};

export type Theme = typeof theme;
