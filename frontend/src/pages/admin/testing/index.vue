<script setup lang="ts">
import { ref } from 'vue';
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Label } from '@/shared/ui';
import { adminTestingApi } from '@/shared/api/adminTestingApi';

const message = ref('');
const email = ref('');
const telegramLoading = ref(false);
const emailLoading = ref(false);
const telegramFeedback = ref<string | null>(null);
const emailFeedback = ref<string | null>(null);
const telegramError = ref<string | null>(null);
const emailError = ref<string | null>(null);

async function sendTelegram() {
  telegramFeedback.value = null;
  telegramError.value = null;
  telegramLoading.value = true;

  try {
    telegramFeedback.value = await adminTestingApi.sendTelegram(message.value);
  } catch (error) {
    telegramError.value = error instanceof Error ? error.message : 'Не удалось отправить сообщение.';
  } finally {
    telegramLoading.value = false;
  }
}

async function sendEmail() {
  emailFeedback.value = null;
  emailError.value = null;
  emailLoading.value = true;

  try {
    emailFeedback.value = await adminTestingApi.sendEmail(email.value, message.value);
  } catch (error) {
    emailError.value = error instanceof Error ? error.message : 'Не удалось отправить письмо.';
  } finally {
    emailLoading.value = false;
  }
}
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-3xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Тестирование</h1>
      <p class="mb-8 text-muted-foreground">
        Проверьте отправку уведомлений через настроенные каналы Telegram и email.
      </p>

      <Card>
        <CardHeader>
          <CardTitle>Тестовое сообщение</CardTitle>
          <p>
            Один и тот же текст будет использован для Telegram и письма.
          </p>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="space-y-2">
            <Label for="testing-message">Сообщение</Label>
            <textarea
              id="testing-message"
              v-model="message"
              rows="5"
              maxlength="4000"
              class="flex min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-primary/30"
              placeholder="Введите текст тестового сообщения"
            />
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <Button
              type="button"
              :disabled="telegramLoading || !message.trim()"
              @click="sendTelegram"
            >
              {{ telegramLoading ? 'Отправка…' : 'Отправить в Telegram' }}
            </Button>
            <p v-if="telegramFeedback" class="text-sm text-emerald-600">
              {{ telegramFeedback }}
            </p>
            <p v-if="telegramError" class="text-sm text-destructive">
              {{ telegramError }}
            </p>
          </div>

          <div class="border-t pt-6">
            <div class="space-y-2">
              <Label for="testing-email">Email получателя</Label>
              <Input
                id="testing-email"
                v-model="email"
                type="email"
                autocomplete="email"
                placeholder="name@example.com"
              />
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
              <Button
                type="button"
                variant="outline"
                :disabled="emailLoading || !message.trim() || !email.trim()"
                @click="sendEmail"
              >
                {{ emailLoading ? 'Отправка…' : 'Отправить на email' }}
              </Button>
              <p v-if="emailFeedback" class="text-sm text-emerald-600">
                {{ emailFeedback }}
              </p>
              <p v-if="emailError" class="text-sm text-destructive">
                {{ emailError }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
