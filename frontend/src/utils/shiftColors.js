// Preset list of Vuetify color names a shift can be colored with — kept in
// sync with the `color` validation list in StoreShiftRequest/UpdateShiftRequest
// on the backend. Real Vuetify color tokens (not arbitrary hex), so every
// shift chip/legend entry/calendar event renders via Vuetify's own color
// prop system rather than inline styles.
export const SHIFT_COLORS = [
  { value: 'primary', label: 'Primary' },
  { value: 'secondary', label: 'Secondary' },
  { value: 'success', label: 'Green' },
  { value: 'warning', label: 'Orange' },
  { value: 'error', label: 'Red' },
  { value: 'info', label: 'Blue' },
  { value: 'purple', label: 'Purple' },
  { value: 'teal', label: 'Teal' },
  { value: 'indigo', label: 'Indigo' },
  { value: 'brown', label: 'Brown' }
]

export const DEFAULT_SHIFT_COLOR = 'primary'
