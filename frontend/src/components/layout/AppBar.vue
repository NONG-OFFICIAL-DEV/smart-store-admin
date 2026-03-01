<template>
  <v-app-bar scroll-behavior="inverted">
    <v-app-bar-nav-icon @click="togglerDrawer">
      <v-icon>mdi-menu</v-icon>
    </v-app-bar-nav-icon>
    <v-app-bar-title class="font-weight-bold d-none d-lg-block d-print-block">
      Inventory Management
    </v-app-bar-title>
    <switcher-language :icon-btn="false" />

    <v-btn class="text-none" stacked to="/notifications">
      <v-badge color="error" :content="props.notifications_count">
        <v-icon>mdi-bell-outline</v-icon>
      </v-badge>
    </v-btn>
    <template v-slot:append>
      <v-menu min-width="200px" rounded>
        <template v-slot:activator="{ props }">
          <v-btn stacked v-bind="props" class="me-4">
            <v-avatar color="brown" size="large">
              <!-- <v-img :src="user.profile"></v-img> -->
              <span class="text-h6">{{ initials }}</span>
            </v-avatar>
          </v-btn>
        </template>
        <v-card>
          <v-card-text>
            <div class="mx-auto text-center">
              <!-- <v-avatar color="brown">
                <span class="text-h5">{{ initials }}</span>
              </v-avatar> -->
              <h3>{{ props.user.name }}</h3>
              <p class="mt-1">
                {{ props.user.first_name }} {{ props.user.last_name }}
              </p>
              <!-- {{ me }} -->
              <!-- <v-divider class="my-3"></v-divider> -->
              <!-- <v-btn variant="text" rounded>Edit Account</v-btn> -->
              <v-divider class="my-3"></v-divider>
              <v-btn variant="text" rounded="lg" @click="handleLogout">
                Disconnect
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-menu>
    </template>
  </v-app-bar>
</template>

<script setup>
  import { computed } from 'vue'
  import { useAuthStore } from '@/stores/auth'
  import { useRouter } from 'vue-router'
  import { useAppUtils } from '@/composables/useAppUtils'
  import SwitcherLanguage from '../customs/SwitcherLanguage.vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const { notif } = useAppUtils()
  const props = defineProps({
    user: Object,
    notifications_count: Number // user will be passed from parent (Layout.vue)
  })

  const authStore = useAuthStore()
  const router = useRouter()
  // define emits
  const emit = defineEmits(['toggle'])
  // Drawer toggle event
  const togglerDrawer = () => {
    // emits event to parent
    emit('toggle')
  }

  const initials = computed(() => {
    if (!props.user) return ''

    const first = props.user.first_name ?? ''
    const last = props.user.last_name ?? ''

    if (!first && !last) return ''

    if (first && last) {
      return first[0].toUpperCase() + last[0].toUpperCase()
    }

    return first[0]?.toUpperCase() || last[0]?.toUpperCase() || ''
  })

  const handleLogout = async () => {
    await authStore.logout()
    notif(t('messages.logout_sucess'), {
      type: 'success',
      color: 'primary'
    })
    router.push({ name: 'Login' })
  }
</script>
