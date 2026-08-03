const ENABLED_VALUES = new Set(['1', 'true', 'yes', 'on']);

export function isFeatureEnabled(name) {
  return ENABLED_VALUES.has(String(process.env[name] ?? '').trim().toLowerCase());
}
