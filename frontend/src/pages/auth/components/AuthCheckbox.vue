<script setup lang="ts">
withDefaults(
  defineProps<{
    id?: string;
    invalid?: boolean;
  }>(),
  {
    id: undefined,
    invalid: false,
  },
);

const checked = defineModel<boolean>({ required: true });
</script>

<template>
  <label class="auth-checkbox" :for="id">
    <span class="auth-checkbox__control">
      <input
        :id="id"
        v-model="checked"
        type="checkbox"
        :aria-invalid="invalid || undefined"
      >
      <svg viewBox="0 0 16 16" aria-hidden="true">
        <path d="m3.5 8 3 3 6-7" />
      </svg>
    </span>
    <span class="auth-checkbox__label"><slot /></span>
  </label>
</template>

<style scoped>
.auth-checkbox {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  cursor: pointer;
}

.auth-checkbox__control {
  position: relative;
  width: 1.25rem;
  height: 1.25rem;
  flex: 0 0 1.25rem;
  margin-top: 0.05rem;
}

.auth-checkbox input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  appearance: none;
  border: 1px solid rgba(235, 236, 237, 0.68);
  border-radius: 0.18rem;
  background: rgba(255, 255, 255, 0.025);
  cursor: pointer;
}

.auth-checkbox input:checked {
  border-color: #ffc42e;
  background: #ffc42e;
}

.auth-checkbox input:focus-visible {
  outline: 2px solid rgba(255, 196, 46, 0.45);
  outline-offset: 2px;
}

.auth-checkbox input[aria-invalid='true'] {
  border-color: hsl(var(--destructive));
}

.auth-checkbox svg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  fill: none;
  stroke: #171108;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  opacity: 0;
  pointer-events: none;
}

.auth-checkbox input:checked + svg {
  opacity: 1;
}

.auth-checkbox__label {
  color: rgba(255, 255, 255, 0.72);
  font-size: 0.875rem;
  line-height: 1.4;
}
</style>
