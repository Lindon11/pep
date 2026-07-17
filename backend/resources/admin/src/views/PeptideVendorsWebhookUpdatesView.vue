<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">Webhook Logs</h1>
        <p class="mt-1 text-sm text-slate-400">All incoming Telegram webhook updates.</p>
      </div>
      <button
        @click="loadUpdates"
        :disabled="loading"
        class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-60"
      >
        {{ loading ? 'Loading...' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading && updates.length === 0" class="py-12 text-center text-sm text-slate-400">Loading...</div>

    <div v-else-if="updates.length === 0" class="py-12 text-center text-sm text-slate-400">No webhook updates yet.</div>

    <div v-else class="overflow-x-auto rounded-2xl border border-slate-700/50">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-700 bg-slate-800/50">
          <tr class="text-left text-slate-400">
            <th class="px-4 py-3">Type</th>
            <th class="px-4 py-3">Chat ID</th>
            <th class="px-4 py-3">Update ID</th>
            <th class="px-4 py-3">Received At</th>
            <th class="px-4 py-3">Payload</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="update in updates" :key="update.id" class="border-b border-slate-800 last:border-0">
            <td class="px-4 py-3">
              <span
                class="rounded px-2 py-1 text-xs font-medium"
                :class="typeClass(update.type)"
              >
                {{ update.type || '-' }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-300"><code class="text-xs">{{ update.chat_id || '-' }}</code></td>
            <td class="px-4 py-3 text-slate-400"><code class="text-xs">{{ update.update_id || '-' }}</code></td>
            <td class="px-4 py-3 text-slate-400">{{ formatDate(update.created_at) }}</td>
            <td class="px-4 py-3">
              <button
                @click="togglePayload(update.id)"
                class="rounded border border-slate-700 px-2 py-1 text-xs text-slate-400 hover:text-white"
              >
                {{ expandedPayload === update.id ? 'Hide' : 'View' }}
              </button>
            </td>
          </tr>
          <tr v-if="expandedPayload">
            <td colspan="5" class="bg-slate-900/60 px-4 py-3">
              <pre class="max-h-60 overflow-auto rounded bg-slate-950 p-3 text-xs text-slate-300">{{ formatPayload(expandedPayload) }}</pre>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination" class="flex items-center justify-between">
      <p class="text-sm text-slate-400">Page {{ pagination.current_page }} of {{ pagination.last_page }}</p>
      <div class="flex gap-2">
        <button
          :disabled="!pagination.prev_page_url"
          @click="loadPage(pagination.current_page - 1)"
          class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-40"
        >
          Previous
        </button>
        <button
          :disabled="!pagination.next_page_url"
          @click="loadPage(pagination.current_page + 1)"
          class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-40"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const loading = ref(false)
const updates = ref([])
const pagination = ref(null)
const expandedPayload = ref(null)

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString()
}

function typeClass(type) {
  const map = {
    message: 'bg-emerald-500/20 text-emerald-400',
    callback_query: 'bg-sky-500/20 text-sky-400',
    my_chat_member: 'bg-amber-500/20 text-amber-400',
    chat_member: 'bg-purple-500/20 text-purple-400',
  }
  return map[type] || 'bg-slate-700 text-slate-300'
}

function togglePayload(id) {
  if (expandedPayload.value === id) {
    expandedPayload.value = null
    return
  }
  const update = updates.value.find(u => u.id === id)
  expandedPayload.value = update || null
}

function formatPayload(update) {
  if (!update || !update.payload) return 'No payload'
  try {
    return JSON.stringify(update.payload, null, 2)
  } catch {
    return String(update.payload)
  }
}

async function loadUpdates() {
  await loadPage(1)
}

async function loadPage(page) {
  loading.value = true
  expandedPayload.value = null
  try {
    const res = await api.get('/admin/peptide-vendors/webhook-updates', { params: { page, per_page: 20 } })
    const data = res.data
    if (data.data) {
      updates.value = data.data
      pagination.value = {
        current_page: data.current_page,
        last_page: data.last_page,
        prev_page_url: data.prev_page_url,
        next_page_url: data.next_page_url,
      }
    } else {
      updates.value = Array.isArray(data) ? data : []
      pagination.value = null
    }
  } catch (e) {
    toast.error('Failed to load webhook updates')
  } finally {
    loading.value = false
  }
}

onMounted(loadUpdates)
</script>
