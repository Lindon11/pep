<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">Verified Members</h1>
        <p class="mt-1 text-sm text-slate-400">Members who have agreed to the rules and can now post.</p>
      </div>
      <button @click="loadVerifications" class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">
        Refresh
      </button>
    </div>

    <div v-if="verified.length === 0" class="py-12 text-center text-sm text-slate-400">No members have verified yet.</div>

    <div v-else class="overflow-x-auto rounded-2xl border border-slate-700/50">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-700 bg-slate-800/50">
          <tr class="text-left text-slate-400">
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Username</th>
            <th class="px-4 py-3">User ID</th>
            <th class="px-4 py-3">Verified At</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in verified" :key="v.id" class="border-b border-slate-800 last:border-0">
            <td class="px-4 py-3 font-medium text-white">{{ v.user_name || 'Unknown' }}</td>
            <td class="px-4 py-3 text-slate-300">@{{ v.username || '-' }}</td>
            <td class="px-4 py-3 text-slate-400"><code class="text-xs">{{ v.user_id }}</code></td>
            <td class="px-4 py-3 text-slate-400">{{ formatDate(v.agreed_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const verified = ref([])

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString()
}

async function loadVerifications() {
  try {
    const res = await api.get('/admin/peptide-vendors/verifications')
    verified.value = res.data?.verified || []
  } catch (e) {
    toast.error('Failed to load')
  }
}

onMounted(loadVerifications)
</script>
