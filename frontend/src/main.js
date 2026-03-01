import { createApp } from "vue"
import { createPinia } from 'pinia'
import App from "./App.vue"
import router from "./router"
import vuetify from "./plugins/vuetify"
import i18n from "./plugins/i18n"
import "./style.css"
import './utils/echo'
import CustomTitle from "./components/global/CustomTitle.vue";
import BaseButton from "./components/customs/BaseButton.vue";
import BaseButtonFilter from "./components/customs/BaseButtonFilter.vue";
import Notif from '@/components/global/Notification.vue'
import Confirm from '@/components/global/Confirm.vue'
import axios from 'axios'

const app = createApp(App)
const pinia = createPinia()

let lastTouchEnd = 0
document.addEventListener(
  'touchend',
  event => {
    const now = new Date().getTime()
    if (now - lastTouchEnd <= 300) {
      event.preventDefault()
    }
    lastTouchEnd = now
  },
  { passive: false }
)

axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL
axios.defaults.headers.common['Content-Type'] = 'application/json'
axios.defaults.headers.common['Accept'] = 'application/json'

app.use(pinia)
app.use(vuetify)
app.use(router)
app.use(i18n)
app.component("CustomTitle", CustomTitle);
app.component("BaseButton", BaseButton);
app.component("BaseButtonFilter", BaseButtonFilter);
app.config.globalProperties.$notif = Notif.value?.newAlert
app.config.globalProperties.$confirm = Confirm.value?.open

app.mount("#app")