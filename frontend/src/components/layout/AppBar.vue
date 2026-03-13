<template>
  <v-app-bar scroll-behavior="inverted">
    <v-app-bar-nav-icon @click="emit('toggle')">
      <v-icon>mdi-menu</v-icon>
    </v-app-bar-nav-icon>
    <v-app-bar-title class="d-none d-lg-block d-print-block">
      <div class="d-flex align-center gap-2">
        <span class="font-weight-black text-body-1">
          {{ buName || 'SaaS Management' }}
        </span>
        <template v-if="branchName">
          <v-chip
            size="x-small"
            variant="tonal"
            color="primary"
            rounded="lg"
            class="ms-3"
          >
            <v-icon start size="10" icon="mdi-store" />
            {{ branchName }}
          </v-chip>
        </template>
      </div>
    </v-app-bar-title>
    <switcher-language :icon-btn="false" />

    <v-btn class="text-none" stacked to="/notifications">
      <v-badge color="error" :content="props.notifications_count">
        <v-icon>mdi-bell-outline</v-icon>
      </v-badge>
    </v-btn>
    <template v-slot:append>
      <!-- Avatar menu -->
      <v-menu rounded="lg">
        <template #activator="{ props: menuProps }">
          <v-btn stacked v-bind="menuProps" class="me-2">
            <v-avatar :color="avatarColor" size="40">
              <v-img v-if="user?.avatar_url" :src="user.avatar_url" cover />
              <span v-else class="text-caption font-weight-black text-white">
                {{ initials }}
              </span>
            </v-avatar>
          </v-btn>
        </template>

        <v-card rounded="lg">
          <v-card-text class="pa-4">
            <!-- User info -->
            <div class="d-flex align-center gap-3 mb-3">
              <v-avatar :color="avatarColor" size="44" rounded="lg">
                <v-img v-if="user?.avatar_url" :src="user.avatar_url" cover />
                <span v-else class="text-body-2 font-weight-black text-white">
                  {{ initials }}
                </span>
              </v-avatar>
              <div class="min-w-0">
                <div class="text-body-2 font-weight-bold text-truncate">
                  {{ user?.first_name }} {{ user?.last_name }}
                </div>
                <div class="text-caption text-medium-emphasis text-truncate">
                  {{ user?.email }}
                </div>
              </div>
            </div>

            <!-- Branch / role badges -->
            <div class="d-flex gap-1 flex-wrap">
              <v-chip
                v-if="branchName"
                size="x-small"
                variant="tonal"
                color="primary"
                rounded="lg"
              >
                <v-icon start size="10" icon="mdi-store-outline" />
                {{ branchName }}
              </v-chip>
              <v-chip
                v-if="buName"
                size="x-small"
                variant="tonal"
                color="secondary"
                rounded="lg"
              >
                {{ buName }}
              </v-chip>
            </div>

            <v-divider class="mb-3 mt-2" />

            <v-btn
              block
              variant="tonal"
              rounded="lg"
              color="error"
              prepend-icon="mdi-logout"
              @click="handleLogout"
            >
              Sign Out
            </v-btn>
          </v-card-text>
        </v-card>
      </v-menu>
    </template>
  </v-app-bar>
</template>

<script setup>
  import { computed } from 'vue'
  import { useAuthStore } from '@/stores/authStore'
  import { useRouter } from 'vue-router'
  import { useAppUtils } from '@/composables/useAppUtils'
  import SwitcherLanguage from '../customs/SwitcherLanguage.vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const { notif } = useAppUtils()
  const props = defineProps({
    user: Object,
    notifications_count: Number,
    buName: String,
    branchName: String
  })

  const authStore = useAuthStore()
  const router = useRouter()
  // define emits
  const emit = defineEmits(['toggle'])

  const initials = computed(() => {
    const f = props.user?.first_name ?? ''
    const l = props.user?.last_name ?? ''
    if (f && l) return (f[0] + l[0]).toUpperCase()
    return (f[0] ?? l[0] ?? '?').toUpperCase()
  })

  // Deterministic color from name
  const avatarColor = computed(() => {
    const colors = [
      'primary',
      'secondary',
      'success',
      'warning',
      'error',
      'info',
      'teal',
      'purple'
    ]
    const name = (props.user?.first_name ?? '') + (props.user?.last_name ?? '')
    const index =
      name.split('').reduce((s, c) => s + c.charCodeAt(0), 0) % colors.length
    return colors[index]
  })

  const handleLogout = async () => {
    await authStore.logout()
    notif(t('messages.logout_sucess'), { type: 'success', color: 'primary' })
    router.push({ name: 'Login' })
  }
</script>
<style scoped>
.gap-1 { gap: 4px;  }
.gap-2 { gap: 8px;  }
.gap-3 { gap: 12px; }
.min-w-0 { min-width: 0; }
</style>