import http from './api'

/**
 * login
 * @param {string} email
 * @param {string} password
 * @param {route name get from laravel base one api url} /v1/auth/login
 * @returns
 */
export default {
  userLogin(email, password) {
    return http.post('/login', {
      email: email, // must match Laravel field
      password: password // must match Laravel field
    })
  },

  userLogout(refreshToken) {
    return http.post('/logout', { refresh_token: refreshToken })
  },

  me() {
    return http.get('/me')
  },

  verifyTwoFactor(twoFactorToken, code) {
    return http.post('/two-factor/verify', { two_factor_token: twoFactorToken, code })
  },

  register(payload) {
    return http.post('/v1/public/business-register', payload)
  },

  forgotPassword(email) {
    return http.post('/forgot-password', { email })
  },

  resetPassword({ token, email, password, password_confirmation }) {
    return http.post('/reset-password', { token, email, password, password_confirmation })
  },

  refresh(refreshToken) {
    return http.post('/refresh', { refresh_token: refreshToken })
  },

  changePassword(current_password, new_password, new_password_confirmation) {
    return http.put('/v1/auth/password', {
      current_password,
      new_password,
      new_password_confirmation
    })
  },

  updateEmail(email, current_password) {
    return http.put('/v1/auth/email', { email, current_password })
  }
}
