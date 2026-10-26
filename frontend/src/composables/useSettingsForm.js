import { reactive, ref, computed, toRaw } from 'vue'

// structuredClone() throws DataCloneError on a Vue reactive Proxy (and
// ref(object) auto-wraps its value reactively too) — toRaw() unwraps back
// to the plain target first. Vue only wraps nested plain objects lazily on
// property access rather than mutating them in place, so the raw target is
// already deeply plain — one toRaw() at the top is enough.
function deepClone(value) {
  const raw = toRaw(value)
  return typeof structuredClone === 'function' ? structuredClone(raw) : JSON.parse(JSON.stringify(raw))
}

// Primitive-only arrays (order_types, payment_methods, ...) are really sets
// — toggling a checkbox off then back on re-appends it via push/splice, so
// a plain ordered deep-equal would still report "dirty" after an undo.
// Sorting makes the compare order-insensitive for exactly those arrays,
// while leaving arrays of objects (if any ever appear) untouched.
export function normalize(value) {
  if (Array.isArray(value)) {
    const normalized = value.map(normalize)
    const allPrimitive = normalized.every(v => v === null || typeof v !== 'object')
    return allPrimitive ? [...normalized].sort() : normalized
  }
  if (value && typeof value === 'object') {
    return Object.keys(value)
      .sort()
      .reduce((acc, key) => {
        acc[key] = normalize(value[key])
        return acc
      }, {})
  }
  return value
}

export function isEqual(a, b) {
  return JSON.stringify(normalize(a)) === JSON.stringify(normalize(b))
}

// One shared reactive `form` + an `original` baseline for a settings-style
// page with a single combined Save bar. load() replaces both atomically (no
// path can update one without the other), discard() reverts form back to
// original, commit() re-baselines original to form's current values after a
// successful save. isDirty is a true structural compare, not a reference or
// shallow check, so it survives nested/array edits and reports clean again
// once a value is set back to what it was.
export function useSettingsForm(defaults) {
  const original = ref(deepClone(defaults))
  const form = reactive(deepClone(defaults))

  function load(data) {
    original.value = deepClone(data)
    Object.assign(form, deepClone(data))
  }

  function discard() {
    Object.assign(form, deepClone(original.value))
  }

  function commit() {
    original.value = deepClone(form)
  }

  const isDirty = computed(() => !isEqual(form, original.value))

  return { form, original, isDirty, load, discard, commit }
}
