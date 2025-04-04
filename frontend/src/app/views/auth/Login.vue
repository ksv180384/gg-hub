<script setup>
import { ref, reactive } from "vue";
import { zodResolver } from '@primevue/forms/resolvers/zod';
import { z } from 'zod';

import AuthLayout from "@/app/layouts/AuthLayout.vue";

const form = reactive({
  email: '',
  password: '',
});

const resolver = ref(zodResolver(
  z.object({
    email: z.string().min(1, { message: 'Заолните email .' }).email({ message: 'Некорректный email.' }),
    password: z.string().min(1, { message: 'Заолните password .' })
  })
));

const onFormSubmit = ({ valid }) => {
  console.log(valid)
};
</script>

<template>
<auth-layout>
  <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
    Авторизация
  </h2>
  <Form
    :resolver="resolver"
    :initialValues="form"
    @submit="onFormSubmit"
    class="mt-8 space-y-6"
  >
    <div>
      <FloatLabel variant="on">
        <InputText id="inputEmail" class="w-full" v-model="form.email" />
        <label for="inputEmail">Email</label>
      </FloatLabel>
    </div>
    <div>
      <FloatLabel variant="on">
        <Password id="inputPassvrd" class="w-full" v-model="form.password" toggleMask :feedback="false"/>
        <label for="inputPassvrd">Password</label>
      </FloatLabel>
    </div>
    <div class="flex items-start">
      <div class="flex items-center h-5">
        <input id="remember" aria-describedby="remember" name="remember" type="checkbox" class="w-4 h-4 border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-primary-300 dark:focus:ring-primary-600 dark:ring-offset-gray-800 dark:bg-gray-700 dark:border-gray-600" required="">
      </div>
      <div class="ml-3 text-sm">
        <label for="remember" class="font-medium text-gray-900 dark:text-white">Remember me</label>
      </div>
      <a href="#" class="ml-auto text-sm text-primary-700 hover:underline dark:text-primary-500">Lost Password?</a>
    </div>
    <button type="submit" class="w-full px-5 py-3 text-base font-medium text-center text-white bg-primary-700 rounded-lg hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 sm:w-auto dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">Login to your account</button>
    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
      Not registered? <a class="text-primary-700 hover:underline dark:text-primary-500">Create account</a>
    </div>
  </Form>
</auth-layout>
</template>

<style scoped>

</style>
