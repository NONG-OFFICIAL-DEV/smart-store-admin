<template>
  <v-card rounded="xl" variant="flat" :elevation="0">
    <template #title>
      <div class="d-flex align-center">
        <v-icon color="indigo" class="me-3">mdi-crown-outline</v-icon>
        {{ t('billing.invoices.title') }}
      </div>
    </template>

    <v-divider />

    <v-data-table
      :items="invoices"
      :headers="headers"
      :loading="loading"
      no-data-text="No invoices yet"
      class="invoice-table"
      hover
      density="comfortable"
    >
      <!-- Invoice ID -->
      <template #item.id="{ item }">
        <span class="font-weight-medium text-indigo">
          {{ item.id }}
        </span>
      </template>

      <!-- Date -->
      <template #item.date="{ item }">
        <span class="text-medium-emphasis">
          {{ formatDate(item.date) }}
        </span>
      </template>

      <!-- Amount -->
      <template #item.amount="{ item }">
        <span class="font-weight-semibold">
          {{ formatCurrency(item.amount, currency) }}
        </span>
      </template>

      <!-- Payment Method -->
      <template #item.payment_method="{ item }">
        <div
          class="method-badge"
          :class="`method-badge--${item.payment_method}`"
        >
          <img
            v-if="item.payment_method === 'aba'"
            src="https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/ABA_Bank_logo.svg/200px-ABA_Bank_logo.svg.png"
            alt="ABA"
            class="method-badge__img"
            @error="handleImageError"
          />
          <img
            v-else
            src="https://bakong.nbc.gov.kh/images/logo.png"
            alt="Bakong"
            class="method-badge__img"
            @error="handleImageError"
          />
          <span class="method-badge__label">
            {{ item.payment_method?.toUpperCase() }}
          </span>
        </div>
      </template>

      <!-- Status -->
      <template #item.status="{ item }">
        <span class="status-pill" :class="`status-pill--${item.status}`">
          <span class="status-pill__dot" />
          {{ item.status }}
        </span>
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <div class="d-flex align-center justify-end ga-2">
          <v-btn
            v-if="item.status !== 'paid'"
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="mdi-qrcode-scan"
            @click="$emit('pay', item)"
          >
            Pay Now
          </v-btn>

          <v-btn
            size="small"
            variant="text"
            color="grey"
            prepend-icon="mdi-download"
            :href="item.pdf_url"
            target="_blank"
            :disabled="!item.pdf_url"
          >
            PDF
          </v-btn>
        </div>
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup>
  import { formatCurrency, formatDate } from '@nong-official-dev/core'
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()

  defineProps({
    invoices: { type: Array, default: () => [] },
    currency: { type: String, default: 'USD' },
    loading: { type: Boolean, default: false }
  })

  defineEmits(['pay'])

  const headers = computed(() => [
    { title: t('billing.invoices.table.invoice'), key: 'id', sortable: true },
    { title: t('billing.invoices.table.date'), key: 'date', sortable: true },
    {
      title: t('billing.invoices.table.amount'),
      key: 'amount',
      sortable: true,
      align: 'start'
    },
    {
      title: t('billing.invoices.table.method'),
      key: 'payment_method',
      sortable: false
    },
    {
      title: t('billing.invoices.table.status'),
      key: 'status',
      sortable: true
    },
    {
      title: t('billing.invoices.table.actions'),
      key: 'actions',
      sortable: false,
      align: 'end'
    }
  ])
  // Optional: Handle broken images
  const handleImageError = e => {
    e.target.style.display = 'none'
  }
</script>

<style scoped>
  .invoice-table :deep(.v-data-table__tr:hover) {
    background: rgba(99, 102, 241, 0.03) !important;
  }

  /* Method Badge */
  .method-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 12px;
  }

  .method-badge--aba {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
  }

  .method-badge--bakong {
    background: #fff5f5;
    border: 1px solid #fecaca;
  }

  .method-badge__img {
    height: 16px;
    width: 28px;
    object-fit: contain;
  }

  .method-badge__label {
    font-weight: 600;
    font-size: 11.5px;
    letter-spacing: 0.3px;
  }

  /* Status Pill */
  .status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 12.5px;
    font-weight: 500;
    text-transform: capitalize;
  }

  .status-pill__dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
  }

  .status-pill--paid {
    background: #f0fdf4;
    color: #166534;
  }
  .status-pill--paid .status-pill__dot {
    background: #4ade80;
  }

  .status-pill--pending {
    background: #fffbeb;
    color: #92400e;
  }
  .status-pill--pending .status-pill__dot {
    background: #fbbf24;
  }

  .status-pill--overdue {
    background: #fef2f2;
    color: #991b1b;
  }
  .status-pill--overdue .status-pill__dot {
    background: #f87171;
  }
</style>
