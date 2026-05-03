import type { EventHistoryParticipantDto } from '@/shared/api/eventHistoryApi';

function sanitizeFileBaseName(name: string, maxLen = 80): string {
  const trimmed = name.trim().slice(0, maxLen);
  const cleaned = trimmed.replace(/[/\\?%*:|"<>]/g, '_').replace(/\s+/g, ' ').trim();
  return cleaned || 'событие';
}

function participantDisplayName(p: EventHistoryParticipantDto): string {
  return p.character?.name || p.external_name || '';
}

/**
 * Собирает .xlsx со списком участников истории события и инициирует скачивание в браузере.
 * Динамически подгружает exceljs, чтобы не раздувать initial bundle.
 */
export async function exportEventParticipantsToXlsx(params: {
  eventTitle: string;
  participants: EventHistoryParticipantDto[];
}): Promise<void> {
  const ExcelJS = (await import('exceljs')).default;
  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet('Участники');
  sheet.getColumn(1).width = 28;

  for (const p of params.participants) {
    sheet.addRow([participantDisplayName(p)]);
  }

  const buffer = await workbook.xlsx.writeBuffer();
  const base = sanitizeFileBaseName(params.eventTitle);
  const filename = `${base}_участники.xlsx`;

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
