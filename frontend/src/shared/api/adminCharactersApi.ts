import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export type AdminCharacterSort = 'name' | 'email' | 'game' | 'server';
export type SortDirection = 'asc' | 'desc';

export interface AdminCharacterDto {
  id: number;
  name: string;
  user: {
    id: number;
    email: string;
  };
  game: {
    id: number;
    name: string;
  };
  server: {
    id: number;
    name: string;
  };
}

export interface AdminCharactersMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface AdminCharactersResponse {
  data: AdminCharacterDto[];
  meta: AdminCharactersMeta;
}

export interface AdminCharacterFilters {
  name: string;
  email: string;
  game_id: number | null;
  server_id: number | null;
  sort: AdminCharacterSort;
  direction: SortDirection;
  page: number;
}

function toQueryString(filters: AdminCharacterFilters): string {
  const params = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value != null) {
      params.set(key, String(value));
    }
  });

  return params.toString();
}

export const adminCharactersApi = {
  async getCharacters(filters: AdminCharacterFilters): Promise<AdminCharactersResponse> {
    const query = toQueryString(filters);
    const response = await http.fetchGet<AdminCharactersResponse>(`/admin/characters?${query}`);

    throwOnError(response, 'Ошибка загрузки персонажей');

    return response.data!;
  },
};
