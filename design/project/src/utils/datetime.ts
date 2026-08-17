const ADMIN_TIME_ZONE = import.meta.env.VITE_ADMIN_TIMEZONE || 'Asia/Dhaka';

const adminDateTimeFormatter = new Intl.DateTimeFormat('en-US', {
  timeZone: ADMIN_TIME_ZONE,
  month: 'short',
  day: 'numeric',
  year: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
  second: '2-digit',
});

export function formatAdminDateTime(value?: string | Date | null): string {
  if (!value) return '-';
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return '-';

  return `${adminDateTimeFormatter.format(date)} ${ADMIN_TIME_ZONE}`;
}
