import { VuetifyDateAdapter } from 'vuetify/date/adapters/vuetify'

const KHMER_MONTHS = [
  'មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា',
  'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'
]

const KHMER_DAYS = ['អា', 'ច', 'អ', 'ព', 'ព្រ', 'សុ', 'ស']

const isKhmer = locale => locale?.startsWith('km')

export class KhmerDateAdapter extends VuetifyDateAdapter {
  format(date, formatString) {
    if (!isKhmer(this.locale)) return super.format(date, formatString)

    const d = new Date(date)
    const month = d.getMonth()       // 0-11
    const year = d.getFullYear()
    const day = d.getDate()
    const weekday = d.getDay()       // 0-6

    const KHMER_DAYS_FULL = [
      'អាទិត្យ', 'ច័ន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍'
    ]

    switch (formatString) {
      case 'monthShort':
        return KHMER_MONTHS[month]

      case 'monthAndYear':
        return `${KHMER_MONTHS[month]} ${year}`

      case 'fullDateWithWeekday':
        return `${KHMER_DAYS_FULL[weekday]} ${day} ${KHMER_MONTHS[month]} ${year}`

      case 'year':
        return `${year}`

      case 'dayOfMonth':
        return `${day}`

      default:
        return super.format(date, formatString)
    }
  }

  getWeekdays() {
    if (isKhmer(this.locale)) return KHMER_DAYS
    return super.getWeekdays()
  }
}