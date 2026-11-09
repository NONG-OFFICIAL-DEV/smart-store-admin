import http from './api'

// Public — no auth, callable before the device is paired.
export const requestCustomerDisplayPairingApi = () =>
  http.post('/v1/public/customer-display/pairing')

export const customerDisplayBroadcastingAuthApi = (socketId, channelName, deviceToken) =>
  http.post('/v1/public/customer-display/broadcasting-auth', {
    socket_id: socketId,
    channel_name: channelName,
    device_token: deviceToken
  })

// Authenticated — POS Settings > Customer display. branchId is the POS's
// own active branch (authStore.activeBranchId) — same convention every
// other POS submit call already uses, and the backend verifies it
// actually belongs to the caller's tenant before using it.
export const claimCustomerDisplayPairingApi = (code, deviceName, branchId) =>
  http.post('/v1/customer-display/pairing/claim', {
    code,
    device_name: deviceName,
    branch_id: branchId
  })

export const getCustomerDisplayDevicesApi = branchId =>
  http.get('/v1/customer-display/devices', { params: { branch_id: branchId } })

export const renameCustomerDisplayDeviceApi = (id, deviceName) =>
  http.put(`/v1/customer-display/devices/${id}`, { device_name: deviceName })

export const unpairCustomerDisplayDeviceApi = id =>
  http.delete(`/v1/customer-display/devices/${id}`)

// Authenticated — FoodPos.vue/RetailPos.vue push the full state here on
// every cart change when a tablet is paired for the active branch.
export const pushCustomerDisplayStateApi = (deviceId, state) =>
  http.post(`/v1/customer-display/${deviceId}/state`, { state })
