<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import PostForm from '../PostForm.vue';

const route = useRoute();
const postId = computed(() => Number(route.params.id));
</script>

<template>
  <h1>weee</h1>
  <PostForm :post-id="postId" />
</template>

