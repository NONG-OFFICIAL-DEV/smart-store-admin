// src/composables/useDate.js
export function useDate() {
  // Format date as "DD/MM/YYYY"
  const formatDate = (date = new Date()) => {
    const d = new Date(date)
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    return `${day}/${month}/${year}`
  }

  // Format date with time "DD/MM/YYYY HH:mm:ss"
  const formatDateTime = (date = new Date()) => {
    const d = new Date(date)
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    const seconds = String(d.getSeconds()).padStart(2, '0')
    return `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`
  }

  // Add days to a date
  const addDays = (date = new Date(), days = 0) => {
    const d = new Date(date)
    d.setDate(d.getDate() + days)
    return d
  }

  const formatLocalDate = date => {
    const d = new Date(date)
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  const formatTimeAgo = dateString => {
    if (!dateString) return ''

    const date = new Date(dateString)
    const now = new Date()
    const diffInSeconds = Math.floor((now - date) / 1000)

    // Define time intervals in seconds
    const intervals = {
      year: 31536000,
      month: 2592000,
      week: 604800,
      day: 86400,
      hour: 3600,
      minute: 60,
      second: 1
    }

    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })

    for (const [unit, secondsInUnit] of Object.entries(intervals)) {
      if (diffInSeconds >= secondsInUnit || unit === 'second') {
        const count = Math.floor(diffInSeconds / secondsInUnit)
        return rtf.format(-count, unit)
      }
    }
  }

  return {
    formatDate,
    formatDateTime,
    addDays,
    formatLocalDate,
    formatTimeAgo
  }
}
