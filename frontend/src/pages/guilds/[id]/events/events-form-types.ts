export type EventsFormTabId = 'information' | 'participants' | 'screenshots';

export const EVENTS_FORM_TABS: { id: EventsFormTabId; label: string }[] = [
  { id: 'information', label: 'Информация' },
  { id: 'participants', label: 'Участники' },
  { id: 'screenshots', label: 'Скриншоты' },
];

export type EventsFormParticipant = {
  character_id?: number | null;
  external_name?: string | null;
  dkp_coefficient?: number;
  dkp_points_override?: number | null;
};
