<script lang="ts">
import { defineComponent, ref, onMounted, createCommentVNode } from 'vue';

export default defineComponent({
  name: 'ClientOnly',
  setup(_, { slots }) {
    const isMounted = ref(false);
    onMounted(() => {
      isMounted.value = true;
    });
    return () =>
      isMounted.value ? slots.default?.() : createCommentVNode('client-only');
  },
});
</script>
