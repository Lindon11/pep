<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">Welcome Messages</h1>
        <p class="mt-1 text-sm text-slate-400">All welcome messages sent to new members.</p>
      </div>
      <button
        @click="loadMessages"
        :disabled="loading"
        class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-60"
      >
        {{ loading ? 'Loading...' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading && messages.length === 0" class="py-12 text-center text-sm text-slate-400">Loading...</div>

    <div v-else-if="messages.length === 0" class="py-12 text-center text-sm text-slate-400">No welcome messages yet.</div>

    <div v-else class="overflow-x-auto rounded-2xl border border-slate-700/50">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-700 bg-slate-800/50">
          <tr class="text-left text-slate-400">
            <th class="px-4 py-3">User</th>
            <th class="px-4 py-3">Username</th>
            <th class="px-4 py-3">Chat ID</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Sent At</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="msg in messages" :key="msg.id" class="border-b border-slate-800 last:border-0">
            <td class="px-4 py-3 font-medium text-white">{{ msg.first_name || 'Unknown' }}</td>
            <td class="px-4 py-3 text-slate-300">@{{ msg.username || '-' }}</td>
            <td class="px-4 py-3 text-slate-300"><code class="text-xs">{{ msg.chat_id }}</code></td>
            <td class="px-4 py-3">
              <span v-if="msg.error" class="rounded bg-red-500/20 px-2 py-1 text-xs font-medium text-red-400" :title="msg.error">Failed</span>
              <span v-else class="rounded bg-emerald-500/20 px-2 py-1 text-xs font-medium text-emerald-400">Sent</span>
            </td>
            <td class="px-4 py-3 text-slate-400">{{ formatDate(msg.created_at) }}</td>
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
const messages = ref([])
const pagination = ref(null)

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString()
}

async function loadMessages() {
  await loadPage(1)
}

async function loadPage(page) {
  loading.value = true
  try {
    const res = await api.get('/admin/peptide-vendors/welcome-messages', { params: { page, per_page: 20 } })
    const data = res.data
    if (data.data) {
      messages.value = data.data
      pagination.value = {
        current_page: data.current_page,
        last_page: data.last_page,
        prev_page_url: data.prev_page_url,
        next_page_url: data.next_page_url,
      }
    } else {
      messages.value = Array.isArray(data) ? data : []
      pagination.value = null
    }
  } catch (e) {
    toast.error('Failed to load welcome messages')
  } finally {
    loading.value = false
  }
}

onMounted(loadMessages)
</script>
