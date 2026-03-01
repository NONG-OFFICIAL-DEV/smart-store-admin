<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-office-building-marker"
      title="Tenants"
      subtitle="Manage tenant profiles and subscription plans"
    >
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          @click="openCreate"
        >
          Add Tenant
        </v-btn>
      </template>
    </custom-title>

    <!-- Table -->
    <v-card rounded="lg" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="tenantStore.tenants"
        item-value="id"
      >
        <!-- Logo -->
        <template #item.logo_url="{ item }">
          <v-avatar size="36" rounded="md">
            <v-img v-if="item.logo_url" :src="item.logo_url" cover />
            <span v-else class="text-caption">{{ item.name.charAt(0) }}</span>
          </v-avatar>
        </template>

        <!-- Plan -->
        <template #item.plan="{ item }">
          <v-chip :color="planColor(item.plan)" variant="tonal" size="small">
            {{ item.plan }}
          </v-chip>
        </template>

        <!-- Status -->
        <template #item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'error'"
            size="small"
            variant="tonal"
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-pencil-outline"
            variant="text"
            size="small"
            @click="openEdit(item)"
          />
        </template>
      </v-data-table>
    </v-card>
    <!-- Dialog -->
    <TenantDialog
      v-model="dialog.show"
      :tenant="dialog.tenant"
      @saved="handleSave"
    />
  </v-container>
</template>

<script setup>
  import { ref, reactive, onMounted } from 'vue'
  import { useAppUtils } from '@nong-official-dev/core'

  const { confirm, notif } = useAppUtils()

  import TenantDialog from '@/components/tenants/TenantDialog.vue'
  import { useTenantStore } from '@/stores/tenantStore'

  const tenantStore = useTenantStore()

  const headers = [
    { title: 'Logo', key: 'logo_url' },
    { title: 'Name', key: 'name' },
    { title: 'Slug', key: 'slug' },
    { title: 'Plan', key: 'plan' },
    { title: 'Currency', key: 'currency' },
    { title: 'Timezone', key: 'timezone' },
    { title: 'Status', key: 'is_active' },
    { title: 'Actions', key: 'actions', sortable: false }
  ]

  const dialog = reactive({
    show: false,
    tenant: null
  })

  const openCreate = () => {
    dialog.tenant = null
    dialog.show = true
  }

  const openEdit = tenant => {
    dialog.tenant = { ...tenant }
    dialog.show = true
  }

  const fetchTenants = async () => {
    await tenantStore.fetchTenants()
  }

  // Called when dialog emits `saved`
  const handleSave = async tenantData => {
    try {
      if (tenantData.id) {
        await tenantStore.updateTenant(tenantData.id, tenantData)
        notif('Tenant saved successfully')
      } else {
        await tenantStore.createTenant(tenantData)
        notif('Tenant saved successfully')
      }

      await fetchTenants()

      dialog.show = false // ✅ CLOSE HERE
      dialog.tenant = null // optional cleanup
    } catch (error) {
      console.error(error)
    }
  }

  onMounted(fetchTenants)

  const planColor = plan => {
    const map = {
      free: 'grey',
      starter: 'info',
      pro: 'primary',
      enterprise: 'purple'
    }
    return map[plan] || 'grey'
  }
</script>
