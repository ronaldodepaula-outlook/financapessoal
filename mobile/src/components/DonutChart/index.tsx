import React from 'react';
import {View} from 'react-native';
import Svg, {Circle, G} from 'react-native-svg';

export interface DonutSegment {
  key: string;
  value: number;
  color: string;
}

export interface DonutChartProps {
  data: DonutSegment[];
  size?: number;
  strokeWidth?: number;
  /** Chamado com a `key` do segmento tocado — o próprio gráfico não sabe o que fazer com o toque. */
  onSegmentPress?: (key: string) => void;
}

/**
 * Gráfico de rosca simples via react-native-svg (técnica de stroke-dasharray
 * por círculo concêntrico) — evita depender de uma lib de gráficos inteira só
 * para um indicador. Cada fatia é um <Circle> independente e pode ser tocada.
 */
export function DonutChart({data, size = 168, strokeWidth = 24, onSegmentPress}: DonutChartProps) {
  const segments = data.filter(segment => segment.value > 0);
  const total = segments.reduce((sum, segment) => sum + segment.value, 0);

  if (total <= 0) {
    return null;
  }

  const radius = (size - strokeWidth) / 2;
  const circumference = 2 * Math.PI * radius;
  let offsetSoFar = 0;

  return (
    <View style={{width: size, height: size}}>
      <Svg width={size} height={size}>
        <G rotation="-90" originX={size / 2} originY={size / 2}>
          {segments.map(segment => {
            const fraction = segment.value / total;
            const segmentLength = circumference * fraction;
            const dashOffset = -offsetSoFar;
            offsetSoFar += segmentLength;
            return (
              <Circle
                key={segment.key}
                cx={size / 2}
                cy={size / 2}
                r={radius}
                stroke={segment.color}
                strokeWidth={strokeWidth}
                strokeDasharray={`${segmentLength} ${circumference - segmentLength}`}
                strokeDashoffset={dashOffset}
                strokeLinecap="butt"
                fill="transparent"
                onPress={onSegmentPress ? () => onSegmentPress(segment.key) : undefined}
              />
            );
          })}
        </G>
      </Svg>
    </View>
  );
}
