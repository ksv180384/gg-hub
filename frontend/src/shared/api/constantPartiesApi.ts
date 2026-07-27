import type { Character } from '@/shared/api/charactersApi';
import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export type ConstantPartyMember = {
  id: number;
  constant_party_id: number;
  character_id: number;
  role: 'leader' | 'member';
  can_manage_storage: boolean;
  joined_at: string | null;
  character?: Character;
};

export type ConstantParty = {
  id: number;
  name: string;
  leader_character_id: number;
  game_id: number;
  localization_id: number;
  server_id: number;
  leader?: Character;
  game?: { id: number; name: string };
  localization?: { id: number; name: string };
  server?: { id: number; name: string };
  members?: ConstantPartyMember[];
  members_count?: number;
  my_member?: {
    id: number;
    character_id: number;
    role: 'leader' | 'member';
    can_manage_storage: boolean;
  } | null;
  created_at?: string;
  updated_at?: string;
};

export type ConstantPartyInvitation = {
  id: number;
  constant_party_id: number;
  invited_character_id: number;
  invited_by_character_id: number;
  status: 'pending' | 'accepted' | 'declined' | 'revoked' | 'expired';
  message: string | null;
  constant_party?: ConstantParty;
  invited_character?: Character;
  invited_by_character?: Character;
  created_at?: string;
};

export type ConstantPartyChatMessage = {
  id: number;
  constant_party_id: number;
  character_id: number;
  body: string;
  character?: Character;
  created_at: string;
};

export type ConstantPartyStorageTier = {
  id: number;
  constant_party_id: number;
  name: string;
  color: string | null;
  sort_order: number;
  items_count?: number;
};

export type ConstantPartyStorageItem = {
  id: number;
  constant_party_id: number;
  tier_id: number | null;
  name: string;
  description: string | null;
  quantity: number | null;
  tier?: ConstantPartyStorageTier | null;
  grants_count?: number;
};

export type ConstantPartyStorageGrant = {
  id: number;
  constant_party_id: number;
  item_id: number;
  received_by_character_id: number;
  granted_by_character_id: number;
  reason: string | null;
  granted_at: string | null;
  item?: ConstantPartyStorageItem;
  received_by_character?: Character;
  granted_by_character?: Character;
};

function unwrap<T>(res: { data: unknown }): T {
  const raw = res.data as { data?: T } | T | null;
  if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: T }).data!;
  return raw as T;
}

export const constantPartiesApi = {
  async list(): Promise<{ parties: ConstantParty[]; invitations: ConstantPartyInvitation[] }> {
    const res = await http.fetchGet<{ data: ConstantParty[]; invitations: ConstantPartyInvitation[] }>('/constant-parties');
    throwOnError(res, 'Не удалось загрузить конст пати.');
    return {
      parties: res.data?.data ?? [],
      invitations: res.data?.invitations ?? [],
    };
  },

  async create(payload: { name: string; leader_character_id: number }): Promise<ConstantParty> {
    const res = await http.fetchPost<ConstantParty>('/constant-parties', payload);
    throwOnError(res, 'Не удалось создать конст пати.');
    return unwrap<ConstantParty>(res as { data: unknown });
  },

  async get(id: number): Promise<ConstantParty> {
    const res = await http.fetchGet<{ data: ConstantParty }>(`/constant-parties/${id}`);
    throwOnError(res, 'Не удалось загрузить конст пати.');
    return res.data?.data ?? ({} as ConstantParty);
  },

  async invite(partyId: number, payload: { character_id: number; message?: string | null }): Promise<ConstantPartyInvitation> {
    const res = await http.fetchPost<ConstantPartyInvitation>(`/constant-parties/${partyId}/invitations`, payload);
    throwOnError(res, 'Не удалось отправить приглашение.');
    return unwrap<ConstantPartyInvitation>(res as { data: unknown });
  },

  async searchInviteCandidates(partyId: number, query: string): Promise<Character[]> {
    const params = new URLSearchParams({ query });
    const res = await http.fetchGet<{ data: Character[] }>(
      `/constant-parties/${partyId}/invitations/candidates?${params.toString()}`,
    );
    throwOnError(res, 'Не удалось найти персонажей.');
    return res.data?.data ?? [];
  },

  async listInvitations(partyId: number): Promise<ConstantPartyInvitation[]> {
    const res = await http.fetchGet<{ data: ConstantPartyInvitation[] }>(`/constant-parties/${partyId}/invitations`);
    throwOnError(res, 'Не удалось загрузить приглашения.');
    return res.data?.data ?? [];
  },

  async acceptInvitation(invitationId: number): Promise<ConstantPartyInvitation> {
    const res = await http.fetchPost<ConstantPartyInvitation>(`/constant-parties/invitations/${invitationId}/accept`, {});
    throwOnError(res, 'Не удалось принять приглашение.');
    return unwrap<ConstantPartyInvitation>(res as { data: unknown });
  },

  async declineInvitation(invitationId: number): Promise<ConstantPartyInvitation> {
    const res = await http.fetchPost<ConstantPartyInvitation>(`/constant-parties/invitations/${invitationId}/decline`, {});
    throwOnError(res, 'Не удалось отклонить приглашение.');
    return unwrap<ConstantPartyInvitation>(res as { data: unknown });
  },

  async updateMember(partyId: number, memberId: number, payload: { can_manage_storage: boolean }): Promise<ConstantPartyMember> {
    const res = await http.fetchFull<{ data: ConstantPartyMember }>({
      url: `/constant-parties/${partyId}/members/${memberId}`,
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    throwOnError(res, 'Не удалось обновить права участника.');
    return res.data?.data ?? ({} as ConstantPartyMember);
  },

  async deleteMember(partyId: number, memberId: number): Promise<void> {
    const res = await http.fetchDelete(`/constant-parties/${partyId}/members/${memberId}`);
    throwOnError(res, 'Не удалось исключить участника.');
  },

  async listMessages(partyId: number): Promise<ConstantPartyChatMessage[]> {
    const res = await http.fetchGet<{ data: ConstantPartyChatMessage[] }>(`/constant-parties/${partyId}/chat/messages`);
    throwOnError(res, 'Не удалось загрузить чат.');
    return res.data?.data ?? [];
  },

  async sendMessage(partyId: number, payload: { character_id: number; body: string }): Promise<ConstantPartyChatMessage> {
    const res = await http.fetchPost<ConstantPartyChatMessage>(`/constant-parties/${partyId}/chat/messages`, payload);
    throwOnError(res, 'Не удалось отправить сообщение.');
    return unwrap<ConstantPartyChatMessage>(res as { data: unknown });
  },

  async storageContext(partyId: number): Promise<{ can_manage_storage: boolean; my_member_id: number; my_character_id: number }> {
    const res = await http.fetchGet<{ data: { can_manage_storage: boolean; my_member_id: number; my_character_id: number } }>(
      `/constant-parties/${partyId}/storage/context`,
    );
    throwOnError(res, 'Не удалось загрузить контекст хранилища.');
    return res.data?.data ?? { can_manage_storage: false, my_member_id: 0, my_character_id: 0 };
  },

  async listStorageItems(partyId: number): Promise<ConstantPartyStorageItem[]> {
    const res = await http.fetchGet<{ data: ConstantPartyStorageItem[] }>(`/constant-parties/${partyId}/storage/items`);
    throwOnError(res, 'Не удалось загрузить хранилище.');
    return res.data?.data ?? [];
  },

  async createStorageItem(partyId: number, payload: {
    name: string;
    description?: string | null;
    quantity?: number | null;
    tier_id?: number | null;
    actor_character_id: number;
  }): Promise<ConstantPartyStorageItem> {
    const res = await http.fetchPost<ConstantPartyStorageItem>(`/constant-parties/${partyId}/storage/items`, payload);
    throwOnError(res, 'Не удалось добавить предмет.');
    return unwrap<ConstantPartyStorageItem>(res as { data: unknown });
  },

  async createGrant(partyId: number, payload: {
    item_id: number;
    received_by_character_id: number;
    granted_by_character_id: number;
    reason?: string | null;
  }): Promise<ConstantPartyStorageGrant> {
    const res = await http.fetchPost<ConstantPartyStorageGrant>(`/constant-parties/${partyId}/storage/grants`, payload);
    throwOnError(res, 'Не удалось выдать предмет.');
    return unwrap<ConstantPartyStorageGrant>(res as { data: unknown });
  },

  async listItemGrants(partyId: number, itemId: number): Promise<ConstantPartyStorageGrant[]> {
    const res = await http.fetchGet<{ data: ConstantPartyStorageGrant[] }>(
      `/constant-parties/${partyId}/storage/items/${itemId}/grants`,
    );
    throwOnError(res, 'Не удалось загрузить историю выдачи.');
    return res.data?.data ?? [];
  },
};
