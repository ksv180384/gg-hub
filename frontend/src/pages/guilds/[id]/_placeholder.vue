<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/ui';
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const title = computed(() => (route.meta.title as string) ?? 'Раздел в разработке');
</script>

<template>
  <div class="container py-6">
    <Card>
      <CardHeader>
        <CardTitle>{{ title }}</CardTitle>
      </CardHeader>
      <CardContent>
        <p class="text-sm text-muted-foreground">Раздел в разработке.</p>
      </CardContent>
    </Card>
  </div>
</template>
