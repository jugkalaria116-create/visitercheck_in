const dt = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
const tm = new Intl.DateTimeFormat(undefined, { timeStyle: 'medium' });

export const fmtDateTime = (iso) => (iso ? dt.format(new Date(iso)) : '—');
export const fmtTime = (iso) => (iso ? tm.format(new Date(iso)) : '—');

export function fmtDuration(seconds) {
  if (seconds == null) return '—';
  if (seconds < 60) return `${seconds}s`;
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return s ? `${m}m ${s}s` : `${m} min`;
}

export function fmtAgo(seconds) {
  if (seconds == null) return 'never';
  if (seconds < 5) return 'just now';
  if (seconds < 60) return `${seconds}s ago`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} h ago`;
  return `${Math.floor(seconds / 86400)} d ago`;
}
