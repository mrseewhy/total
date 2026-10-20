export function escapeCsvCell(value: string): string {
  const text = /^(?:\s*[=+@\-＝＋＠－]|[\t\r\n])/u.test(value)
    ? "'" + value
    : value;
  return `"${text.replaceAll('"', '""')}"`;
}
export function exportCsv(filename: string, rows: string[][]) {
  const csv =
    "\uFEFF" + rows.map((row) => row.map(escapeCsvCell).join(",")).join("\r\n");
  const url = URL.createObjectURL(
    new Blob([csv], { type: "text/csv;charset=utf-8;" }),
  );
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
