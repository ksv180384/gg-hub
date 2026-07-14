<template>
  <section class="auth-frame">
    <slot />
  </section>
</template>

<style scoped>
.auth-frame {
  position: relative;
  width: 100%;
  padding: 2.55rem 2.2rem;
  border: 1px solid rgba(185, 116, 16, 0.92);
  color: #fff;
  background: linear-gradient(145deg, rgba(7, 16, 22, 0.97), rgba(4, 11, 16, 0.95));
  box-shadow:
    0 28px 72px rgba(0, 0, 0, 0.58),
    inset 0 0 0 7px rgba(3, 10, 15, 0.96),
    inset 0 0 0 8px rgba(174, 108, 15, 0.58);
}

.auth-frame::before {
  content: '';
  position: absolute;
  inset: 9px;
  border: 1px solid rgba(154, 94, 13, 0.55);
  pointer-events: none;
}

.auth-frame::after {
  content: '';
  position: absolute;
  inset: 9px;
  pointer-events: none;
  background:
    linear-gradient(#b97717, #b97717) left top / 12px 1px no-repeat,
    linear-gradient(#b97717, #b97717) left top / 1px 12px no-repeat,
    linear-gradient(#b97717, #b97717) right top / 12px 1px no-repeat,
    linear-gradient(#b97717, #b97717) right top / 1px 12px no-repeat,
    linear-gradient(#b97717, #b97717) left bottom / 12px 1px no-repeat,
    linear-gradient(#b97717, #b97717) left bottom / 1px 12px no-repeat,
    linear-gradient(#b97717, #b97717) right bottom / 12px 1px no-repeat,
    linear-gradient(#b97717, #b97717) right bottom / 1px 12px no-repeat;
}

:deep(.auth-frame__header) {
  margin-bottom: 1.8rem;
  text-align: center;
}

:deep(.auth-frame__header h1) {
  font-size: 2rem;
  font-weight: 700;
}

:deep(.auth-frame__header p) {
  margin-top: 0.45rem;
  color: #ffffffa8;
}

:deep(.auth-form),
:deep(.auth-field) {
  display: flex;
  flex-direction: column;
}

:deep(.auth-form) {
  gap: 1rem;
}

:deep(.auth-field) {
  gap: 0.45rem;
}

:deep(.auth-field > label) {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}

:deep(.auth-field input:not([type='checkbox'])) {
  height: 3.55rem;
  padding-right: 3.55rem;
  padding-left: 3.3rem;
  border-color: #ffffff4d;
  color: #fff;
  background-color: #ffffff08;
}

:deep(.auth-field input::placeholder) {
  color: #ffffff7a;
}

:deep(.auth-field > div.relative) {
  display: grid;
  grid-template: 3.55rem / 1fr;
  height: 3.55rem;
}

:deep(.auth-field > div.relative > input),
:deep(.auth-field > div.relative > button) {
  grid-area: 1 / 1;
}

:deep(.auth-field > div.relative > button) {
  position: relative !important;
  inset: auto !important;
  place-self: center end;
  width: 3.55rem;
  height: 3.55rem;
  transform: none !important;
}

:deep(.auth-submit) {
  height: 3.55rem;
  border: 0;
  color: #171108;
  font-weight: 700;
  background: linear-gradient(105deg, #f4b823, #ffd34c, #efad18);
}

:deep(.auth-divider) {
  position: relative;
  display: flex;
  height: 2rem;
  align-items: center;
  justify-content: center;
}

:deep(.auth-divider::before) {
  content: '';
  position: absolute;
  right: 0;
  left: 0;
  height: 1px;
  background: #ffffff5c;
}

:deep(.auth-divider span) {
  position: relative;
  padding: 0 1rem;
  color: #ffffffd1;
  background: #071016;
}

:deep(a:not(.social-auth-buttons__yandex):not(.auth-home-link)) {
  color: #ffc42e;
}
</style>
<style scoped>
.auth-frame :deep(.auth-field input[type='email']),
.auth-frame :deep(.auth-field input[id*='name']) {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23d8dadd' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='7' r='4'/%3E%3Cpath d='M4 21c0-5 3-8 8-8s8 3 8 8'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: 1rem center;
  background-size: 1.5rem;
}

.auth-frame :deep(.auth-field input[type='password']),
.auth-frame :deep(.auth-field input[id*='password']),
.auth-frame :deep(.auth-field input[id*='confirmation']) {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23d8dadd' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='5' y='10' width='14' height='11' rx='2'/%3E%3Cpath d='M8 10V7a4 4 0 0 1 8 0v3M12 14v3'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: 1rem center;
  background-size: 1.5rem;
}
</style>
<style scoped>
.auth-frame :deep(button[aria-label='Показать пароль']),
.auth-frame :deep(button[aria-label='Скрыть пароль']) {
  color: rgba(235, 236, 237, 0.9);
}

.auth-frame :deep(button[aria-label='Показать пароль']:hover),
.auth-frame :deep(button[aria-label='Скрыть пароль']:hover) {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.06);
}

.auth-frame :deep(button[aria-label='Показать пароль']:focus-visible),
.auth-frame :deep(button[aria-label='Скрыть пароль']:focus-visible) {
  color: #ffffff;
  outline: 2px solid rgba(255, 196, 46, 0.42);
  outline-offset: -3px;
}
</style>
