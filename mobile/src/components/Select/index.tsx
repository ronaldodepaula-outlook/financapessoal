import React, {useState} from 'react';
import {FlatList, Modal, Pressable, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {theme} from '../../theme';

export interface SelectOption<T extends string | number> {
  value: T;
  label: string;
}

export interface SelectProps<T extends string | number> {
  label: string;
  value: T | undefined;
  options: Array<SelectOption<T>>;
  onChange: (value: T) => void;
  placeholder?: string;
  error?: string;
}

/**
 * Picker simples em modal — evita depender de @react-native-picker/picker
 * (mais um módulo nativo para linkar) para uma necessidade que uma lista
 * modal comum já resolve bem.
 */
export function Select<T extends string | number>({
  label,
  value,
  options,
  onChange,
  placeholder = 'Selecionar',
  error,
}: SelectProps<T>) {
  const [open, setOpen] = useState(false);
  const selected = options.find(option => option.value === value);

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label}</Text>
      <TouchableOpacity
        accessibilityRole="button"
        style={[styles.trigger, error ? styles.triggerError : null]}
        onPress={() => setOpen(true)}>
        <Text style={selected ? styles.value : styles.placeholder}>
          {selected ? selected.label : placeholder}
        </Text>
      </TouchableOpacity>
      {error ? <Text style={styles.error}>{error}</Text> : null}

      <Modal visible={open} animationType="slide" transparent onRequestClose={() => setOpen(false)}>
        <Pressable style={styles.backdrop} onPress={() => setOpen(false)}>
          <View style={styles.sheet} onStartShouldSetResponder={() => true}>
            <Text style={styles.sheetTitle}>{label}</Text>
            <FlatList
              data={options}
              keyExtractor={item => String(item.value)}
              style={styles.list}
              renderItem={({item}) => (
                <TouchableOpacity
                  style={styles.option}
                  onPress={() => {
                    onChange(item.value);
                    setOpen(false);
                  }}>
                  <Text style={item.value === value ? styles.optionLabelActive : styles.optionLabel}>
                    {item.label}
                  </Text>
                </TouchableOpacity>
              )}
              ListEmptyComponent={<Text style={styles.empty}>Nenhuma opção disponível.</Text>}
            />
          </View>
        </Pressable>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {marginBottom: theme.spacing.md},
  label: {fontSize: theme.font.size.sm, fontWeight: '700', color: '#506559', marginBottom: 6},
  trigger: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: '#dfe6dc',
    borderRadius: theme.radius.sm,
    paddingHorizontal: 12,
    justifyContent: 'center',
    backgroundColor: '#fff',
  },
  triggerError: {borderColor: theme.colors.red},
  value: {fontSize: theme.font.size.md, color: theme.colors.ink},
  placeholder: {fontSize: theme.font.size.md, color: '#acb6ad'},
  error: {color: theme.colors.red, fontSize: theme.font.size.xs, marginTop: 4},
  backdrop: {flex: 1, backgroundColor: '#162b235c', justifyContent: 'flex-end'},
  sheet: {backgroundColor: '#fff', borderTopLeftRadius: 18, borderTopRightRadius: 18, maxHeight: '70%', padding: theme.spacing.lg},
  sheetTitle: {fontSize: theme.font.size.lg, fontWeight: theme.font.weight.bold, marginBottom: theme.spacing.md},
  list: {marginBottom: theme.spacing.md},
  option: {paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: '#f0f4ee'},
  optionLabel: {fontSize: theme.font.size.md, color: theme.colors.ink},
  optionLabelActive: {fontSize: theme.font.size.md, color: theme.colors.green, fontWeight: '700'},
  empty: {textAlign: 'center', color: theme.colors.muted, paddingVertical: 20},
});
