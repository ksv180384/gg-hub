import type { GuildRosterMember } from '@/shared/api/guildsApi';
import { isRosterCommonTag, rosterTagDisplayRows } from '@/shared/lib/rosterTagDisplay';

function sanitizeFileBaseName(name: string, maxLen = 80): string {
  const trimmed = name.trim().slice(0, maxLen);
  const cleaned = trimmed.replace(/[/\\?%*:|"<>]/g, '_').replace(/\s+/g, ' ').trim();
  return cleaned || 'guild';
}

function formatYmd(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function memberCommonTags(m: GuildRosterMember): string {
  return rosterTagDisplayRows(m)
    .filter((r) => r.source === 'personal' && isRosterCommonTag(r.tag))
    .map((r) => r.tag.name)
    .join(', ');
}

function memberGuildTags(m: GuildRosterMember): string {
  return rosterTagDisplayRows(m)
    .filter((r) => r.source === 'guild')
    .map((r) => r.tag.name)
    .join(', ');
}

/**
 * Собирает .xlsx со списком участников состава гильдии и инициирует скачивание в браузере.
 * Динамически подгружает exceljs, чтобы не раздувать initial bundle.
 */
export async function exportGuildRosterToXlsx(params: {
  guildName: string;
  members: GuildRosterMember[];
}): Promise<void> {
  const ExcelJS = (await import('exceljs')).default;
  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet('Состав');

  sheet.columns = [
    { header: 'Имя', key: 'name', width: 24 },
    { header: 'Роль', key: 'role', width: 18 },
    { header: 'Классы', key: 'classes', width: 28 },
    { header: 'Общие теги', key: 'commonTags', width: 32 },
    { header: 'Теги гильдии', key: 'guildTags', width: 32 },
  ];

  for (const m of params.members) {
    sheet.addRow({
      name: m.name,
      role: m.guild_role?.name ?? '',
      classes: (m.game_classes ?? []).map((gc) => gc.name_ru ?? gc.name).join(', '),
      commonTags: memberCommonTags(m),
      guildTags: memberGuildTags(m),
    });
  }

  sheet.getRow(1).font = { bold: true };
  sheet.views = [{ state: 'frozen', ySplit: 1 }];

  const buffer = await workbook.xlsx.writeBuffer();
  const base = sanitizeFileBaseName(params.guildName);
  const filename = `${base}_roster_${formatYmd(new Date())}.xlsx`;

  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.rel = 'noopener';
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

