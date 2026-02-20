<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
  Avatar,
  Button,
  Card,
  CardContent,
  Input,
  Label,
  TimezoneSelect,
} from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const name = ref('');
const timezone = ref('UTC');
const avatarFile = ref<File | null>(null);
const avatarPreview = ref<string | null>(null);
const profileSaving = ref(false);

const currentPassword = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const passwordSuccess = ref(false);
const passwordSaving = ref(false);

type TabId = 'profile' | 'password';
const activeTab = ref<TabId>('profile');

const avatarDisplayUrl = computed(() => {
  if (avatarPreview.value) return avatarPreview.value;
  return auth.user?.avatar_url ?? null;
});

const avatarFallback = computed(() => {
  const n = auth.user?.name?.trim() || '';
  if (n.length >= 2) return n.slice(0, 2).toUpperCase();
  return n || '??';
});

onMounted(() => {
  auth.clearError();
  if (!auth.isAuthenticated) {
    router.replace('/login');
    return;
  }
  name.value = auth.user?.name ?? '';
  timezone.value = auth.user?.timezone ?? 'UTC';
});

watch(
  () => auth.user,
  (u) => {
    if (u) {
      name.value = u.name ?? '';
      timezone.value = u.timezone ?? 'UTC';
    }
  },
  { deep: true }
);

function onAvatarChange(e: Event) {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file?.type.startsWith('image/')) {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
    avatarFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);
  }
  target.value = '';
}

async function saveProfile(e: Event) {
  e.preventDefault();
  profileSaving.value = true;
  auth.clearError();
  try {
    await auth.updateProfile({
      name: name.value.trim(),
      timezone: timezone.value || 'UTC',
      avatar: avatarFile.value ?? undefined,
    });
    avatarFile.value = null;
    if (avatarPreview.value) {
      URL.revokeObjectURL(avatarPreview.value);
      avatarPreview.value = null;
    }
  } catch {
    // error in auth.error
  } finally {
    profileSaving.value = false;
  }
}

async function savePassword(e: Event) {
  e.preventDefault();
  if (password.value !== passwordConfirmation.value) {
    auth.setError('Пароли не совпадают');
    return;
  }
  passwordSaving.value = true;
  auth.clearError();
  try {
    await auth.updatePassword({
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    passwordSuccess.value = true;
    currentPassword.value = '';
    password.value = '';
    passwordConfirmation.value = '';
  } catch {
    // error in auth.error
  } finally {
    passwordSaving.value = false;
  }
}
</script>

<template>
  <div class="container max-w-lg py-8">
    <h1 class="mb-6 text-2xl font-bold">Профиль</h1>

    <div class="mb-4 flex border-b">
      <button
        type="button"
        class="rounded-t-lg px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'profile'
          ? 'border-b-2 border-primary bg-muted/50 text-foreground -mb-px'
          : 'text-muted-foreground hover:text-foreground'"
        @click="activeTab = 'profile'"
      >
        Данные профиля
      </button>
      <button
        type="button"
        class="rounded-t-lg px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'password'
          ? 'border-b-2 border-primary bg-muted/50 text-foreground -mb-px'
          : 'text-muted-foreground hover:text-foreground'"
        @click="activeTab = 'password'"
      >
        Смена пароля
      </button>
    </div>

    <form v-show="activeTab === 'profile'" @submit="saveProfile">
      <Card>
        <CardContent class="pt-6 flex flex-col gap-4">
          <p v-if="auth.error" class="text-sm text-destructive">{{ auth.error }}</p>

          <div class="flex items-center gap-4">
            <div class="relative">
              <Avatar
                :src="avatarDisplayUrl ?? undefined"
                :fallback="avatarFallback"
                class="h-20 w-20"
              />
              <label
                class="absolute bottom-0 right-0 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full border bg-background shadow"
                title="Загрузить аватар"
              >
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                  class="sr-only"
                  @change="onAvatarChange"
                />
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
                </svg>
              </label>
            </div>
            <p class="text-sm text-muted-foreground">Нажмите на иконку, чтобы загрузить аватар (JPEG, PNG, GIF, WebP, до 5 МБ)</p>
          </div>

          <div class="space-y-2">
            <Label for="profile-name">Имя *</Label>
            <Input id="profile-name" v-model="name" type="text" required maxlength="255" />
          </div>

          <div class="space-y-2">
            <Label for="profile-timezone">Часовой пояс</Label>
            <TimezoneSelect id="profile-timezone" v-model="timezone" class="w-full" />
            <p class="text-xs text-muted-foreground">Время на сайте будет отображаться в выбранном поясе.</p>
          </div>

          <Button type="submit" :disabled="profileSaving">
            {{ profileSaving ? 'Сохранение...' : 'Сохранить профиль' }}
          </Button>
        </CardContent>
      </Card>
    </form>

    <form v-show="activeTab === 'password'" @submit="savePassword">
      <Card>
        <CardContent class="pt-6 flex flex-col gap-4">
          <p v-if="auth.error" class="text-sm text-destructive">{{ auth.error }}</p>

          <template v-if="!passwordSuccess">
            <div class="space-y-2">
              <Label for="current_password">Текущий пароль</Label>
              <Input id="current_password" v-model="currentPassword" type="password" required />
            </div>
            <div class="space-y-2">
              <Label for="new_password">Новый пароль</Label>
              <Input id="new_password" v-model="password" type="password" required />
            </div>
            <div class="space-y-2">
              <Label for="password_confirmation">Подтверждение нового пароля</Label>
              <Input id="password_confirmation" v-model="passwordConfirmation" type="password" required />
            </div>
            <Button type="submit" :disabled="passwordSaving">
              {{ passwordSaving ? 'Сохранение...' : 'Сменить пароль' }}
            </Button>
          </template>
          <template v-else>
            <p class="text-sm text-muted-foreground">Пароль успешно изменён.</p>
            <Button type="button" variant="outline" @click="passwordSuccess = false">Сменить пароль снова</Button>
          </template>
        </CardContent>
      </Card>
    </form>
  </div>
</template>
