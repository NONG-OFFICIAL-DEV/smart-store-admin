<template>
  <v-container fluid class="pa-0">
    <custom-title
      title="Table Management"
      subtitle="Floor plans · Tables · Reservations"
    >
      <template #right>
        <v-btn
          class="bg-primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          @click="openCreate"
        >
          {{
            tab === 'floor'
              ? 'New Floor Plan'
              : tab === 'tables'
                ? 'New Table'
                : 'New Reservation'
          }}
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Tab Bar ─────────────────────────────────────────────────────────── -->
    <div class="tab-bar mb-6">
      <button
        v-for="t in tabs"
        :key="t.value"
        class="tab-btn"
        :class="{ active: tab === t.value }"
        @click="tab = t.value"
      >
        <v-icon :icon="t.icon" size="16" class="mr-2" />
        {{ t.label }}
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         FLOOR PLANS TAB
    ═══════════════════════════════════════════════════════════════════ -->
    <div v-show="tab === 'floor'">
      <!-- Floor selector pills -->
      <div class="d-flex align-center gap-3 mb-5 flex-wrap">
        <button
          v-for="fp in floorPlans"
          :key="fp.id"
          class="floor-pill"
          :class="{ active: activeFloor?.id === fp.id }"
          @click="activeFloor = fp"
        >
          <v-icon icon="mdi-layers-outline" size="14" class="mr-1" />
          {{ fp.name }}
        </button>
        <span
          v-if="!floorPlans.length"
          class="text-caption"
          style="color: #888"
        >
          No floor plans yet
        </span>
      </div>

      <!-- Canvas -->
      <div class="canvas-wrap">
        <div class="canvas-grid" ref="canvasRef" @click.self="deselectTable">
          <!-- Grid lines decoration -->
          <svg class="canvas-grid-lines" width="100%" height="100%">
            <defs>
              <pattern
                id="smallGrid"
                width="30"
                height="30"
                patternUnits="userSpaceOnUse"
              >
                <path
                  d="M 30 0 L 0 0 0 30"
                  fill="none"
                  stroke="rgba(255,255,255,0.03)"
                  stroke-width="1"
                />
              </pattern>
              <pattern
                id="grid"
                width="150"
                height="150"
                patternUnits="userSpaceOnUse"
              >
                <rect width="150" height="150" fill="url(#smallGrid)" />
                <path
                  d="M 150 0 L 0 0 0 150"
                  fill="none"
                  stroke="rgba(255,255,255,0.06)"
                  stroke-width="1"
                />
              </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" />
          </svg>

          <!-- Empty state -->
          <div v-if="!activeFloor" class="canvas-empty">
            <v-icon
              icon="mdi-floor-plan"
              size="52"
              style="color: #444; margin-bottom: 12px"
            />
            <div style="color: #666; font-size: 14px">
              Select or create a floor plan to begin
            </div>
          </div>

          <div v-else-if="!floorTables.length" class="canvas-empty">
            <v-icon
              icon="mdi-table-furniture"
              size="52"
              style="color: #444; margin-bottom: 12px"
            />
            <div style="color: #666; font-size: 14px">
              No tables on this floor — add tables and assign them here
            </div>
          </div>

          <!-- Table nodes -->
          <div
            v-for="tbl in floorTables"
            :key="tbl.id"
            class="canvas-table"
            :class="[
              `shape-${tbl.shape}`,
              `status-${tbl.status}`,
              { selected: selectedTable?.id === tbl.id }
            ]"
            :style="{
              left: (tbl.pos_x || 80) + 'px',
              top: (tbl.pos_y || 80) + 'px'
            }"
            @mousedown="startDrag($event, tbl)"
            @click.stop="selectedTable = tbl"
          >
            <span class="table-num">{{ tbl.table_number }}</span>
            <span class="table-cap">{{ tbl.capacity }}p</span>
            <div class="status-dot" />
          </div>
        </div>

        <!-- Canvas legend -->
        <div class="canvas-legend">
          <span v-for="s in tableStatuses" :key="s" class="legend-item">
            <span class="legend-dot" :class="`dot-${s}`" />
            {{ s }}
          </span>
        </div>

        <!-- Selected table info -->
        <transition name="slide-up">
          <div v-if="selectedTable" class="table-info-card">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="info-label">
                  Table {{ selectedTable.table_number }}
                </div>
                <div class="info-sub">
                  {{ selectedTable.capacity }} seats · {{ selectedTable.shape }}
                </div>
              </div>
              <div class="d-flex gap-2">
                <v-btn
                  size="small"
                  variant="tonal"
                  icon="mdi-pencil-outline"
                  @click="openEditTable(selectedTable)"
                />
                <v-btn
                  size="small"
                  variant="tonal"
                  icon="mdi-close"
                  @click="selectedTable = null"
                />
              </div>
            </div>
            <div class="mt-2">
              <v-chip
                :color="statusColor(selectedTable.status)"
                size="x-small"
                variant="tonal"
                rounded="lg"
              >
                {{ selectedTable.status }}
              </v-chip>
            </div>
          </div>
        </transition>
      </div>

      <!-- Floor plan list cards -->
      <div class="mt-6 section-label mb-3">All Floor Plans</div>
      <div class="floor-grid">
        <div
          v-for="fp in floorPlans"
          :key="fp.id"
          class="floor-card"
          :class="{ active: activeFloor?.id === fp.id }"
          @click="activeFloor = fp"
        >
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-3">
              <div class="floor-icon">
                <v-icon icon="mdi-layers-outline" size="20" />
              </div>
              <div>
                <div class="floor-card-name">{{ fp.name }}</div>
                <div class="floor-card-sub">Sort: {{ fp.sort_order }}</div>
              </div>
            </div>
            <div class="d-flex gap-1">
              <v-btn
                icon="mdi-pencil-outline"
                size="x-small"
                variant="text"
                style="color: #aaa"
                @click.stop="edit(fp)"
              />
              <v-btn
                icon="mdi-delete-outline"
                size="x-small"
                variant="text"
                color="error"
                @click.stop
              />
            </div>
          </div>
        </div>
        <div v-if="!floorPlans.length" class="empty-card">
          <v-icon icon="mdi-floor-plan" size="32" style="color: #444" />
          <p>No floor plans yet</p>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         TABLES TAB
    ═══════════════════════════════════════════════════════════════════ -->
    <div v-show="tab === 'tables'">
      <!-- Status filter chips -->
      <div class="d-flex gap-2 mb-4 flex-wrap">
        <button
          v-for="s in ['all', ...tableStatuses]"
          :key="s"
          class="filter-chip"
          :class="{ active: tableFilter === s }"
          @click="tableFilter = s"
        >
          {{ s }}
        </button>
      </div>

      <div class="table-grid">
        <div v-for="tbl in filteredTables" :key="tbl.id" class="tbl-card">
          <!-- Shape badge -->
          <div class="tbl-shape-badge" :class="`shape-badge-${tbl.shape}`">
            <v-icon :icon="shapeIcon(tbl.shape)" size="20" />
          </div>

          <div class="tbl-body">
            <div class="tbl-number">#{{ tbl.table_number }}</div>
            <div class="tbl-meta">
              {{ tbl.capacity }} seats · {{ tbl.shape }}
            </div>
          </div>

          <div class="d-flex align-center justify-space-between mt-3">
            <v-chip
              :color="statusColor(tbl.status)"
              size="x-small"
              variant="tonal"
              rounded="lg"
            >
              <span class="status-dot-inline" :class="`dot-${tbl.status}`" />
              {{ tbl.status }}
            </v-chip>
            <div class="d-flex gap-1">
              <v-btn
                icon="mdi-pencil-outline"
                size="x-small"
                variant="text"
                style="color: #aaa"
                @click="edit(tbl)"
              />
              <v-btn
                icon="mdi-delete-outline"
                size="x-small"
                variant="text"
                color="error"
              />
            </div>
          </div>
        </div>

        <div
          v-if="!filteredTables.length"
          class="empty-card"
          style="grid-column: 1/-1"
        >
          <v-icon icon="mdi-table-furniture" size="32" style="color: #444" />
          <p>No tables found</p>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         RESERVATIONS TAB
    ═══════════════════════════════════════════════════════════════════ -->
    <div v-show="tab === 'reservations'">
      <div class="res-list">
        <div v-for="res in reservations" :key="res.id" class="res-card">
          <div class="res-time-col">
            <div class="res-time">{{ formatTime(res.reserved_at) }}</div>
            <div class="res-date">{{ formatDate(res.reserved_at) }}</div>
            <div class="res-dur">{{ res.duration_minutes }}min</div>
          </div>
          <div class="res-divider" />
          <div class="res-body">
            <div class="res-name">{{ res.customer_name }}</div>
            <div class="res-meta">
              <v-icon icon="mdi-phone-outline" size="13" class="mr-1" />
              {{ res.customer_phone }}
              <span class="mx-2">·</span>
              <v-icon icon="mdi-account-group-outline" size="13" class="mr-1" />
              {{ res.party_size }} guests
            </div>
            <div v-if="res.notes" class="res-notes">{{ res.notes }}</div>
          </div>
          <div class="res-actions">
            <v-chip
              :color="reservationColor(res.status)"
              size="x-small"
              variant="tonal"
              rounded="lg"
              class="mb-2"
            >
              {{ res.status }}
            </v-chip>
            <div class="d-flex gap-1">
              <v-btn
                icon="mdi-pencil-outline"
                size="x-small"
                variant="text"
                style="color: #aaa"
                @click="edit(res)"
              />
              <v-btn
                icon="mdi-delete-outline"
                size="x-small"
                variant="text"
                color="error"
              />
            </div>
          </div>
        </div>

        <div v-if="!reservations.length" class="empty-card">
          <v-icon
            icon="mdi-calendar-blank-outline"
            size="32"
            style="color: #444"
          />
          <p>No reservations yet</p>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         SLIDE-OVER DRAWER
    ═══════════════════════════════════════════════════════════════════ -->
    <v-navigation-drawer
      v-model="drawer"
      location="right"
      width="440"
      temporary
      class="drawer-panel"
    >
      <!-- Drawer Header -->
      <div class="drawer-header">
        <div class="d-flex align-center gap-3">
          <div class="drawer-avatar" :class="isEdit ? 'edit' : 'create'">
            <v-icon
              :icon="isEdit ? 'mdi-pencil' : 'mdi-plus'"
              size="18"
              color="white"
            />
          </div>
          <div>
            <div class="drawer-title">
              {{ isEdit ? 'Edit' : 'New' }} {{ tabLabel }}
            </div>
            <div class="drawer-sub">Fill in the details below</div>
          </div>
        </div>
        <v-btn
          icon="mdi-close"
          size="small"
          variant="text"
          style="color: #888"
          @click="closeDrawer"
        />
      </div>

      <v-divider style="border-color: rgba(255, 255, 255, 0.07)" />

      <div class="pa-5">
        <v-form ref="formRef" validate-on="input">
          <!-- FLOOR PLAN FORM -->
          <template v-if="tab === 'floor'">
            <div class="field-label">Floor Plan Name</div>
            <v-text-field
              v-model="form.name"
              placeholder="e.g. Ground Floor, Rooftop..."
              variant="outlined"
              density="comfortable"
              class="dark-field mb-3"
              :rules="[r.required]"
            />
            <div class="field-label">Sort Order</div>
            <v-text-field
              v-model.number="form.sort_order"
              type="number"
              min="0"
              variant="outlined"
              density="comfortable"
              class="dark-field"
              :rules="[r.nonNeg]"
            />
          </template>

          <!-- TABLE FORM -->
          <template v-if="tab === 'tables'">
            <div class="form-row">
              <div>
                <div class="field-label">Table Number</div>
                <v-text-field
                  v-model="form.table_number"
                  placeholder="e.g. T-01"
                  variant="outlined"
                  density="comfortable"
                  class="dark-field"
                  :rules="[r.required]"
                />
              </div>
              <div>
                <div class="field-label">Capacity</div>
                <v-text-field
                  v-model.number="form.capacity"
                  type="number"
                  min="1"
                  variant="outlined"
                  density="comfortable"
                  class="dark-field"
                  :rules="[r.required, r.positive]"
                />
              </div>
            </div>

            <div class="field-label">Shape</div>
            <div class="shape-picker mb-4">
              <button
                v-for="s in ['round', 'square', 'rectangle', 'bar']"
                :key="s"
                type="button"
                class="shape-option"
                :class="{ active: form.shape === s }"
                @click="form.shape = s"
              >
                <v-icon :icon="shapeIcon(s)" size="22" />
                <span>{{ s }}</span>
              </button>
            </div>

            <div class="field-label">Status</div>
            <div class="status-picker mb-4">
              <button
                v-for="s in tableStatuses"
                :key="s"
                type="button"
                class="status-option"
                :class="[{ active: form.status === s }, `status-opt-${s}`]"
                @click="form.status = s"
              >
                {{ s }}
              </button>
            </div>

            <v-switch
              v-model="form.is_active"
              label="Active"
              color="amber"
              density="compact"
              inset
              hide-details
            />
          </template>

          <!-- RESERVATION FORM -->
          <template v-if="tab === 'reservations'">
            <div class="form-row">
              <div>
                <div class="field-label">Customer Name</div>
                <v-text-field
                  v-model="form.customer_name"
                  variant="outlined"
                  density="comfortable"
                  class="dark-field"
                  :rules="[r.required]"
                />
              </div>
              <div>
                <div class="field-label">Phone</div>
                <v-text-field
                  v-model="form.customer_phone"
                  variant="outlined"
                  density="comfortable"
                  class="dark-field"
                />
              </div>
            </div>

            <div class="form-row">
              <div>
                <div class="field-label">Party Size</div>
                <v-text-field
                  v-model.number="form.party_size"
                  type="number"
                  min="1"
                  variant="outlined"
                  density="comfortable"
                  class="dark-field"
                  :rules="[r.required, r.positive]"
                />
              </div>
              <div>
                <div class="field-label">Duration (min)</div>
                <v-text-field
                  v-model.number="form.duration_minutes"
                  type="number"
                  min="15"
                  variant="outlined"
                  density="comfortable"
                  class="dark-field"
                />
              </div>
            </div>

            <div class="field-label">Reserved At</div>
            <v-text-field
              v-model="form.reserved_at"
              type="datetime-local"
              variant="outlined"
              density="comfortable"
              class="dark-field mb-3"
              :rules="[r.required]"
            />

            <div class="field-label">Status</div>
            <div class="status-picker mb-4">
              <button
                v-for="s in reservationStatuses"
                :key="s"
                type="button"
                class="status-option"
                :class="[{ active: form.status === s }, `status-opt-${s}`]"
                @click="form.status = s"
              >
                {{ s }}
              </button>
            </div>

            <div class="field-label">Notes</div>
            <v-textarea
              v-model="form.notes"
              variant="outlined"
              density="comfortable"
              class="dark-field"
              rows="3"
              placeholder="Any special requests..."
            />
          </template>
        </v-form>
      </div>

      <template #append>
        <v-divider style="border-color: rgba(255, 255, 255, 0.07)" />
        <div class="pa-5 d-flex gap-3">
          <v-btn
            block
            variant="tonal"
            rounded="lg"
            size="large"
            @click="closeDrawer"
          >
            Cancel
          </v-btn>
          <v-btn block class="save-btn" rounded="lg" size="large" @click="save">
            {{ isEdit ? 'Save Changes' : 'Create' }}
          </v-btn>
        </div>
      </template>
    </v-navigation-drawer>
  </v-container>
</template>

<script setup>
  import { ref, computed } from 'vue'

  /* ── Tabs ── */
  const tabs = [
    { value: 'floor', label: 'Floor Plans', icon: 'mdi-floor-plan' },
    { value: 'tables', label: 'Tables', icon: 'mdi-table-furniture' },
    { value: 'reservations', label: 'Reservations', icon: 'mdi-calendar-clock' }
  ]
  const tab = ref('floor')

  /* ── Drawer ── */
  const drawer = ref(false)
  const isEdit = ref(false)
  const formRef = ref(null)
  const form = ref({})

  /* ── Data ── */
  const floorPlans = ref([
    { id: 1, name: 'Ground Floor', sort_order: 1 },
    { id: 2, name: 'Rooftop', sort_order: 2 }
  ])
  const activeFloor = ref(null)
  const selectedTable = ref(null)

  const tables = ref([
    {
      id: 1,
      table_number: 'T-01',
      capacity: 4,
      shape: 'round',
      status: 'available',
      is_active: true,
      pos_x: 80,
      pos_y: 80,
      floor_plan_id: 1
    },
    {
      id: 2,
      table_number: 'T-02',
      capacity: 6,
      shape: 'rectangle',
      status: 'occupied',
      is_active: true,
      pos_x: 220,
      pos_y: 80,
      floor_plan_id: 1
    },
    {
      id: 3,
      table_number: 'T-03',
      capacity: 2,
      shape: 'square',
      status: 'reserved',
      is_active: true,
      pos_x: 380,
      pos_y: 80,
      floor_plan_id: 1
    },
    {
      id: 4,
      table_number: 'T-04',
      capacity: 8,
      shape: 'bar',
      status: 'cleaning',
      is_active: false,
      pos_x: 80,
      pos_y: 220,
      floor_plan_id: 2
    }
  ])

  const reservations = ref([
    {
      id: 1,
      customer_name: 'Sopheap Keo',
      customer_phone: '012 345 678',
      party_size: 4,
      reserved_at: '2026-02-27T18:30',
      duration_minutes: 90,
      status: 'confirmed',
      notes: 'Window seat preferred'
    },
    {
      id: 2,
      customer_name: 'Dara Chea',
      customer_phone: '089 654 321',
      party_size: 2,
      reserved_at: '2026-02-27T20:00',
      duration_minutes: 60,
      status: 'pending',
      notes: ''
    },
    {
      id: 3,
      customer_name: 'Maly Pich',
      customer_phone: '077 111 222',
      party_size: 6,
      reserved_at: '2026-02-28T19:00',
      duration_minutes: 120,
      status: 'completed',
      notes: 'Birthday celebration'
    }
  ])

  /* ── Filter ── */
  const tableFilter = ref('all')
  const filteredTables = computed(() =>
    tableFilter.value === 'all'
      ? tables.value
      : tables.value.filter(t => t.status === tableFilter.value)
  )

  const floorTables = computed(() =>
    activeFloor.value
      ? tables.value.filter(t => t.floor_plan_id === activeFloor.value.id)
      : []
  )

  /* ── Status / shape lists ── */
  const tableStatuses = [
    'available',
    'occupied',
    'reserved',
    'cleaning',
    'inactive'
  ]
  const reservationStatuses = [
    'pending',
    'confirmed',
    'seated',
    'completed',
    'no_show',
    'cancelled'
  ]

  /* ── Computed label ── */
  const tabLabel = computed(() => {
    if (tab.value === 'floor') return 'Floor Plan'
    if (tab.value === 'tables') return 'Table'
    return 'Reservation'
  })

  /* ── Validation ── */
  const r = {
    required: v => (!!v && String(v).trim() !== '') || 'Required',
    positive: v => Number(v) > 0 || 'Must be greater than 0',
    nonNeg: v => Number(v) >= 0 || 'Must be 0 or more'
  }

  /* ── Helpers ── */
  function statusColor(s) {
    return (
      {
        available: 'green',
        occupied: 'red',
        reserved: 'orange',
        cleaning: 'blue',
        inactive: 'grey'
      }[s] ?? 'grey'
    )
  }
  function reservationColor(s) {
    return (
      {
        confirmed: 'green',
        pending: 'orange',
        cancelled: 'red',
        completed: 'blue',
        seated: 'teal',
        no_show: 'grey'
      }[s] ?? 'grey'
    )
  }
  function shapeIcon(s) {
    return (
      {
        round: 'mdi-circle-outline',
        square: 'mdi-square-outline',
        rectangle: 'mdi-rectangle-outline',
        bar: 'mdi-minus-box-outline'
      }[s] ?? 'mdi-square-outline'
    )
  }
  function formatTime(dt) {
    if (!dt) return '--'
    return new Date(dt).toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit'
    })
  }
  function formatDate(dt) {
    if (!dt) return '--'
    return new Date(dt).toLocaleDateString([], {
      month: 'short',
      day: 'numeric'
    })
  }

  /* ── Drawer controls ── */
  function openCreate() {
    isEdit.value = false
    form.value = {
      sort_order: 0,
      is_active: true,
      shape: 'round',
      status: tableStatuses[0]
    }
    drawer.value = true
  }
  function edit(item) {
    isEdit.value = true
    form.value = { ...item }
    drawer.value = true
  }
  function openEditTable(item) {
    tab.value = 'tables'
    edit(item)
  }
  function closeDrawer() {
    drawer.value = false
    setTimeout(() => {
      form.value = {}
      formRef.value?.reset()
    }, 300)
  }
  function save() {
    // connect to API
    closeDrawer()
  }

  /* ── Canvas deselect ── */
  function deselectTable() {
    selectedTable.value = null
  }

  /* ── Drag-to-move tables on canvas ── */
  function startDrag(e, tbl) {
    const startX = e.clientX - (tbl.pos_x || 0)
    const startY = e.clientY - (tbl.pos_y || 0)
    function onMove(ev) {
      tbl.pos_x = Math.max(0, ev.clientX - startX)
      tbl.pos_y = Math.max(0, ev.clientY - startY)
    }
    function onUp() {
      window.removeEventListener('mousemove', onMove)
      window.removeEventListener('mouseup', onUp)
    }
    window.addEventListener('mousemove', onMove)
    window.addEventListener('mouseup', onUp)
  }
</script>

<style scoped>
  .page-title {
    font-size: 22px;
    font-weight: 700;
    color: #f0ece4;
    letter-spacing: -0.3px;
  }
  .page-subtitle {
    font-size: 13px;
    color: #666;
    margin-top: 2px;
  }

  /* ── Tab bar ── */
  .tab-bar {
    display: flex;
    gap: 4px;
    background: #1a1c1f;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 12px;
    padding: 5px;
    width: fit-content;
  }
  .tab-btn {
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #666;
    background: transparent;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: all 0.2s;
  }
  .tab-btn.active {
    background: #252830;
    color: #d4a053;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
  }
  .tab-btn:hover:not(.active) {
    color: #aaa;
  }

  /* ── Floor pills ── */
  .floor-pill {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: #1a1c1f;
    color: #777;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: all 0.2s;
  }
  .floor-pill.active {
    background: rgba(212, 160, 83, 0.15);
    border-color: #d4a053;
    color: #d4a053;
  }
  .floor-pill:hover:not(.active) {
    color: #bbb;
    border-color: rgba(255, 255, 255, 0.2);
  }

  /* ── Canvas ── */
  .canvas-wrap {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.07);
    background: #13151a;
  }
  .canvas-grid {
    position: relative;
    width: 100%;
    height: 520px;
    overflow: hidden;
  }
  .canvas-grid-lines {
    position: absolute;
    inset: 0;
    pointer-events: none;
  }
  .canvas-empty {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  /* ── Table nodes ── */
  .canvas-table {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: grab;
    user-select: none;
    transition:
      box-shadow 0.2s,
      transform 0.15s;
    border: 2px solid rgba(255, 255, 255, 0.1);
  }
  .canvas-table:active {
    cursor: grabbing;
  }
  .canvas-table:hover {
    transform: scale(1.05);
  }
  .canvas-table.selected {
    border-color: #d4a053 !important;
    box-shadow: 0 0 0 3px rgba(212, 160, 83, 0.25);
  }

  /* Shapes */
  .shape-round {
    width: 72px;
    height: 72px;
    border-radius: 50%;
  }
  .shape-square {
    width: 72px;
    height: 72px;
    border-radius: 10px;
  }
  .shape-rectangle {
    width: 110px;
    height: 68px;
    border-radius: 10px;
  }
  .shape-bar {
    width: 130px;
    height: 44px;
    border-radius: 6px;
  }

  /* Status colors */
  .status-available {
    background: rgba(76, 175, 80, 0.18);
    border-color: rgba(76, 175, 80, 0.5);
  }
  .status-occupied {
    background: rgba(244, 67, 54, 0.18);
    border-color: rgba(244, 67, 54, 0.5);
  }
  .status-reserved {
    background: rgba(255, 152, 0, 0.18);
    border-color: rgba(255, 152, 0, 0.5);
  }
  .status-cleaning {
    background: rgba(33, 150, 243, 0.18);
    border-color: rgba(33, 150, 243, 0.5);
  }
  .status-inactive {
    background: rgba(120, 120, 120, 0.15);
    border-color: rgba(120, 120, 120, 0.4);
  }

  .table-num {
    font-size: 13px;
    font-weight: 700;
    color: #e0dbd0;
  }
  .table-cap {
    font-size: 10px;
    color: #888;
    margin-top: 1px;
  }
  .status-dot {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
  }
  .status-available .status-dot {
    background: #4caf50;
  }
  .status-occupied .status-dot {
    background: #f44336;
  }
  .status-reserved .status-dot {
    background: #ff9800;
  }
  .status-cleaning .status-dot {
    background: #2196f3;
  }
  .status-inactive .status-dot {
    background: #777;
  }

  /* Canvas legend */
  .canvas-legend {
    display: flex;
    gap: 16px;
    padding: 10px 16px;
    background: #0e1012;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
  }
  .legend-item {
    display: flex;
    align-items: center;
    font-size: 11px;
    color: #666;
    gap: 6px;
  }
  .legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
  }
  .dot-available {
    background: #4caf50;
  }
  .dot-occupied {
    background: #f44336;
  }
  .dot-reserved {
    background: #ff9800;
  }
  .dot-cleaning {
    background: #2196f3;
  }
  .dot-inactive {
    background: #555;
  }

  /* Selected table info card */
  .table-info-card {
    position: absolute;
    bottom: 52px;
    left: 16px;
    background: #1e2026;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 14px 16px;
    min-width: 220px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
  }
  .info-label {
    font-size: 14px;
    font-weight: 700;
    color: #f0ece4;
  }
  .info-sub {
    font-size: 12px;
    color: #777;
    margin-top: 1px;
  }

  .slide-up-enter-active,
  .slide-up-leave-active {
    transition: all 0.25s ease;
  }
  .slide-up-enter-from,
  .slide-up-leave-to {
    opacity: 0;
    transform: translateY(8px);
  }

  /* ── Floor card grid ── */
  .section-label {
    font-size: 11px;
    font-weight: 600;
    color: #555;
    letter-spacing: 0.8px;
    text-transform: uppercase;
  }
  .floor-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 12px;
  }
  .floor-card {
    background: #1a1c1f;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s;
  }
  .floor-card:hover {
    border-color: rgba(255, 255, 255, 0.15);
  }
  .floor-card.active {
    border-color: rgba(212, 160, 83, 0.5);
    background: rgba(212, 160, 83, 0.06);
  }
  .floor-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #d4a053;
  }
  .floor-card-name {
    font-size: 14px;
    font-weight: 600;
    color: #e0dbd0;
  }
  .floor-card-sub {
    font-size: 12px;
    color: #666;
    margin-top: 1px;
  }

  /* ── Table card grid ── */
  .table-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 14px;
  }
  .tbl-card {
    background: #1a1c1f;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 14px;
    padding: 16px;
    transition: border-color 0.2s;
  }
  .tbl-card:hover {
    border-color: rgba(255, 255, 255, 0.14);
  }
  .tbl-shape-badge {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    color: #d4a053;
    background: rgba(212, 160, 83, 0.1);
  }
  .shape-badge-round {
    border-radius: 50%;
  }
  .shape-badge-bar {
    border-radius: 6px;
  }
  .tbl-number {
    font-size: 18px;
    font-weight: 700;
    color: #f0ece4;
  }
  .tbl-meta {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
  }
  .status-dot-inline {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50;
    margin-right: 4px;
    vertical-align: middle;
  }

  /* ── Filter chips ── */
  .filter-chip {
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: #1a1c1f;
    color: #666;
    cursor: pointer;
    text-transform: capitalize;
    transition: all 0.2s;
  }
  .filter-chip.active {
    background: rgba(212, 160, 83, 0.15);
    border-color: #d4a053;
    color: #d4a053;
  }
  .filter-chip:hover:not(.active) {
    color: #bbb;
  }

  /* ── Reservation list ── */
  .res-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .res-card {
    background: #1a1c1f;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    transition: border-color 0.2s;
  }
  .res-card:hover {
    border-color: rgba(255, 255, 255, 0.12);
  }
  .res-time-col {
    min-width: 56px;
    text-align: center;
  }
  .res-time {
    font-size: 16px;
    font-weight: 700;
    color: #d4a053;
  }
  .res-date {
    font-size: 11px;
    color: #666;
    margin-top: 1px;
  }
  .res-dur {
    font-size: 10px;
    color: #555;
    margin-top: 3px;
  }
  .res-divider {
    width: 1px;
    background: rgba(255, 255, 255, 0.07);
    align-self: stretch;
    flex-shrink: 0;
  }
  .res-body {
    flex: 1;
    min-width: 0;
  }
  .res-name {
    font-size: 14px;
    font-weight: 600;
    color: #e0dbd0;
  }
  .res-meta {
    font-size: 12px;
    color: #666;
    margin-top: 3px;
    display: flex;
    align-items: center;
  }
  .res-notes {
    font-size: 12px;
    color: #888;
    margin-top: 6px;
    font-style: italic;
  }
  .res-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
  }

  /* ── Empty card ── */
  .empty-card {
    background: #1a1c1f;
    border: 1px dashed rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 40px;
    text-align: center;
    color: #555;
    font-size: 13px;
  }
  .empty-card p {
    margin-top: 10px;
    margin-bottom: 0;
  }

  /* ── Drawer panel ── */
  .drawer-panel {
    background: #15171b !important;
    border-left: 1px solid rgba(255, 255, 255, 0.07) !important;
  }
  .drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
  }
  .drawer-avatar {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .drawer-avatar.create {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
  }
  .drawer-avatar.edit {
    background: linear-gradient(135deg, #3498db, #2980b9);
  }
  .drawer-title {
    font-size: 15px;
    font-weight: 700;
    color: #e0dbd0;
  }
  .drawer-sub {
    font-size: 12px;
    color: #555;
    margin-top: 1px;
  }

  /* ── Form fields ── */
  .field-label {
    font-size: 12px;
    font-weight: 600;
    color: #777;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    text-transform: uppercase;
  }
  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 0;
  }

  .dark-field :deep(.v-field__outline__start),
  .dark-field :deep(.v-field__outline__notch),
  .dark-field :deep(.v-field__outline__end) {
    border-color: rgba(255, 255, 255, 0.12) !important;
  }
  .dark-field :deep(.v-field) {
    background: #1e2026;
    color: #e0dbd0;
    border-radius: 10px;
  }
  .dark-field :deep(input),
  .dark-field :deep(textarea) {
    color: #e0dbd0 !important;
  }
  .dark-field :deep(.v-label) {
    color: #666 !important;
  }

  /* ── Shape picker ── */
  .shape-picker {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
  }
  .shape-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    padding: 10px 6px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: #1e2026;
    color: #666;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
    text-transform: capitalize;
  }
  .shape-option.active {
    border-color: #d4a053;
    color: #d4a053;
    background: rgba(212, 160, 83, 0.1);
  }
  .shape-option:hover:not(.active) {
    color: #bbb;
    border-color: rgba(255, 255, 255, 0.2);
  }

  /* ── Status picker ── */
  .status-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .status-option {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: #1e2026;
    color: #666;
    cursor: pointer;
    text-transform: capitalize;
    transition: all 0.2s;
  }
  .status-option.active.status-opt-available {
    background: rgba(76, 175, 80, 0.18);
    border-color: #4caf50;
    color: #4caf50;
  }
  .status-option.active.status-opt-occupied {
    background: rgba(244, 67, 54, 0.18);
    border-color: #f44336;
    color: #f44336;
  }
  .status-option.active.status-opt-reserved {
    background: rgba(255, 152, 0, 0.18);
    border-color: #ff9800;
    color: #ff9800;
  }
  .status-option.active.status-opt-cleaning {
    background: rgba(33, 150, 243, 0.18);
    border-color: #2196f3;
    color: #2196f3;
  }
  .status-option.active.status-opt-inactive {
    background: rgba(120, 120, 120, 0.18);
    border-color: #888;
    color: #888;
  }
  .status-option.active.status-opt-confirmed {
    background: rgba(76, 175, 80, 0.18);
    border-color: #4caf50;
    color: #4caf50;
  }
  .status-option.active.status-opt-pending {
    background: rgba(255, 152, 0, 0.18);
    border-color: #ff9800;
    color: #ff9800;
  }
  .status-option.active.status-opt-cancelled {
    background: rgba(244, 67, 54, 0.18);
    border-color: #f44336;
    color: #f44336;
  }
  .status-option.active.status-opt-completed {
    background: rgba(33, 150, 243, 0.18);
    border-color: #2196f3;
    color: #2196f3;
  }
  .status-option.active.status-opt-seated {
    background: rgba(0, 188, 212, 0.18);
    border-color: #00bcd4;
    color: #00bcd4;
  }
  .status-option.active.status-opt-no_show {
    background: rgba(120, 120, 120, 0.18);
    border-color: #888;
    color: #888;
  }
  .status-option:hover:not(.active) {
    color: #bbb;
    border-color: rgba(255, 255, 255, 0.2);
  }

  /* ── Save button ── */
  .save-btn {
    background: linear-gradient(135deg, #d4a053, #b8872e) !important;
    color: #1a1408 !important;
    font-weight: 600 !important;
  }
</style>
