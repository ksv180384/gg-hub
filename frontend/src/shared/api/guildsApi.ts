/**
 * API гильдий.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';
import type { PermissionGroupDto } from '@/shared/api/accessApi';
import type { GameClass } from '@/shared/api/gamesApi';

export interface GuildGame {
  id: number;
  name: string;
  slug: string;
  /** Размер пати (число ячеек в ряду сетки рейда). */
  party_size?: number;
}

export interface GuildLocalization {
  id: number;
  code: string;
  name: string;
}

export interface GuildServer {
  id: number;
  name: string;
  slug?: string;
}

/** Тег гильдии в ответе API (только отображение). */
export interface GuildTagLabel {
  id: number;
  name: string;
}

export interface GuildLeader {
  id: number;
  name: string;
  server_id?: number;
}

/** Гильдия пользователя для меню (текущая игра). */
export interface UserGuildItem {
  id: number;
  name: string;
  is_leader: boolean;
  /** Доступ к странице «Роли членов гильдии» (хотя бы одно из прав). */
  can_access_roles?: boolean;
  /** Право приглашать в гильдию (подтверждение/отклонение заявок). */
  can_invite?: boolean;
  /** Включён показ состава неучастникам (авторизованным), если открыт в настройках гильдии. */
  show_roster_to_all?: boolean;
}

export interface Guild {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  /** Внутренний путь к файлу (если когда-либо вернётся с API). */
  logo_path?: string | null;
  logo_url: string | null;
  /** URL логотипа 350px для карточек (если есть). */
  logo_card_url?: string | null;
  show_roster_to_all: boolean;
  about_text: string | null;
  charter_text: string | null;
  owner_id: number;
  leader_character_id: number | null;
  leader?: GuildLeader | null;
  members_count?: number;
  is_recruiting: boolean;
  /** Текст над формой заявки (вкладка «Форма заявки», публичная страница заявки). */
  application_form_description?: string | null;
  game_id: number;
  localization_id: number;
  server_id: number;
  game?: GuildGame;
  localization?: GuildLocalization;
  server?: GuildServer;
  tags?: GuildTagLabel[];
  /** Дополнительные поля формы заявки (приходит с GET /guilds/:id/settings). */
  application_form_fields?: GuildApplicationFormFieldDto[];
  /** Права текущего пользователя в гильдии (приходит только с GET /guilds/:id/settings). */
  my_permission_slugs?: string[];
  /** Персонажи текущего пользователя в гильдии (приходит с GET /guilds/:id/settings). */
  my_characters?: { id: number; name: string; avatar_url?: string | null }[];
  /** Смена leader_character_id: владелец гильдии или текущий лидер (по персонажу). GET /guilds/:id/settings. */
  can_change_guild_leader?: boolean;
  /**
   * Можно ли менять локализацию/сервер гильдии: только если в гильдии один участник
   * и он же является лидером гильдии. GET /guilds/:id/settings.
   */
  can_change_localization_server?: boolean;
  /**
   * URL Discord-вебхука. Потенциально секретное поле — отдаётся только эндпоинтами,
   * требующими права на редактирование гильдии (GET /guilds/:id/settings и ответ PUT /guilds/:id).
   * В публичных GET /guilds и GET /guilds/:id поле отсутствует.
   */
  discord_webhook_url?: string | null;
  discord_notify_application_new?: boolean;
  discord_notify_member_joined?: boolean;
  discord_notify_member_left?: boolean;
  discord_notify_event_starting?: boolean;
  discord_notify_poll_started?: boolean;
  discord_notify_role_changed?: boolean;
  discord_notify_post_published?: boolean;
}

/** Дополнительное поле формы заявки гильдии. */
export interface GuildApplicationFormFieldDto {
  id: number;
  guild_id: number;
  name: string;
  type: 'text' | 'textarea' | 'screenshot' | 'select' | 'multiselect';
  required: boolean;
  sort_order: number;
  /** Варианты выбора для type === 'select' | 'multiselect'. */
  options?: string[];
}

/** Данные для страницы подачи заявки в гильдию (публичный эндпоинт). */
export interface GuildApplicationFormData {
  id: number;
  name: string;
  slug: string;
  logo_url: string | null;
  logo_card_url: string | null;
  is_recruiting: boolean;
  application_form_description?: string | null;
  game?: { id: number; name: string };
  server?: { id: number; name: string };
  application_form_fields: GuildApplicationFormFieldDto[];
}

/** Полезная нагрузка при подаче заявки в гильдию. */
export interface SubmitGuildApplicationPayload {
  character_id: number;
  /** Для multiselect значения передаются как JSON-строка массива выбранных вариантов. */
  form_data: Record<number, string>;
}

/** Одна заявка в гильдию (ответ show/approve/reject). */
export interface GuildApplicationItem {
  id: number;
  guild_id: number;
  character_id: number;
  /** Гильдия (приходит при загрузке заявки для владельца/участников). */
  guild?: { id: number; name: string };
  character?: {
    id: number;
    name: string;
    /** Классы персонажа (для отображения в карточке заявки). */
    game_classes?: GameClass[];
  };
  form_data: Record<number, string>;
  /** Соответствие id поля формы → название (для отображения вместо «Поле 1», «Поле 2»). */
  form_field_labels?: Record<number | string, string>;
  /** Тип поля формы (например screenshot), когда подгружена гильдия с applicationFormFields. */
  form_field_types?: Record<number | string, string>;
  status: 'pending' | 'invitation' | 'approved' | 'rejected' | 'revoked' | 'withdrawn';
  /** ID персонажа-участника гильдии, от имени которого отправлено приглашение (для status === 'invitation'). */
  invited_by_character_id?: number | null;
  invited_by_character?: { id: number; name: string } | null;
  /** ID персонажа, отозвавшего приглашение (для status === 'revoked'). */
  revoked_by_character_id?: number | null;
  revoked_by_character?: { id: number; name: string } | null;
  likes_count?: number;
  dislikes_count?: number;
  my_vote?: 'like' | 'dislike' | null;
  reviewed_at?: string | null;
  created_at?: string | null;
}

export interface GuildApplicationCommentItem {
  id: number;
  post_id: number;
  user_id: number;
  parent_id: number | null;
  replied_to_comment_id?: number | null;
  body: string | null;
  is_hidden?: boolean;
  /** Удалён автором, но оставлен в дереве из‑за дочерних ответов (API заявки гильдии). */
  is_deleted?: boolean;
  author_name: string;
  author_avatar_url: string | null;
  replied_to_author_name: string | null;
  created_at: string;
  depth: number;
  children: GuildApplicationCommentItem[];
}

export interface GuildApplicationCommentCharacter {
  id: number;
  name: string;
  avatar_url: string | null;
}

export interface CreateGuildApplicationFormFieldPayload {
  name: string;
  type: 'text' | 'textarea' | 'screenshot' | 'select' | 'multiselect';
  required?: boolean;
  /** Варианты выбора для type === 'select' | 'multiselect'. */
  options?: string[];
}

export interface UpdateGuildApplicationFormFieldPayload {
  name?: string;
  type?: 'text' | 'textarea' | 'screenshot' | 'select' | 'multiselect';
  required?: boolean;
  options?: string[];
}

export interface CreateGuildPayload {
  name: string;
  localization_id: number;
  server_id: number;
  leader_character_id: number;
  description?: string;
  tag_ids?: number[];
}

export interface UpdateGuildPayload {
  name?: string;
  localization_id?: number;
  server_id?: number;
  show_roster_to_all?: boolean;
  is_recruiting?: boolean;
  about_text?: string | null;
  charter_text?: string | null;
  application_form_description?: string | null;
  logo?: File | null;
  remove_logo?: boolean;
  tag_ids?: number[];
  leader_character_id?: number;
  /** URL Discord-вебхука (пустая строка/null — стирает значение). */
  discord_webhook_url?: string | null;
  discord_notify_application_new?: boolean;
  discord_notify_member_joined?: boolean;
  discord_notify_member_left?: boolean;
  discord_notify_event_starting?: boolean;
  discord_notify_poll_started?: boolean;
  discord_notify_role_changed?: boolean;
  discord_notify_post_published?: boolean;
}

/** Ответ сервера: список гильдий с пагинацией (GET /guilds). */
export interface GuildsListResponse {
  data: Guild[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

/** Ответ сервера при ошибке (message). */
export interface ErrorMessageResponse {
  message?: string;
}

function unwrapGuild(res: { data: unknown }): Guild {
  const raw = res.data as { data?: Guild } | Guild | null;
  if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: Guild }).data!;
  return raw as Guild;
}

/** Роль гильдии (для страницы «Роли членов гильдии»). */
export interface GuildRole {
  id: number;
  guild_id: number;
  name: string;
  slug: string;
  priority: number;
  permissions?: { id: number; name: string; slug: string }[];
}

/** Участник состава гильдии (персонаж с ролью, классами, тегами). */
export interface GuildRosterMember {
  character_id: number;
  name: string;
  avatar_url: string | null;
  game_classes: {
    id: number;
    name: string;
    name_ru?: string;
    slug: string;
    image?: string | null;
    image_thumb?: string | null;
  }[];
  guild_role: { id: number; name: string; slug: string } | null;
  /** Теги в контексте гильдии (character_guild_tag). */
  tags: {
    id: number;
    name: string;
    slug: string;
    used_by_user_id?: number | null;
    used_by_guild_id?: number | null;
    created_by_user_id?: number | null;
    used_by?: { id: number; name: string } | null;
    created_by?: { id: number; name: string } | null;
  }[];
  /** Личные теги персонажа (character_tag). */
  personal_tags: {
    id: number;
    name: string;
    slug: string;
    used_by_user_id?: number | null;
    used_by_guild_id?: number | null;
    created_by_user_id?: number | null;
    used_by?: { id: number; name: string } | null;
    created_by?: { id: number; name: string } | null;
  }[];
}

/** Роли гильдии в meta ответа GET /guilds/:id/roster (полный список для фильтров). */
export interface GuildRosterRoleSummary {
  id: number;
  name: string;
  slug: string;
}

export interface GuildRosterResponse {
  members: GuildRosterMember[];
  guild_roles: GuildRosterRoleSummary[];
}

export const guildsApi = {
  /** Гильдии текущей игры, в которых состоит пользователь (по персонажам). */
  async getMyGuildsForGame(gameId: number): Promise<UserGuildItem[]> {
    const res = await http.fetchGet<{ data: UserGuildItem[] }>('/user/guilds', {
      params: { game_id: gameId },
    });
    throwOnError(res, 'Ошибка загрузки гильдий');
    const raw = res.data as { data?: UserGuildItem[] } | null;
    return raw?.data ?? [];
  },

  async getGuilds(params?: {
    per_page?: number;
    page?: number;
    game_id?: number;
    name?: string;
    localization_ids?: number[];
    server_ids?: number[];
    is_recruiting?: boolean;
  }): Promise<{ guilds: Guild[]; meta: GuildsListResponse['meta'] }> {
    const query: Record<string, string | number | number[] | undefined> = {};
    if (params?.per_page != null) query.per_page = params.per_page;
    if (params?.page != null) query.page = params.page;
    if (params?.game_id != null) query.game_id = params.game_id;
    if (params?.name != null && params.name.trim() !== '') query.name = params.name.trim();
    if (params?.localization_ids?.length) query.localization_ids = params.localization_ids;
    if (params?.server_ids?.length) query.server_ids = params.server_ids;
    if (params?.is_recruiting === true) query.is_recruiting = 1;

    const res = await http.fetchGet<GuildsListResponse>('/guilds', { params: query });
    throwOnError(res, 'Ошибка загрузки гильдий');
    const data = res.data as GuildsListResponse | null;
    const list = Array.isArray(data?.data) ? data.data : [];
    const meta = data?.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 };
    return { guilds: list, meta };
  },

  async getGuild(id: number): Promise<Guild> {
    const res = await http.fetchGet<{ data: Guild } | Guild>(`/guilds/${id}`);
    throwOnError(res, 'Ошибка загрузки гильдии');
    return unwrapGuild(res);
  },

  /** Данные формы заявки в гильдию (GET /guilds/:id/application-form, без авторизации). */
  async getGuildApplicationForm(guildId: number): Promise<GuildApplicationFormData> {
    const res = await http.fetchGet<GuildApplicationFormData | { data: GuildApplicationFormData }>(
      `/guilds/${guildId}/application-form`
    );
    throwOnError(res, 'Ошибка загрузки формы заявки');
    const raw = res.data as GuildApplicationFormData | { data?: GuildApplicationFormData } | null;
    if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: GuildApplicationFormData }).data!;
    return raw as GuildApplicationFormData;
  },

  /** Подать заявку в гильдию (POST /guilds/:id/applications). */
  async submitGuildApplication(guildId: number, payload: SubmitGuildApplicationPayload): Promise<unknown> {
    const res = await http.fetchPost<{ data: unknown }>(`/guilds/${guildId}/applications`, {
      character_id: payload.character_id,
      form_data: payload.form_data,
    });
    throwOnError(res, 'Ошибка отправки заявки');
    return (res.data as { data?: unknown })?.data ?? res.data;
  },

  /** Список заявок в гильдию (GET /guilds/:id/applications). Только для участников с правом просмотра заявок. */
  async getGuildApplications(
    guildId: number,
    params?: {
      page?: number;
      per_page?: number;
      status?: GuildApplicationItem['status'];
      character_name?: string;
    }
  ): Promise<{ applications: GuildApplicationItem[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
    const page = params?.page ?? 1;
    const perPage = params?.per_page ?? 20;
    const status = params?.status;
    const characterName = params?.character_name?.trim();
    const res = await http.fetchGet<{ data: GuildApplicationItem[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>(
      `/guilds/${guildId}/applications`,
      { params: { page, per_page: perPage, ...(status ? { status } : {}), ...(characterName ? { character_name: characterName } : {}) } }
    );
    throwOnError(res, 'Ошибка загрузки заявок');
    const data = res.data as { data?: GuildApplicationItem[]; meta?: { current_page: number; last_page: number; per_page: number; total: number } } | null;
    const list = data && typeof data === 'object' && Array.isArray((data as { data?: GuildApplicationItem[] }).data)
      ? (data as { data: GuildApplicationItem[] }).data
      : [];
    const meta = data && typeof data === 'object' && data.meta
      ? data.meta
      : { current_page: 1, last_page: 1, per_page: perPage, total: 0 };
    return { applications: list, meta };
  },

  /** Одна заявка (GET /guilds/:id/applications/:applicationId). Только для участников с правом просмотра заявок. */
  async getGuildApplication(guildId: number, applicationId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchGet<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}`
    );
    throwOnError(res, 'Ошибка загрузки заявки');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Принять заявку (POST /guilds/:id/applications/:id/approve). */
  async approveGuildApplication(guildId: number, applicationId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchPost<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}/approve`,
      {}
    );
    throwOnError(res, 'Ошибка принятия заявки');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Отклонить заявку (POST /guilds/:id/applications/:id/reject). */
  async rejectGuildApplication(guildId: number, applicationId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchPost<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}/reject`,
      {}
    );
    throwOnError(res, 'Ошибка отклонения заявки');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Отправить приглашение в гильдию персонажу. */
  async sendGuildInvitation(guildId: number, characterId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchPost<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/invitations`,
      { character_id: characterId }
    );
    throwOnError(res, 'Ошибка отправки приглашения');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Принять приглашение в гильдию (владелец персонажа). */
  async acceptGuildInvitation(guildId: number, applicationId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchPost<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}/accept-invitation`,
      {}
    );
    throwOnError(res, 'Ошибка принятия приглашения');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Отклонить приглашение в гильдию (владелец персонажа). */
  async declineGuildInvitation(guildId: number, applicationId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchPost<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}/decline-invitation`,
      {}
    );
    throwOnError(res, 'Ошибка отклонения приглашения');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Отозвать приглашение в гильдию (участник с правом «Подтверждение или отклонение заявок»). */
  async revokeGuildInvitation(guildId: number, applicationId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchPost<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}/revoke-invitation`,
      {}
    );
    throwOnError(res, 'Ошибка отзыва приглашения');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Моя заявка: GET /guilds/:id/applications/:applicationId/owner (для пользователя, подавшего заявку). */
  async getMyGuildApplication(guildId: number, applicationId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchGet<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}/owner`
    );
    throwOnError(res, 'Ошибка загрузки заявки');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Отозвать заявку (только в статусе «на рассмотрении»). */
  async withdrawGuildApplication(guildId: number, applicationId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchPost<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}/withdraw`,
      {}
    );
    throwOnError(res, 'Ошибка отзыва заявки');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Лайк/дизлайк заявки участником гильдии. */
  async voteGuildApplication(
    guildId: number,
    applicationId: number,
    vote: 'like' | 'dislike'
  ): Promise<GuildApplicationItem> {
    const res = await http.fetchPost<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}/vote`,
      { vote }
    );
    throwOnError(res, 'Ошибка голосования по заявке');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Убрать голос (лайк/дизлайк) с заявки. */
  async removeGuildApplicationVote(guildId: number, applicationId: number): Promise<GuildApplicationItem> {
    const res = await http.fetchDelete<{ data: GuildApplicationItem } | GuildApplicationItem>(
      `/guilds/${guildId}/applications/${applicationId}/vote`
    );
    throwOnError(res, 'Ошибка удаления голоса по заявке');
    const raw = res.data as GuildApplicationItem | { data?: GuildApplicationItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationItem }).data!;
    return raw as GuildApplicationItem;
  },

  /** Комментарии к заявке (для автора заявки и участников гильдии). */
  async getGuildApplicationComments(guildId: number, applicationId: number): Promise<{
    comments: GuildApplicationCommentItem[];
    myCharacters: GuildApplicationCommentCharacter[];
    defaultCharacterId: number | null;
  }> {
    const res = await http.fetchGet<{
      data: GuildApplicationCommentItem[];
      meta?: {
        my_characters?: GuildApplicationCommentCharacter[];
        default_character_id?: number | null;
      };
    } | { data: GuildApplicationCommentItem[] } | GuildApplicationCommentItem[]>(
      `/guilds/${guildId}/applications/${applicationId}/comments`
    );
    throwOnError(res, 'Ошибка загрузки комментариев');
    const raw = res.data as {
      data?: GuildApplicationCommentItem[];
      meta?: { my_characters?: GuildApplicationCommentCharacter[]; default_character_id?: number | null };
    } | GuildApplicationCommentItem[] | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw) {
      return {
        comments: raw.data ?? [],
        myCharacters: raw.meta?.my_characters ?? [],
        defaultCharacterId: raw.meta?.default_character_id ?? null,
      };
    }
    return { comments: Array.isArray(raw) ? raw : [], myCharacters: [], defaultCharacterId: null };
  },

  async createGuildApplicationComment(
    guildId: number,
    applicationId: number,
    body: string,
    parentId?: number | null,
    characterId?: number | null
  ): Promise<GuildApplicationCommentItem> {
    const res = await http.fetchPost<{ data: GuildApplicationCommentItem } | GuildApplicationCommentItem>(
      `/guilds/${guildId}/applications/${applicationId}/comments`,
      { body, parent_id: parentId ?? null, character_id: characterId }
    );
    throwOnError(res, 'Ошибка отправки комментария');
    const raw = res.data as { data?: GuildApplicationCommentItem } | GuildApplicationCommentItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationCommentItem }).data!;
    return raw as GuildApplicationCommentItem;
  },

  async updateGuildApplicationComment(
    guildId: number,
    applicationId: number,
    commentId: number,
    body: string
  ): Promise<GuildApplicationCommentItem> {
    const res = await http.fetchPut<{ data: GuildApplicationCommentItem } | GuildApplicationCommentItem>(
      `/guilds/${guildId}/applications/${applicationId}/comments/${commentId}`,
      { body }
    );
    throwOnError(res, 'Ошибка сохранения комментария');
    const raw = res.data as { data?: GuildApplicationCommentItem } | GuildApplicationCommentItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationCommentItem }).data!;
    return raw as GuildApplicationCommentItem;
  },

  async deleteGuildApplicationComment(guildId: number, applicationId: number, commentId: number): Promise<void> {
    const res = await http.fetchDelete(`/guilds/${guildId}/applications/${applicationId}/comments/${commentId}`);
    throwOnError(res, 'Ошибка удаления комментария');
  },

  /** Заявки текущего пользователя во все гильдии (GET /user/applications). */
  async getMyGuildApplicationsList(
    params?: { page?: number; per_page?: number }
  ): Promise<{ applications: GuildApplicationItem[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
    const page = params?.page ?? 1;
    const perPage = params?.per_page ?? 20;
    const res = await http.fetchGet<{
      data: GuildApplicationItem[];
      meta: { current_page: number; last_page: number; per_page: number; total: number };
    }>('/user/applications', {
      params: { page, per_page: perPage },
    });
    throwOnError(res, 'Ошибка загрузки заявок');
    const body = res.data as {
      data?: GuildApplicationItem[];
      meta?: { current_page: number; last_page: number; per_page: number; total: number };
    } | null;
    const list = body?.data ?? [];
    const meta = body?.meta ?? { current_page: 1, last_page: 1, per_page: perPage, total: 0 };
    return { applications: list, meta };
  },

  /**
   * Состав гильдии. Только участники гильдии (403 иначе).
   * В guild_roles — все роли гильдии (для фильтров), не только назначенные участникам.
   */
  async getGuildRoster(guildId: number): Promise<GuildRosterResponse> {
    const res = await http.fetchGet<{
      data: GuildRosterMember[];
      meta?: { guild_roles?: GuildRosterRoleSummary[] };
    }>(`/guilds/${guildId}/roster`);
    throwOnError(res, 'Ошибка загрузки состава');
    const raw = res.data as {
      data?: GuildRosterMember[];
      meta?: { guild_roles?: GuildRosterRoleSummary[] };
    } | null;
    return {
      members: raw?.data ?? [],
      guild_roles: raw?.meta?.guild_roles ?? [],
    };
  },

  /**
   * Один участник состава (для страницы просмотра). Ответ: { data, can_exclude, can_change_role }.
   */
  async getGuildRosterMember(
    guildId: number,
    characterId: number
  ): Promise<{
    data: GuildRosterMember;
    can_exclude: boolean;
    can_change_role: boolean;
    can_edit_guild_tags: boolean;
    can_create_guild_tag: boolean;
    can_delete_guild_tag: boolean;
  }> {
    const res = await http.fetchGet<{
      data: GuildRosterMember;
      can_exclude: boolean;
      can_change_role: boolean;
      can_edit_guild_tags: boolean;
      can_create_guild_tag: boolean;
      can_delete_guild_tag: boolean;
    }>(`/guilds/${guildId}/roster/${characterId}`);
    throwOnError(res, 'Ошибка загрузки данных участника');
    const raw = res.data as {
      data?: GuildRosterMember;
      can_exclude?: boolean;
      can_change_role?: boolean;
      can_edit_guild_tags?: boolean;
      can_create_guild_tag?: boolean;
      can_delete_guild_tag?: boolean;
    } | null;
    return {
      data: raw?.data ?? ({} as GuildRosterMember),
      can_exclude: raw?.can_exclude ?? false,
      can_change_role: raw?.can_change_role ?? false,
      can_edit_guild_tags: raw?.can_edit_guild_tags ?? false,
      can_create_guild_tag: raw?.can_create_guild_tag ?? false,
      can_delete_guild_tag: raw?.can_delete_guild_tag ?? false,
    };
  },

  /**
   * Теги участника в гильдии (character_guild_tag). Право «Изменять теги пользователей гильдии».
   */
  async updateGuildMemberTags(guildId: number, characterId: number, tagIds: number[]): Promise<void> {
    const res = await http.fetchPut<{ message?: string }>(`/guilds/${guildId}/members/${characterId}/tags`, {
      tag_ids: tagIds,
    });
    throwOnError(res, 'Ошибка сохранения тегов');
  },

  /**
   * Изменить роль участника гильдии. Требуется право «Менять/назначать пользователю роль».
   */
  async updateGuildMemberRole(
    guildId: number,
    characterId: number,
    guildRoleId: number
  ): Promise<void> {
    const res = await http.fetchPut<{ message?: string }>(
      `/guilds/${guildId}/members/${characterId}/role`,
      { guild_role_id: guildRoleId }
    );
    throwOnError(res, 'Не удалось изменить роль');
  },

  /**
   * Исключить участника из гильдии. Требуется право «Исключение пользователя из гильдии».
   */
  async excludeGuildMember(guildId: number, characterId: number): Promise<void> {
    const res = await http.fetchDelete<{ message?: string }>(
      `/guilds/${guildId}/members/${characterId}`
    );
    throwOnError(res, 'Не удалось исключить участника');
  },

  /**
   * Гильдия для страницы настроек. Только для участников гильдии.
   * При 404 (нет доступа к закрытым данным гильдии) обрабатывайте как «не найдено».
   */
  async getGuildForSettings(id: number): Promise<Guild> {
    const res = await http.fetchGet<{ data: Guild } | Guild>(`/guilds/${id}/settings`);
    throwOnError(res, 'Ошибка загрузки гильдии');
    return unwrapGuild(res);
  },

  /** Покинуть гильдию (участник, кроме лидера). */
  async leaveGuild(id: number): Promise<void> {
    const res = await http.fetchPost<{ message?: string }>(`/guilds/${id}/leave`, {});
    throwOnError(res, 'Не удалось покинуть гильдию');
  },

  async createGuild(payload: CreateGuildPayload): Promise<Guild> {
    const res = await http.fetchPost<{ data: Guild } | Guild>('/guilds', {
      name: payload.name,
      localization_id: payload.localization_id,
      server_id: payload.server_id,
      leader_character_id: payload.leader_character_id,
      ...(payload.description != null && payload.description !== '' && { description: payload.description }),
      ...(payload.tag_ids?.length && { tag_ids: payload.tag_ids }),
    });
    throwOnError(res, 'Ошибка создания гильдии');
    return unwrapGuild(res);
  },

  async updateGuild(id: number, payload: UpdateGuildPayload): Promise<Guild> {
    const form = new FormData();
    if (payload.name !== undefined) form.append('name', payload.name);
    if (payload.localization_id !== undefined) form.append('localization_id', String(payload.localization_id));
    if (payload.server_id !== undefined) form.append('server_id', String(payload.server_id));
    if (payload.show_roster_to_all !== undefined) form.append('show_roster_to_all', payload.show_roster_to_all ? '1' : '0');
    if (payload.is_recruiting !== undefined) form.append('is_recruiting', payload.is_recruiting ? '1' : '0');
    if (payload.about_text !== undefined) form.append('about_text', payload.about_text ?? '');
    if (payload.charter_text !== undefined) form.append('charter_text', payload.charter_text ?? '');
    if (payload.application_form_description !== undefined) {
      form.append('application_form_description', payload.application_form_description ?? '');
    }
    if (payload.remove_logo) form.append('remove_logo', '1');
    if (payload.logo) form.append('logo', payload.logo);
    if (payload.tag_ids !== undefined) {
      payload.tag_ids.forEach((id) => form.append('tag_ids[]', String(id)));
    }
    if (payload.leader_character_id !== undefined) {
      form.append('leader_character_id', String(payload.leader_character_id));
    }
    if (payload.discord_webhook_url !== undefined) {
      form.append('discord_webhook_url', payload.discord_webhook_url ?? '');
    }
    if (payload.discord_notify_application_new !== undefined) {
      form.append('discord_notify_application_new', payload.discord_notify_application_new ? '1' : '0');
    }
    if (payload.discord_notify_member_joined !== undefined) {
      form.append('discord_notify_member_joined', payload.discord_notify_member_joined ? '1' : '0');
    }
    if (payload.discord_notify_member_left !== undefined) {
      form.append('discord_notify_member_left', payload.discord_notify_member_left ? '1' : '0');
    }
    if (payload.discord_notify_event_starting !== undefined) {
      form.append('discord_notify_event_starting', payload.discord_notify_event_starting ? '1' : '0');
    }
    if (payload.discord_notify_poll_started !== undefined) {
      form.append('discord_notify_poll_started', payload.discord_notify_poll_started ? '1' : '0');
    }
    if (payload.discord_notify_role_changed !== undefined) {
      form.append('discord_notify_role_changed', payload.discord_notify_role_changed ? '1' : '0');
    }
    if (payload.discord_notify_post_published !== undefined) {
      form.append('discord_notify_post_published', payload.discord_notify_post_published ? '1' : '0');
    }
    form.append('_method', 'PUT');
    const res = await http.fetchPost<{ data: Guild } | Guild>(`/guilds/${id}`, form);
    throwOnError(res, 'Ошибка сохранения гильдии');
    return unwrapGuild(res);
  },

  /** Группы прав гильдии (для страницы ролей). Только для участников гильдии. */
  async getGuildPermissionGroups(guildId: number): Promise<PermissionGroupDto[]> {
    const res = await http.fetchGet<{ data: PermissionGroupDto[] } | PermissionGroupDto[]>(
      `/guilds/${guildId}/permission-groups`
    );
    throwOnError(res, 'Ошибка загрузки прав');
    const raw = res.data as { data?: PermissionGroupDto[] } | PermissionGroupDto[] | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: PermissionGroupDto[] }).data ?? [];
    return Array.isArray(raw) ? raw : [];
  },

  /** Ответ GET /guilds/:id/roles: роли и права текущего пользователя в гильдии. */
  async getGuildRoles(guildId: number): Promise<{ roles: GuildRole[]; myPermissionSlugs: string[] }> {
    const res = await http.fetchGet<{ data: GuildRole[]; my_permission_slugs?: string[] }>(
      `/guilds/${guildId}/roles`
    );
    throwOnError(res, 'Ошибка загрузки ролей');
    const raw = res.data as { data?: GuildRole[]; my_permission_slugs?: string[] } | null;
    const roles = raw && typeof raw === 'object' && 'data' in raw ? (raw.data ?? []) : [];
    const myPermissionSlugs = raw && typeof raw === 'object' && Array.isArray(raw.my_permission_slugs) ? raw.my_permission_slugs : [];
    return { roles, myPermissionSlugs };
  },

  async deleteGuildRole(guildId: number, guildRoleId: number): Promise<void> {
    const res = await http.fetchDelete<{ message?: string }>(`/guilds/${guildId}/roles/${guildRoleId}`);
    throwOnError(res, 'Ошибка удаления роли');
  },

  async createGuildRole(guildId: number, payload: { name: string; slug?: string }): Promise<GuildRole> {
    const res = await http.fetchPost<{ data: GuildRole } | GuildRole>(`/guilds/${guildId}/roles`, payload);
    throwOnError(res, 'Ошибка создания роли');
    const raw = res.data as { data?: GuildRole } | GuildRole | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw) return (raw as { data: GuildRole }).data!;
    return raw as GuildRole;
  },

  async updateGuildRolePermissions(guildId: number, guildRoleId: number, permissionIds: number[]): Promise<GuildRole> {
    const res = await http.fetchPut<{ data: GuildRole } | GuildRole>(
      `/guilds/${guildId}/roles/${guildRoleId}/permissions`,
      { permission_ids: permissionIds }
    );
    throwOnError(res, 'Ошибка сохранения прав');
    const raw = res.data as { data?: GuildRole } | GuildRole | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw) return (raw as { data: GuildRole }).data!;
    return raw as GuildRole;
  },

  async createApplicationFormField(
    guildId: number,
    payload: CreateGuildApplicationFormFieldPayload
  ): Promise<GuildApplicationFormFieldDto> {
    const body: Record<string, unknown> = {
      name: payload.name,
      type: payload.type,
      required: payload.required ?? false,
    };
    if (payload.options?.length) body.options = payload.options;
    const res = await http.fetchPost<{ data: GuildApplicationFormFieldDto } | GuildApplicationFormFieldDto>(
      `/guilds/${guildId}/application-form-fields`,
      body
    );
    throwOnError(res, 'Ошибка добавления поля');
    const raw = res.data as { data?: GuildApplicationFormFieldDto } | GuildApplicationFormFieldDto | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationFormFieldDto }).data!;
    return raw as GuildApplicationFormFieldDto;
  },

  async updateApplicationFormField(
    guildId: number,
    fieldId: number,
    payload: UpdateGuildApplicationFormFieldPayload
  ): Promise<GuildApplicationFormFieldDto> {
    const res = await http.fetchPut<{ data: GuildApplicationFormFieldDto } | GuildApplicationFormFieldDto>(
      `/guilds/${guildId}/application-form-fields/${fieldId}`,
      payload
    );
    throwOnError(res, 'Ошибка сохранения поля');
    const raw = res.data as { data?: GuildApplicationFormFieldDto } | GuildApplicationFormFieldDto | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationFormFieldDto }).data!;
    return raw as GuildApplicationFormFieldDto;
  },

  async deleteApplicationFormField(guildId: number, fieldId: number): Promise<void> {
    const res = await http.fetchDelete(`/guilds/${guildId}/application-form-fields/${fieldId}`);
    throwOnError(res, 'Ошибка удаления поля');
  },

  // ——— Рейды ———

  /** Дерево рейдов гильдии (GET /guilds/:id/raids). Только для участников. */
  async getGuildRaids(guildId: number): Promise<RaidItem[]> {
    const res = await http.fetchGet<{ data: RaidItem[] } | RaidItem[]>(`/guilds/${guildId}/raids`);
    throwOnError(res, 'Ошибка загрузки рейдов');
    const raw = res.data as { data?: RaidItem[] } | RaidItem[] | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: RaidItem[] }).data ?? [];
    return Array.isArray(raw) ? raw : [];
  },

  /** Один рейд (GET /guilds/:id/raids/:raidId). */
  async getGuildRaid(guildId: number, raidId: number): Promise<RaidItem> {
    const res = await http.fetchGet<{ data: RaidItem } | RaidItem>(`/guilds/${guildId}/raids/${raidId}`);
    throwOnError(res, 'Ошибка загрузки рейда');
    const raw = res.data as { data?: RaidItem } | RaidItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: RaidItem }).data!;
    return raw as RaidItem;
  },

  /** Создать рейд. Требуется право formirovat-reidy. */
  async createGuildRaid(guildId: number, payload: CreateRaidPayload): Promise<RaidItem> {
    const res = await http.fetchPost<{ data: RaidItem } | RaidItem>(`/guilds/${guildId}/raids`, payload);
    throwOnError(res, 'Ошибка создания рейда');
    const raw = res.data as { data?: RaidItem } | RaidItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: RaidItem }).data!;
    return raw as RaidItem;
  },

  /** Обновить рейд. Требуется право formirovat-reidy. */
  async updateGuildRaid(guildId: number, raidId: number, payload: UpdateRaidPayload): Promise<RaidItem> {
    const res = await http.fetchPut<{ data: RaidItem } | RaidItem>(
      `/guilds/${guildId}/raids/${raidId}`,
      payload
    );
    throwOnError(res, 'Ошибка сохранения рейда');
    const raw = res.data as { data?: RaidItem } | RaidItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: RaidItem }).data!;
    return raw as RaidItem;
  },

  /** Удалить рейд. Требуется право udaliat-reidy. */
  async deleteGuildRaid(guildId: number, raidId: number): Promise<void> {
    const res = await http.fetchDelete(`/guilds/${guildId}/raids/${raidId}`);
    throwOnError(res, 'Ошибка удаления рейда');
  },

  /** Голосования пользователя: активные + закрытые не более 3 дней назад (GET /user/polls). */
  async getUserPolls(gameId?: number | null): Promise<UserPollItem[]> {
    const params = gameId != null && gameId > 0 ? { game_id: gameId } : undefined;
    const res = await http.fetchGet<{ data: UserPollItem[] } | UserPollItem[]>(
      '/user/polls',
      params ? { params } : {}
    );
    throwOnError(res, 'Ошибка загрузки голосований');
    const raw = res.data as unknown;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw) {
      const arr = (raw as { data?: unknown }).data;
      return Array.isArray(arr) ? arr as UserPollItem[] : [];
    }
    return Array.isArray(raw) ? (raw as UserPollItem[]) : [];
  },

  /** Голосования гильдии (GET /guilds/:id/polls). Только для участников гильдии. */
  async getGuildPolls(guildId: number): Promise<GuildPollItem[]> {
    const res = await http.fetchGet<{ data: GuildPollItem[] } | GuildPollItem[]>(`/guilds/${guildId}/polls`);
    throwOnError(res, 'Ошибка загрузки голосований');
    const raw = res.data as { data?: GuildPollItem[] } | GuildPollItem[] | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildPollItem[] }).data ?? [];
    return Array.isArray(raw) ? raw : [];
  },

  /** Одно голосование (GET /guilds/:id/polls/:pollId). */
  async getGuildPoll(guildId: number, pollId: number): Promise<GuildPollItem> {
    const res = await http.fetchGet<{ data: GuildPollItem } | GuildPollItem>(
      `/guilds/${guildId}/polls/${pollId}`
    );
    throwOnError(res, 'Ошибка загрузки голосования');
    const raw = res.data as { data?: GuildPollItem } | GuildPollItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildPollItem }).data!;
    return raw as GuildPollItem;
  },

  /** Создать голосование. Требуется право dobavliat-gollosovanie. */
  async createGuildPoll(guildId: number, payload: CreateGuildPollPayload): Promise<GuildPollItem> {
    const res = await http.fetchPost<{ data: GuildPollItem } | GuildPollItem>(
      `/guilds/${guildId}/polls`,
      payload
    );
    throwOnError(res, 'Ошибка создания голосования');
    const raw = res.data as { data?: GuildPollItem } | GuildPollItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildPollItem }).data!;
    return raw as GuildPollItem;
  },

  /** Обновить голосование. Требуется право redaktirovat-gollosovanie. */
  async updateGuildPoll(
    guildId: number,
    pollId: number,
    payload: UpdateGuildPollPayload
  ): Promise<GuildPollItem> {
    const res = await http.fetchPut<{ data: GuildPollItem } | GuildPollItem>(
      `/guilds/${guildId}/polls/${pollId}`,
      payload
    );
    throwOnError(res, 'Ошибка сохранения голосования');
    const raw = res.data as { data?: GuildPollItem } | GuildPollItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildPollItem }).data!;
    return raw as GuildPollItem;
  },

  /** Удалить голосование. Требуется право udaliat-gollosovanie. */
  async deleteGuildPoll(guildId: number, pollId: number): Promise<void> {
    const res = await http.fetchDelete(`/guilds/${guildId}/polls/${pollId}`);
    throwOnError(res, 'Ошибка удаления голосования');
  },

  /** Закрыть голосование. Требуется право zakryvat-gollosovanie. */
  async closeGuildPoll(guildId: number, pollId: number): Promise<GuildPollItem> {
    const res = await http.fetchPost<{ data: GuildPollItem } | GuildPollItem>(
      `/guilds/${guildId}/polls/${pollId}/close`,
      {}
    );
    throwOnError(res, 'Ошибка закрытия голосования');
    const raw = res.data as { data?: GuildPollItem } | GuildPollItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildPollItem }).data!;
    return raw as GuildPollItem;
  },

  /** Сбросить голосование (удалить все голоса). Требуется право sbrasyvat-gollosovanie. */
  async resetGuildPoll(guildId: number, pollId: number): Promise<GuildPollItem> {
    const res = await http.fetchPost<{ data: GuildPollItem } | GuildPollItem>(
      `/guilds/${guildId}/polls/${pollId}/reset`,
      {}
    );
    throwOnError(res, 'Ошибка сброса голосования');
    const raw = res.data as { data?: GuildPollItem } | GuildPollItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildPollItem }).data!;
    return raw as GuildPollItem;
  },

  /** Проголосовать. Требуется быть участником гильдии. */
  async voteGuildPoll(
    guildId: number,
    pollId: number,
    optionId: number,
    characterId: number
  ): Promise<void> {
    const res = await http.fetchPost(`/guilds/${guildId}/polls/${pollId}/vote`, {
      option_id: optionId,
      character_id: characterId,
    });
    throwOnError(res, 'Ошибка голосования');
  },

  /** Отозвать голос. */
  async withdrawGuildPollVote(
    guildId: number,
    pollId: number,
    characterId: number
  ): Promise<void> {
    const res = await http.fetchDeleteWithBody(
      `/guilds/${guildId}/polls/${pollId}/vote`,
      { character_id: characterId }
    );
    throwOnError(res, 'Ошибка отзыва голоса');
  },

  // --- Админка: все голосования ---
  async getAdminPolls(params?: {
    page?: number;
    per_page?: number;
    guild_id?: number;
  }): Promise<{
    data: AdminPollItem[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
  }> {
    const qs = new URLSearchParams();
    if (params?.page != null) qs.set('page', String(params.page));
    if (params?.per_page != null) qs.set('per_page', String(params.per_page));
    if (params?.guild_id != null) qs.set('guild_id', String(params.guild_id));
    const url = `/admin/polls${qs.toString() ? `?${qs}` : ''}`;
    const res = await http.fetchGet<{
      data: AdminPollItem[];
      meta: { current_page: number; last_page: number; per_page: number; total: number };
    }>(url);
    throwOnError(res, 'Ошибка загрузки голосований');
    const body = res.data;
    const list =
      body && typeof body === 'object' && 'data' in body && Array.isArray(body.data)
        ? body.data
        : [];
    const meta =
      body && typeof body === 'object' && body.meta
        ? body.meta
        : { current_page: 1, last_page: 1, per_page: 20, total: 0 };
    return { data: list, meta };
  },

  async deleteAdminPoll(pollId: number, reason: string): Promise<void> {
    const res = await http.fetchDeleteWithBody(`/admin/polls/${pollId}`, { reason });
    throwOnError(res, 'Ошибка удаления голосования');
  },

  /** Установить состав рейда (участники и слоты). PUT /guilds/:id/raids/:raidId/composition */
  async setRaidComposition(
    guildId: number,
    raidId: number,
    members: RaidCompositionMemberPayload[]
  ): Promise<RaidItem> {
    const res = await http.fetchPut<{ data: RaidItem } | RaidItem>(
      `/guilds/${guildId}/raids/${raidId}/composition`,
      { members }
    );
    throwOnError(res, 'Ошибка сохранения состава рейда');
    const raw = res.data as { data?: RaidItem } | RaidItem | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: RaidItem }).data!;
    return raw as RaidItem;
  },
};

/** Вариант ответа голосования. */
export interface GuildPollOptionItem {
  id: number;
  text: string;
  sort_order: number;
  votes_count: number;
  /** Список проголосовавших (только для открытых голосований). */
  voters?: { character_id: number; name: string }[];
}

/** Голосование гильдии. */
export interface GuildPollItem {
  id: number;
  guild_id: number;
  title: string;
  description: string | null;
  is_anonymous: boolean;
  is_closed: boolean;
  ends_at: string | null;
  created_by: number | null;
  created_by_character_id: number | null;
  creator_character?: { id: number; name: string } | null;
  options: GuildPollOptionItem[];
  total_votes: number;
  my_vote_option_id: number | null;
  my_vote_character_id: number | null;
  created_at: string | null;
  updated_at: string | null;
}

/** Голосование для виджета пользователя (с данными гильдии и персонажами). */
export interface UserPollItem extends GuildPollItem {
  guild?: { id: number; name: string } | null;
  my_characters?: { id: number; name: string }[];
}

/** Голосование в админке (без my_vote, с guild). */
export interface AdminPollItem extends Omit<GuildPollItem, 'my_vote_option_id' | 'my_vote_character_id'> {
  guild?: { id: number; name: string } | null;
}

export interface CreateGuildPollPayload {
  title: string;
  description?: string | null;
  /** По умолчанию true — анонимное. false — открытое, видны проголосовавшие. */
  is_anonymous?: boolean;
  /** ISO 8601 дата и время окончания (опционально). */
  ends_at?: string | null;
  options: string[];
  created_by_character_id?: number | null;
}

export interface UpdateGuildPollPayload {
  title: string;
  description?: string | null;
  is_anonymous?: boolean;
  ends_at?: string | null;
  options: string[];
}

/** Участник рейда (персонаж). */
export interface RaidMemberItem {
  character_id: number;
  name: string;
  role?: string | null;
  accepted_at?: string | null;
  /** Индекс ячейки в сетке рейда (0-based). null = не назначен в ячейку. */
  slot_index?: number | null;
}

/** Элемент для обновления состава рейда. */
export interface RaidCompositionMemberPayload {
  character_id: number;
  slot_index: number | null;
}

/** Рейд (дерево: children). */
export interface RaidItem {
  id: number;
  guild_id: number;
  parent_id: number | null;
  leader_character_id: number | null;
  created_by: number | null;
  name: string;
  description: string | null;
  sort_order: number;
  leader?: { id: number; name: string } | null;
  parent?: { id: number; name: string } | null;
  creator?: { id: number; name: string } | null;
  children?: RaidItem[];
  /** Количество участников рейда (в списке дерева). */
  members_count?: number;
  /** Участники рейда (загружаются при запросе одного рейда GET /guilds/:id/raids/:raidId). */
  members?: RaidMemberItem[];
  created_at?: string | null;
  updated_at?: string | null;
}

export interface CreateRaidPayload {
  name: string;
  description?: string | null;
  parent_id?: number | null;
  leader_character_id?: number | null;
  sort_order?: number | null;
}

export interface UpdateRaidPayload {
  name?: string;
  description?: string | null;
  parent_id?: number | null;
  leader_character_id?: number | null;
  sort_order?: number | null;
}
