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

  userLogout() {
    return http.post('/logout') // call your backend logout endpoint
  },

  me() {
    return http.get('/me')
  },

  /** PIN login — branch_id is optional */
  loginByPin(pin_code, branch_id = null) {
    return http.post('/login-pin', { pin_code, branch_id })
  },

  refresh() {
    return http.post('/refresh')
  },

  changePassword(current_password, new_password, new_password_confirmation) {
    return http.put('/v1/auth/password', {
      current_password,
      new_password,
      new_password_confirmation
    })
  }
}
