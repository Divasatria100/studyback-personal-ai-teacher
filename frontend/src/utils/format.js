/**
 * Format a numeric progress value (0–100) as an integer percentage for display.
 * Presentation-only: the raw value is never modified anywhere else, so internal
 * precision (e.g. progress rings, thresholds) stays intact.
 *
 * Invalid values (NaN, Infinity, null, undefined) fall back to 0, and the result
 * is clamped to the valid 0–100 range so the UI can never render -1% or 101%.
 *
 * @param {unknown} value
 * @returns {number}
 */
export function formatPercentage(value) {
  const num = Number(value);
  if (!Number.isFinite(num)) return 0;
  return Math.min(100, Math.max(0, Math.round(num)));
}