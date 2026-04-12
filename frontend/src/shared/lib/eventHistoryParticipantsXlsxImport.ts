/**
 * Чтение списка ников из .xlsx (первый лист, первый столбец, как при экспорте).
 */
export async function parseParticipantNicknamesFromXlsxFile(file: File): Promise<string[]> {
  const ExcelJS = (await import('exceljs')).default;
  const workbook = new ExcelJS.Workbook();
  await workbook.xlsx.load(await file.arrayBuffer());
  const sheet = workbook.worksheets[0];
  if (!sheet) {
    return [];
  }
  const out: string[] = [];
  sheet.eachRow((row) => {
    const raw = row.getCell(1).text?.trim() ?? '';
    if (raw) {
      out.push(raw);
    }
  });
  return out;
}
