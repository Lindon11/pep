<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">Peptide Vendors Bot</h1>
        <p class="mt-1 text-sm text-slate-400">Bot health, activity stats, and quick access to logs.</p>
      </div>
      <button
        @click="loadStatus"
        :disabled="loading"
        class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-60"
      >
        {{ loading ? 'Refreshing...' : 'Refresh' }}
      </button>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4">
        <p class="text-sm text-slate-400">Bot Status</p>
        <p class="mt-2 text-2xl font-bold" :class="status.bot_token_configured ? 'text-emerald-400' : 'text-red-400'">
          {{ status.bot_token_configured ? 'Configured' : 'No Token' }}
        </p>
        <p class="mt-1 text-xs text-slate-500">Webhook: {{ status.webhook_enabled ? 'ON' : 'OFF' }}</p>
      </div>
      <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4">
        <p class="text-sm text-slate-400">Welcome Messages</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ status.stats?.total_welcomes ?? 0 }}</p>
        <p class="mt-1 text-xs" :class="status.welcome_enabled ? 'text-emerald-400' : 'text-amber-400'">
          {{ status.welcome_enabled ? 'Active' : 'Disabled' }}
        </p>
      </div>
      <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4">
        <p class="text-sm text-slate-400">Webhook Updates</p>
        <p class="mt-2 text-3xl font-bold text-white">{{ status.stats?.total_updates ?? 0 }}</p>
        <p class="mt-1 text-xs text-slate-500">Total received</p>
      </div>
      <div class="rounded-2xl border border-sky-500/25 bg-sky-500/10 p-4">
        <p class="text-sm text-sky-200">Target Chat</p>
        <p class="mt-2 text-lg font-bold text-white truncate">{{ status.target_chat_id || 'Not set' }}</p>
        <p class="mt-1 text-xs text-sky-100/70">Telegram group ID</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <router-link to="/peptide-vendors/welcome-messages" class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5 transition hover:border-slate-500">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-white">Recent Welcome Messages</h2>
          <span class="text-sm text-slate-400">View all &rarr;</span>
        </div>
        <div v-if="recentWelcomes.length === 0" class="mt-4 text-sm text-slate-400">No welcomes yet.</div>
        <div v-else class="mt-4 space-y-2">
          <div v-for="welcome in recentWelcomes" :key="welcome.id" class="flex items-center justify-between rounded-xl bg-slate-900/60 px-3 py-2">
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ welcome.first_name || 'Unknown' }}</p>
              <p class="text-xs text-slate-500">@{{ welcome.username || 'no username' }}</p>
            </div>
            <span class="ml-3 text-xs text-slate-500">{{ formatDate(welcome.created_at) }}</span>
          </div>
        </div>
      </router-link>

      <router-link to="/peptide-vendors/webhook-updates" class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5 transition hover:border-slate-500">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-white">Recent Webhook Updates</h2>
          <span class="text-sm text-slate-400">View all &rarr;</span>
        </div>
        <div v-if="recentUpdates.length === 0" class="mt-4 text-sm text-slate-400">No updates yet.</div>
        <div v-else class="mt-4 space-y-2">
          <div v-for="update in recentUpdates" :key="update.id" class="flex items-center justify-between rounded-xl bg-slate-900/60 px-3 py-2">
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ update.type || '-' }}</p>
              <p class="text-xs text-slate-500">Chat: {{ update.chat_id || '-' }}</p>
            </div>
            <span class="ml-3 text-xs text-slate-500">{{ formatDate(update.created_at) }}</span>
          </div>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const loading = ref(false)
const status = ref({ bot_token_configured: false, webhook_enabled: false, stats: {} })
const recentWelcomes = ref([])
const recentUpdates = ref([])

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString()
}

async function loadStatus() {
  loading.value = true
  try {
    const [statusRes, welcomesRes, updatesRes] = await Promise.allSettled([
      api.get('/admin/peptide-vendors/status'),
      api.get('/admin/peptide-vendors/welcome-messages', { params: { per_page: 5 } }),
      api.get('/admin/peptide-vendors/webhook-updates', { params: { per_page: 5 } }),
    ])

    if (statusRes.status === 'fulfilled') {
      status.value = statusRes.value.data || {}
    }
    if (welcomesRes.status === 'fulfilled') {
      recentWelcomes.value = welcomesRes.value.data?.data || welcomesRes.value.data || []
    }
    if (updatesRes.status === 'fulfilled') {
      recentUpdates.value = updatesRes.value.data?.data || updatesRes.value.data || []
    }
  } catch (e) {
    toast.error('Failed to load Peptide Vendors status')
  } finally {
    loading.value = false
  }
}

onMounted(loadStatus)
</script>
