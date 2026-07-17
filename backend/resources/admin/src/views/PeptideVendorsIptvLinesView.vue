<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">IPTV Lines</h1>
        <p class="mt-1 text-sm text-slate-400">Manage all IPTV lines, search, and assign new credentials.</p>
      </div>
      <div class="flex gap-2">
        <button @click="loadLines" :disabled="loading" class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-60">
          {{ loading ? "Loading..." : "Refresh" }}
        </button>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1" style="min-width:200px;max-width:320px">
        <input v-model="search" @input="onSearchInput" type="text" placeholder="Search by username, telegram, owner..." class="w-full rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-2.5 pl-10 text-sm text-white placeholder-slate-500 focus:border-sky-500 focus:outline-none" />
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      <select v-model="sortBy" @change="loadLines" class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
        <option value="linked">Linked first</option>
        <option value="expire_asc">Expiring soonest</option>
        <option value="expire_desc">Expiring latest</option>
        <option value="recent">Most recent</option>
      </select>
      <select v-model="dmFilter" @change="loadLines" class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
        <option value="">All DM</option>
        <option value="1">Can DM</option>
        <option value="0">No DM</option>
      </select>
    </div>

    <div v-if="loading && lines.length === 0" class="py-12 text-center text-sm text-slate-400">Loading...</div>
    <div v-else-if="lines.length === 0" class="py-12 text-center text-sm text-slate-400">No IPTV lines found.</div>

    <div v-else class="overflow-x-auto rounded-2xl border border-slate-700/50">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-700 bg-slate-800/50">
          <tr class="text-left text-slate-400">
            <th class="px-4 py-3">Old Username</th>
            <th class="px-4 py-3">Telegram</th>
            <th class="px-4 py-3">Expires</th>
            <th class="px-4 py-3">New Username</th>
            <th class="px-4 py-3">New Password</th>
            <th class="px-4 py-3">DM</th>
            <th class="px-4 py-3">Last Notified</th>
            <th class="px-4 py-3">Owner</th>
            <th class="px-4 py-3">Linked At</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="line in lines" :key="line.id" class="border-b border-slate-800 last:border-0 hover:bg-slate-800/30">
            <td class="px-4 py-3 font-medium text-white">{{ line.old_username }}</td>
            <td class="px-4 py-3 text-slate-300">
              <span v-if="line.telegram_username && line.telegram_username.startsWith('@')" class="text-sky-300">{{ line.telegram_username }}</span>
              <span v-else-if="line.telegram_username" class="text-sky-300">@{{ line.telegram_username }}</span>
              <span v-else-if="line.telegram_user_id" class="text-xs text-sky-300">ID: {{ line.telegram_user_id }}</span>
              <span v-else class="text-slate-500">--</span>
            </td>
            <td class="px-4 py-3" :class="expiringClass(line.expire_date)">{{ line.expire_date || "--" }}</td>
            <td class="px-4 py-3">
              <input v-model="edits[line.id].new_username" @input="markDirty(line)" type="text" placeholder="--" class="w-28 rounded-lg border border-slate-700 bg-slate-900/60 px-2 py-1 text-xs text-white placeholder-slate-600 focus:border-sky-500 focus:outline-none" />
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <input v-model="edits[line.id].new_password" @input="markDirty(line)" type="text" placeholder="--" class="w-28 rounded-lg border border-slate-700 bg-slate-900/60 px-2 py-1 text-xs text-white placeholder-slate-600 focus:border-sky-500 focus:outline-none" />
                <button @click="saveLine(line)" v-if="edits[line.id].dirty" class="rounded-lg bg-emerald-500/20 px-3 py-1.5 text-xs font-semibold text-emerald-400 hover:bg-emerald-500/30 border border-emerald-500/30 flex items-center gap-1 whitespace-nowrap">
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  Save &amp; Notify
                </button>
              </div>
            </td>
            <td class="px-4 py-3">
              <span v-if="line.telegram_user_id && line.can_dm" class="inline-flex items-center gap-1 rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs font-medium text-emerald-400">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                DM
              </span>
              <span v-else-if="line.telegram_user_id" class="inline-flex items-center gap-1 rounded-full bg-red-500/20 px-2 py-0.5 text-xs font-medium text-red-400">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                No DM
              </span>
              <span v-else class="text-slate-500 text-xs">--</span>
            </td>
            <td class="px-4 py-3 text-slate-400 text-xs">
              <span v-if="line.last_notified_at" :title="line.last_notified_message">{{ formatDate(line.last_notified_at) }}</span>
              <span v-else class="text-slate-500">--</span>
            </td>
            <td class="px-4 py-3 text-slate-400">{{ line.owner || "--" }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(line.linked_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination" class="flex items-center justify-between">
      <p class="text-sm text-slate-400">Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total)</p>
      <div class="flex gap-2">
        <button :disabled="!pagination.prev_page_url" @click="loadPage(pagination.current_page - 1)" class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-40">Previous</button>
        <button :disabled="!pagination.next_page_url" @click="loadPage(pagination.current_page + 1)" class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-40">Next</button>
      </div>
    </div>

    <Transition name="fade">
      <div v-if="toast" class="fixed bottom-6 right-6 rounded-xl bg-slate-800 px-5 py-3 text-sm text-white shadow-lg border border-slate-700 z-50">{{ toast }}</div>
    </Transition>
  </div>
</template>

<script setup>
import { onMounted, ref, reactive } from "vue"
import api from "@/services/api"

const loading = ref(false)
const lines = ref([])
const pagination = ref(null)
const search = ref("")
const sortBy = ref("linked")
const dmFilter = ref("")
const toast = ref("")
let searchTimeout = null
const edits = reactive({})

function toastMsg(msg) {
  toast.value = msg
  setTimeout(() => { toast.value = "" }, 3000)
}

function formatDate(value) {
  if (!value) return "--"
  return new Date(value).toLocaleString()
}

function expiringClass(dateStr) {
  if (!dateStr) return "text-slate-300"
  const m = dateStr.match(/(\d+)\s*days?\s*left/)
  if (m && parseInt(m[1]) <= 60) return "text-amber-400"
  return "text-slate-300"
}

function markDirty(line) {
  if (edits[line.id]) edits[line.id].dirty = true
}

function buildParams(page) {
  const p = { page, per_page: 50 }
  if (search.value.trim()) p.search = search.value.trim()
  if (sortBy.value) p.sort_by = sortBy.value
  if (dmFilter.value !== "") p.can_dm = dmFilter.value
  return p
}

function onSearchInput() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadLines(), 400)
}

function initEdits() {
  for (const line of lines.value) {
    if (!edits[line.id]) {
      edits[line.id] = reactive({
        new_username: line.new_username || "",
        new_password: line.new_password || "",
        dirty: false,
      })
    }
  }
}

async function loadLines() {
  await loadPage(1)
}

async function loadPage(page) {
  loading.value = true
  try {
    const res = await api.get("/admin/peptide-vendors/iptv-lines", { params: buildParams(page) })
    const data = res.data
    if (data.data) {
      lines.value = data.data
      pagination.value = {
        current_page: data.current_page,
        last_page: data.last_page,
        prev_page_url: data.prev_page_url,
        next_page_url: data.next_page_url,
        total: data.total,
      }
    } else {
      lines.value = Array.isArray(data) ? data : []
      pagination.value = null
    }
    initEdits()
  } catch (e) {
    toastMsg("Failed to load IPTV lines")
  } finally {
    loading.value = false
  }
}

async function saveLine(line) {
  const edit = edits[line.id]
  if (!edit) return
  try {
    await api.put("/admin/peptide-vendors/iptv-lines/" + line.id, {
      new_username: edit.new_username,
      new_password: edit.new_password,
    })
    edit.dirty = false
    toastMsg("Saved and user notified via Telegram")
  } catch (e) {
    toastMsg("Failed to save")
  }
}

onMounted(loadLines)
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
