// Shared schedule/attendance time helpers — fixed to Asia/Phnom_Penh since no
// branch/tenant timezone column exists in the schema (see CLAUDE.md). Used by
// StaffManagement.vue's summary cards and every Schedule-tab component so
// "now"/"today"/lateness are computed identically everywhere.

const PHNOM_PENH_TZ = 'Asia/Phnom_Penh'
// Cambodia has a fixed UTC+7 offset year-round (no DST) — safe to hardcode
// when building an absolute instant from a "YYYY-MM-DD"/"HH:mm" wall-clock
// pair that's meant to represent Phnom Penh local time.
const PHNOM_PENH_OFFSET = '+07:00'

export function nowInPhnomPenh() {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: PHNOM_PENH_TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  }).formatToParts(new Date())
  const map = Object.fromEntries(parts.map(p => [p.type, p.value]))
  const hour = map.hour === '24' ? '00' : map.hour
  return { dateStr: `${map.year}-${map.month}-${map.day}`, time: `${hour}:${map.minute}` }
}

export function toDateStr(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export function addDays(dateStr, n) {
  const d = new Date(`${dateStr}T00:00:00`)
  d.setDate(d.getDate() + n)
  return toDateStr(d)
}

// Monday–Sunday week containing the given "YYYY-MM-DD" date string (defaults
// to today in Phnom Penh).
export function weekRangeFor(dateStr = nowInPhnomPenh().dateStr) {
  const d = new Date(`${dateStr}T00:00:00`)
  const dow = d.getDay() // 0=Sun..6=Sat
  const mondayOffset = dow === 0 ? -6 : 1 - dow
  const monday = new Date(d)
  monday.setDate(d.getDate() + mondayOffset)
  const days = Array.from({ length: 7 }, (_, i) => {
    const day = new Date(monday)
    day.setDate(monday.getDate() + i)
    return toDateStr(day)
  })
  return { from: days[0], to: days[6], days }
}

function shiftWindow(a) {
  const start = a.shift?.start_time?.slice(0, 5)
  const end = a.shift?.end_time?.slice(0, 5)
  return { start, end }
}

export function isWithinShiftWindow(a, now) {
  if (a.shift_date !== now.dateStr) return false
  const { start, end } = shiftWindow(a)
  if (!start || !end) return false
  // Overnight shift (end < start) spans past midnight — "now" is inside the
  // window if it's after start OR before end, not strictly between them.
  return start <= end ? now.time >= start && now.time <= end : now.time >= start || now.time <= end
}

// Mirrors StaffShift's own derived status accessor (scheduled/clocked_in/
// completed/absent) — re-derived client-side since StaffShiftResource
// doesn't expose it yet.
export function deriveStatus(a, now = nowInPhnomPenh()) {
  if (a.actual_end) return 'completed'
  if (a.actual_start) return 'clocked_in'
  const { start } = shiftWindow(a)
  if (`${a.shift_date} ${start ?? '00:00'}` < `${now.dateStr} ${now.time}`) return 'absent'
  return 'scheduled'
}

// Minutes actual_start landed after the scheduled start, clamped to >= 0.
export function minutesLate(a) {
  const { start } = shiftWindow(a)
  if (!a.actual_start || !start) return 0
  const scheduled = new Date(`${a.shift_date}T${start}:00${PHNOM_PENH_OFFSET}`)
  const actual = new Date(a.actual_start)
  return Math.max(0, Math.round((actual - scheduled) / 60000))
}

export function assignmentDurationMinutes(a) {
  const { start, end } = shiftWindow(a)
  if (!start || !end) return 0
  const [sh, sm] = start.split(':').map(Number)
  const [eh, em] = end.split(':').map(Number)
  let mins = eh * 60 + em - (sh * 60 + sm)
  if (mins <= 0) mins += 24 * 60
  mins -= a.shift?.break_minutes ?? 0
  return Math.max(0, mins)
}

export function formatHoursMinutes(totalMinutes) {
  const h = Math.floor(totalMinutes / 60)
  const m = totalMinutes % 60
  return `${h}h ${String(m).padStart(2, '0')}m`
}

// Wall-clock "YYYY-MM-DD HH:mm" strings (no timezone) for v-calendar's event
// start/end. v-calendar's own day grid is built from literal date strings
// with no timezone conversion, but if we instead hand it a real tz-aware
// Date instant (e.g. built via the +07:00 offset), v-calendar re-reads it
// back out with Date.getHours()/getDate() in the BROWSER's local timezone —
// on any machine not already set to Asia/Phnom_Penh, that silently shifts
// shifts onto the wrong calendar day (and can push them clean out of the
// visible week), which is why the calendar appeared to show nothing. Plain
// wall-clock strings sidestep that conversion entirely.
export function calendarEventRange(a) {
  const { start, end } = shiftWindow(a)
  if (!start || !end) return null
  const endDateStr = end < start ? addDays(a.shift_date, 1) : a.shift_date
  return {
    start: `${a.shift_date} ${start}`,
    end: `${endDateStr} ${end}`
  }
}
