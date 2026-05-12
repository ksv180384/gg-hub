import { computed, ref } from 'vue';
import {
  eventHistoryTitlesApi,
  type EventHistoryTitleDto,
} from '@/shared/api/eventHistoryTitlesApi';

export type EventHistoryTitleForm = {
  name: string;
  dkp_base_points: string;
};

export function useEventHistoryTitlesAdmin(options: { dkpEnabled: () => boolean }) {
  const open = ref(false);
  const loading = ref(false);
  const listError = ref('');
  const formError = ref('');
  const saving = ref(false);
  const deletingId = ref<number | null>(null);
  const titles = ref<EventHistoryTitleDto[]>([]);
  const editingId = ref<number | null>(null);
  const createFormOpen = ref(false);
  const form = ref<EventHistoryTitleForm>({ name: '', dkp_base_points: '' });
  const editForm = ref<EventHistoryTitleForm>({ name: '', dkp_base_points: '' });

  const sortedTitles = computed(() =>
    [...titles.value].sort((a, b) => a.name.localeCompare(b.name, 'ru')),
  );

  function resetCreateForm() {
    form.value = { name: '', dkp_base_points: '' };
    formError.value = '';
  }

  function resetEditForm() {
    editingId.value = null;
    editForm.value = { name: '', dkp_base_points: '' };
    formError.value = '';
  }

  function openCreateForm() {
    resetEditForm();
    resetCreateForm();
    createFormOpen.value = true;
  }

  function cancelCreateForm() {
    createFormOpen.value = false;
    resetCreateForm();
  }

  function parseDkpValue(raw: string): number | null {
    const trimmed = raw.trim();
    if (!trimmed) return null;
    const value = Number(trimmed);
    if (!Number.isFinite(value) || value < 0) return null;
    return Math.trunc(value);
  }

  function buildPayload(source: EventHistoryTitleForm) {
    const payload: { name: string; dkp_base_points?: number | null } = {
      name: source.name.trim(),
    };
    if (options.dkpEnabled()) {
      payload.dkp_base_points = parseDkpValue(source.dkp_base_points);
    }
    return payload;
  }

  async function loadTitles() {
    loading.value = true;
    listError.value = '';
    try {
      titles.value = await eventHistoryTitlesApi.list({ limit: 0 });
    } catch (e: unknown) {
      titles.value = [];
      listError.value = e instanceof Error ? e.message : 'Не удалось загрузить виды событий.';
    } finally {
      loading.value = false;
    }
  }

  async function openModal() {
    open.value = true;
    createFormOpen.value = false;
    resetCreateForm();
    resetEditForm();
    await loadTitles();
  }

  function closeModal() {
    if (saving.value || deletingId.value != null) return;
    open.value = false;
    createFormOpen.value = false;
    resetCreateForm();
    resetEditForm();
  }

  async function createTitle() {
    const payload = buildPayload(form.value);
    if (!payload.name) {
      formError.value = 'Укажите название вида события.';
      return;
    }
    saving.value = true;
    formError.value = '';
    try {
      const created = await eventHistoryTitlesApi.create(payload);
      titles.value = [...titles.value, created];
      createFormOpen.value = false;
      resetCreateForm();
    } catch (e: unknown) {
      formError.value = e instanceof Error ? e.message : 'Не удалось добавить вид события.';
    } finally {
      saving.value = false;
    }
  }

  function startEdit(title: EventHistoryTitleDto) {
    createFormOpen.value = false;
    resetCreateForm();
    editingId.value = title.id;
    editForm.value = {
      name: title.name,
      dkp_base_points: title.dkp_base_points == null ? '' : String(title.dkp_base_points),
    };
    formError.value = '';
  }

  async function saveEdit() {
    if (!editingId.value) return;
    const payload = buildPayload(editForm.value);
    if (!payload.name) {
      formError.value = 'Укажите название вида события.';
      return;
    }
    saving.value = true;
    formError.value = '';
    try {
      const updated = await eventHistoryTitlesApi.update(editingId.value, payload);
      titles.value = titles.value.map((title) => (title.id === updated.id ? updated : title));
      resetEditForm();
    } catch (e: unknown) {
      formError.value = e instanceof Error ? e.message : 'Не удалось сохранить вид события.';
    } finally {
      saving.value = false;
    }
  }

  async function deleteTitle(title: EventHistoryTitleDto) {
    if ((title.histories_count ?? 0) > 0) return;
    deletingId.value = title.id;
    formError.value = '';
    try {
      await eventHistoryTitlesApi.delete(title.id);
      titles.value = titles.value.filter((item) => item.id !== title.id);
      if (editingId.value === title.id) {
        resetEditForm();
      }
    } catch (e: unknown) {
      formError.value = e instanceof Error ? e.message : 'Не удалось удалить вид события.';
    } finally {
      deletingId.value = null;
    }
  }

  return {
    open,
    loading,
    listError,
    formError,
    saving,
    deletingId,
    titles,
    sortedTitles,
    editingId,
    createFormOpen,
    form,
    editForm,
    openModal,
    closeModal,
    openCreateForm,
    cancelCreateForm,
    createTitle,
    startEdit,
    saveEdit,
    resetEditForm,
    deleteTitle,
  };
}
