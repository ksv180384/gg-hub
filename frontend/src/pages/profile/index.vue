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
import { useThemeStore } from '@/stores/theme';
import type { ThemePreference } from '@/shared/lib/themePreference';

const router = useRouter();
const auth = useAuthStore();
const theme = useThemeStore();

const name = ref('');
const timezone = ref('UTC');
const avatarFile = ref<File | null>(null);
const avatarPreview = ref<string | null>(null);
const profileSaving = ref(false);
const themePreference = ref<ThemePreference>('system');
const themeSaving = ref(false);
const themeOptions: Array<{ value: ThemePreference; label: string }> = [
  { value: 'light', label: 'Светлая' },
  { value: 'dark', label: 'Тёмная' },
  { value: 'system', label: 'Системная' },
];

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
  themePreference.value = auth.user?.theme_preference ?? 'system';
});

watch(
  () => auth.user,
  (u) => {
    if (u) {
      name.value = u.name ?? '';
      timezone.value = u.timezone ?? 'UTC';
      themePreference.value = u.theme_preference ?? 'system';
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

async function selectThemePreference(value: ThemePreference) {
  if (themeSaving.value || profileSaving.value || value === themePreference.value) return;

  const previous = themePreference.value;
  themePreference.value = value;
  theme.setAccountPreference(value);
  themeSaving.value = true;
  auth.clearError();

  try {
    await auth.updateProfile({ theme_preference: value });
  } catch {
    themePreference.value = previous;
    theme.setAccountPreference(previous);
  } finally {
    themeSaving.value = false;
  }
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
            <Label>Email</Label>
            <p class="rounded-md border border-input bg-muted/30 px-3 py-2 text-sm break-all">
              {{ auth.user?.email?.trim() || '—' }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="profile-name">Имя</Label>
            <Input id="profile-name" v-model="name" type="text" maxlength="255" />
            <p class="text-xs text-muted-foreground">Можно оставить пустым.</p>
          </div>

          <div class="space-y-2">
            <Label for="profile-timezone">Часовой пояс</Label>
            <TimezoneSelect id="profile-timezone" v-model="timezone" class="w-full" />
            <p class="text-xs text-muted-foreground">Время на сайте будет отображаться в выбранном поясе.</p>
          </div>

          <fieldset class="space-y-2">
            <legend class="text-sm font-medium">Тема оформления</legend>
            <div class="grid grid-cols-3 gap-1 rounded-md border bg-muted/30 p-1">
              <button
                v-for="option in themeOptions"
                :key="option.value"
                type="button"
                class="flex min-h-16 flex-col items-center justify-center gap-1 rounded-md px-2 py-2 text-xs font-medium transition-colors"
                :class="themePreference === option.value
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted-foreground hover:bg-background/60 hover:text-foreground'"
                :aria-pressed="themePreference === option.value"
                :disabled="themeSaving || profileSaving"
                @click="selectThemePreference(option.value)"
              >
                <svg
                  v-if="option.value === 'light'"
                  xmlns="http://www.w3.org/2000/svg"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <circle cx="12" cy="12" r="4" />
                  <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                </svg>
                <svg
                  v-else-if="option.value === 'dark'"
                  xmlns="http://www.w3.org/2000/svg"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
                </svg>
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <rect width="20" height="14" x="2" y="3" rx="2" />
                  <line x1="8" x2="16" y1="21" y2="21" />
                  <line x1="12" x2="12" y1="17" y2="21" />
                </svg>
                <span>{{ option.label }}</span>
              </button>
            </div>
            <p class="text-xs text-muted-foreground">
              Выбор сохраняется сразу и применяется на всех устройствах после входа.
            </p>
          </fieldset>

          <Button type="submit" :disabled="profileSaving || themeSaving">
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
