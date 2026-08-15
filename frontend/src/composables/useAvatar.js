// composables/useAvatar.js
//
// ─── Shared "person avatar" helpers ────────────────────────────────────────────
//
// Centralises the initials + deterministic-color logic that used to be
// hand-rolled (slightly differently) in ~14 components. Accepts either a
// plain name string ("John Doe") or a { first_name, last_name } object, so
// existing call sites don't need to change how they call these — only where
// the logic lives.

// Default palettes — kept distinct since they're used in different contexts
// (Vuetify theme color names vs raw hex for places that need a literal color).
export const AVATAR_THEME_PALETTE = [
  'brown-darken-2',
  'blue-darken-2',
  'teal-darken-2',
  'purple-darken-2',
  'orange-darken-2',
  'green-darken-2'
]

export const AVATAR_HEX_PALETTE = [
  '#3b5bdb',
  '#2f9e44',
  '#e67700',
  '#c92a2a',
  '#0c8599',
  '#6741d9'
]

export function useAvatar() {
  // "John Doe" -> "JD" ; { first_name: 'John', last_name: 'Doe' } -> "JD"
  const getInitials = (source, fallback = '?') => {
    if (!source) return fallback

    if (typeof source === 'string') {
      const initials = source
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .map(word => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
      return initials || fallback
    }

    const initials =
      `${source.first_name?.[0] ?? ''}${source.last_name?.[0] ?? ''}`.toUpperCase()
    return initials || fallback
  }

  // Deterministic color from a fixed palette, keyed off a name/string or
  // { first_name } object — the same person always gets the same color.
  const getAvatarColor = (
    source,
    { palette = AVATAR_THEME_PALETTE, fallback = 'grey' } = {}
  ) => {
    const seed =
      typeof source === 'string' ? source : (source?.first_name ?? source?.name)
    if (!seed) return fallback
    return palette[seed.charCodeAt(0) % palette.length] ?? fallback
  }

  return { getInitials, getAvatarColor }
}
