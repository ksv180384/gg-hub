<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const guild = ref<Guild | null>(null);
const loading = ref(true);

onMounted(async () => {
  if (!guildId.value) return;
  try {
    guild.value = await guildsApi.getGuild(guildId.value);
  } catch {
    guild.value = null;
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="container py-6">
    <Card>
      <CardHeader>
        <CardTitle>{{ guild?.name ?? 'Журнал гильдии' }}</CardTitle>
      </CardHeader>
      <CardContent>
        <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
        <template v-else-if="guild">
          <p class="mb-4 text-sm text-muted-foreground">
            Журнал гильдии. Раздел в разработке.
          </p>
          <Button variant="outline" size="sm" @click="router.push({ name: 'guild-settings', params: { id: String(guild.id) } })">
            Настройки гильдии
          </Button>
        </template>
        <p v-else class="text-sm text-muted-foreground">Гильдия не найдена.</p>
      </CardContent>
    </Card>
  </div>
</template>
