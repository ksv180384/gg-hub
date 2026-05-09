/**
 * API банка/хранилища гильдии.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export type GuildBankItem = {
  id: number;
  guild_id: number;
  name: string;
  description: string | null;
  tier: string | null;
  color: string | null;
  dkp_cost: number | null;
  quantity: number | null;
  grants_count?: number;
  last_granted_at?: string | null;
  created_at: string;
  updated_at: string;
};

export type GuildBankGrant = {
  id: number;
  guild_id: number;
  guild_bank_item_id: number;
  received_by_character_id: number;
  granted_by_character_id: number | null;
  reason: string;
  granted_at: string;
  item?: { id: number; name: string; tier: string | null; color: string | null; dkp_cost: number | null; quantity: number | null };
  received_by_character?: { id: number; name: string };
  granted_by_character?: { id: number; name: string } | null;
  created_at: string;
  updated_at: string;
};

export type CreateGuildBankItemPayload = {
  name: string;
  description?: string | null;
  tier?: string | null;
  color?: string | null;
  dkp_cost?: number | null;
  quantity?: number | null;
};

export type UpdateGuildBankItemPayload = Partial<CreateGuildBankItemPayload>;

export type CreateGuildBankGrantPayload = {
  guild_bank_item_id: number;
  received_by_character_id: number;
  granted_by_character_id?: number | null;
  reason?: string | null;
  granted_at?: string | null;
};

export const guildBankApi = {
  unwrap<T>(res: { data: unknown }): T {
    const raw = res.data as { data?: T } | T | null;
    if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: T }).data!;
    return raw as T;
  },

  async listItems(guildId: number): Promise<GuildBankItem[]> {
    const res = await http.fetchGet<{ data: GuildBankItem[] }>(`/guilds/${guildId}/bank/items`);
    throwOnError(res, 'Не удалось загрузить предметы банка.');
    return res.data?.data ?? [];
  },

  async createItem(guildId: number, payload: CreateGuildBankItemPayload): Promise<GuildBankItem> {
    const res = await http.fetchPost<GuildBankItem>(`/guilds/${guildId}/bank/items`, payload as Record<string, unknown>);
    throwOnError(res, 'Не удалось добавить предмет.');
    return this.unwrap<GuildBankItem>(res as { data: unknown });
  },

  async updateItem(guildId: number, itemId: number, payload: UpdateGuildBankItemPayload): Promise<GuildBankItem> {
    const res = await http.fetchPut<GuildBankItem>(`/guilds/${guildId}/bank/items/${itemId}`, payload as Record<string, unknown>);
    throwOnError(res, 'Не удалось сохранить предмет.');
    return this.unwrap<GuildBankItem>(res as { data: unknown });
  },

  async deleteItem(guildId: number, itemId: number): Promise<void> {
    const res = await http.fetchDelete<unknown>(`/guilds/${guildId}/bank/items/${itemId}`);
    if (res.status >= 400) {
      throwOnError(res, 'Не удалось удалить предмет.');
    }
  },

  async listItemGrants(guildId: number, itemId: number): Promise<GuildBankGrant[]> {
    const res = await http.fetchGet<{ data: GuildBankGrant[] }>(`/guilds/${guildId}/bank/items/${itemId}/grants`);
    throwOnError(res, 'Не удалось загрузить историю выдач.');
    return res.data?.data ?? [];
  },

  async createGrant(guildId: number, payload: CreateGuildBankGrantPayload): Promise<GuildBankGrant> {
    const res = await http.fetchPost<GuildBankGrant>(`/guilds/${guildId}/bank/grants`, payload as Record<string, unknown>);
    throwOnError(res, 'Не удалось выдать предмет.');
    return this.unwrap<GuildBankGrant>(res as { data: unknown });
  },

  async revokeGrant(guildId: number, grantId: number): Promise<{ id: number }> {
    const res = await http.fetchDelete<unknown>(`/guilds/${guildId}/bank/grants/${grantId}`);
    throwOnError(res, 'Не удалось отменить выдачу.');
    return this.unwrap<{ id: number }>(res as { data: unknown });
  },

  async listMemberGrants(guildId: number, characterId: number): Promise<GuildBankGrant[]> {
    const res = await http.fetchGet<{ data: GuildBankGrant[] }>(`/guilds/${guildId}/bank/members/${characterId}/grants`);
    throwOnError(res, 'Не удалось загрузить предметы участника.');
    return res.data?.data ?? [];
  },
};

