import { useI18n } from 'vue-i18n'

// ── Shared password policy used by User/Tenant-owner/Staff create forms and
// the self-service change-password form — keeps generation + validation in
// sync with the backend's PasswordPolicy::rules() (min 8, upper+lower+digit).
export function usePasswordPolicy() {
  const { t } = useI18n()

  const generate = (length = 12) => {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ'
    const lower = 'abcdefghijkmnpqrstuvwxyz'
    const digits = '23456789'
    const all = upper + lower + digits

    let password =
      upper[Math.floor(Math.random() * upper.length)] +
      lower[Math.floor(Math.random() * lower.length)] +
      digits[Math.floor(Math.random() * digits.length)]

    for (let i = password.length; i < length; i++) {
      password += all[Math.floor(Math.random() * all.length)]
    }

    return password
      .split('')
      .sort(() => Math.random() - 0.5)
      .join('')
  }

  const rules = [
    v => !!v || t('validation.required'),
    v => !v || v.length >= 8 || t('validation.min_length', { n: 8 }),
    v => !v || /[a-z]/.test(v) && /[A-Z]/.test(v) && /\d/.test(v) || t('validation.password_complexity')
  ]

  return { generate, rules }
}
