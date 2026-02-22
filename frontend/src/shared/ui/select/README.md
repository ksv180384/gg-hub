# Select (shadcn-style)

Все селекты в стиле shadcn UI на базе Radix Vue.

## Вариант 1: Переиспользуемый компонент `Select`

Для простого выбора из списка опций:

```vue
<script setup lang="ts">
import { Select, type SelectOption } from '@/shared/ui';

const value = ref('');
const options = computed<SelectOption[]>(() => [
  { value: '1', label: 'Пункт 1' },
  { value: '2', label: 'Пункт 2' },
]);
</script>

<template>
  <Select
    v-model="value"
    :options="options"
    placeholder="Выберите..."
    trigger-class="w-full"
  />
</template>
```

Опции: `{ value: string, label: string, disabled?: boolean }[]`.

## Вариант 2: Примитивы (кастомная вёрстка)

Для сложных кейсов (поиск внутри списка, свои пункты):

```vue
<SelectRoot v-model="selectedId">
  <SelectTrigger class="w-full">
    <SelectValue placeholder="Выберите" />
  </SelectTrigger>
  <SelectContent>
    <SelectItem v-for="item in items" :key="item.id" :value="String(item.id)">
      {{ item.name }}
    </SelectItem>
  </SelectContent>
</SelectRoot>
```

Компоненты: `SelectRoot`, `SelectTrigger`, `SelectValue`, `SelectContent`, `SelectItem`.
