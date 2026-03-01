<template>
  <v-container fluid class="pa-0">
    <custom-title icon="mdi-invoice-outline">Invoice Settings</custom-title>
    <v-row>
      <v-col cols="12" md="4">
        <v-card flat rounded="xl" class="border pa-4 sticky-panel">
          <!-- <div class="text-h6 font-weight-black mb-4">Invoice Settings</div> -->

          <v-form>
            <label class="text-caption font-weight-bold text-grey">
              BRANDING
            </label>
            <v-file-input
              label="Upload Logo"
              variant="outlined"
              density="compact"
              prepend-icon="mdi-camera"
              class="mt-2"
              rounded="lg"
              @change="handleLogoUpload"
            />

            <v-select
              v-model="settings.themeColor"
              :items="colors"
              label="Theme Color"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            >
              <template v-slot:item="{ props, item }">
                <v-list-item
                  v-bind="props"
                  :prepend-icon="'mdi-circle'"
                  :base-color="item.value"
                />
              </template>
            </v-select>

            <v-divider class="my-4" />

            <label class="text-caption font-weight-bold text-grey">
              DETAILS
            </label>
            <v-text-field
              v-model="settings.prefix"
              label="Invoice Prefix (e.g., INV-)"
              variant="outlined"
              density="compact"
              rounded="lg"
              class="mt-2"
            />

            <v-switch
              v-model="settings.showTax"
              label="Show Tax Breakdown"
              color="primary"
              hide-details
            />

            <v-switch
              v-model="settings.showTerms"
              label="Include Terms & Conditions"
              color="primary"
              hide-details
            />

            <v-textarea
              v-if="settings.showTerms"
              v-model="settings.termsText"
              label="Terms and Conditions"
              variant="outlined"
              rows="3"
              class="mt-3"
              rounded="lg"
            />
          </v-form>

          <v-btn
            block
            color="primary"
            size="large"
            rounded="lg"
            class="text-none mt-4 font-weight-black"
            prepend-icon="mdi-content-save"
            @click="saveInvoiceSettings"
          >
            Save Layout
          </v-btn>
        </v-card>
      </v-col>

      <v-col cols="12" md="8">
        <v-card flat rounded="xl" class="border invoice-preview pa-10">
          <div class="d-flex justify-space-between mb-10">
            <div>
              <v-avatar
                size="80"
                rounded="lg"
                color="grey-lighten-3"
                class="mb-4"
              >
                <v-img v-if="settings.logo" :src="settings.logo" />
                <v-icon v-else size="40">mdi-storefront</v-icon>
              </v-avatar>
              <h2 class="text-h5 font-weight-black">YOUR BUSINESS NAME</h2>
              <p class="text-caption text-grey">123 Business St, Phnom Penh</p>
            </div>
            <div class="text-right">
              <h1
                class="text-h3 font-weight-black mb-1"
                :style="{ color: settings.themeColor }"
              >
                INVOICE
              </h1>
              <p class="font-weight-bold">{{ settings.prefix }}0001</p>
              <p class="text-caption text-grey">Date: Feb 02, 2026</p>
            </div>
          </div>

          <v-table class="mb-6 border rounded-lg">
            <thead :style="{ backgroundColor: settings.themeColor + '15' }">
              <tr>
                <th class="font-weight-black">Item Description</th>
                <th class="text-center font-weight-black">Qty</th>
                <th class="text-right font-weight-black">Price</th>
                <th class="text-right font-weight-black">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Cappuccino (Large)</td>
                <td class="text-center">2</td>
                <td class="text-right">$4.00</td>
                <td class="text-right font-weight-bold">$8.00</td>
              </tr>
            </tbody>
          </v-table>

          <v-row justify="end">
            <v-col cols="4">
              <div class="d-flex justify-space-between mb-1">
                <span class="text-grey">Subtotal:</span>
                <span class="font-weight-bold">$8.00</span>
              </div>
              <div
                v-if="settings.showTax"
                class="d-flex justify-space-between mb-1"
              >
                <span class="text-grey">VAT (10%):</span>
                <span class="font-weight-bold">$0.80</span>
              </div>
              <v-divider class="my-2" />
              <div class="d-flex justify-space-between text-h6">
                <span class="font-weight-black">Total:</span>
                <span
                  class="font-weight-black"
                  :style="{ color: settings.themeColor }"
                >
                  $8.80
                </span>
              </div>
            </v-col>
          </v-row>

          <div v-if="settings.showTerms" class="mt-10 pt-4 border-t">
            <div class="text-caption font-weight-bold mb-1">
              TERMS & CONDITIONS
            </div>
            <p class="text-caption text-grey">{{ settings.termsText }}</p>
          </div>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { reactive, ref } from 'vue'

  const settings = reactive({
    logo: null,
    themeColor: '#004D40',
    prefix: 'INV-',
    showTax: true,
    showTerms: true,
    termsText: 'Please pay within 7 days. Thank you for your business!'
  })

  const colors = [
    { title: 'Teal (Dark)', value: '#004D40' },
    { title: 'Indigo', value: '#3F51B5' },
    { title: 'Deep Orange', value: '#FF5722' },
    { title: 'Black', value: '#212121' }
  ]

  const handleLogoUpload = event => {
    const file = event.target.files[0]
    if (file) {
      settings.logo = URL.createObjectURL(file)
    }
  }

  const saveInvoiceSettings = () => {
    // Logic to save settings to backend
    console.log('Saved settings:', settings)
  }
</script>

<style scoped>
  .sticky-panel {
    position: sticky;
    top: 24px;
  }

  .invoice-preview {
    background-color: white !important;
    min-height: 800px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05) !important;
  }

  .border-t {
    border-top: 1px solid #eee;
  }
</style>
