<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">Topic Permissions</h1>
        <p class="mt-1 text-sm text-slate-400">Restrict topics so only allowed users can post. Others' messages are auto-deleted.</p>
      </div>
      <button @click="loadAll" :disabled="loading" class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-60">
        {{ loading ? 'Loading...' : 'Refresh' }}
      </button>
    </div>

    <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5">
      <h2 class="mb-4 text-lg font-semibold text-white">Add Allowed User</h2>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div>
          <label class="mb-1 block text-xs text-slate-400">Topic</label>
          <select v-model="form.topic_id" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
            <option value="" disabled>Select a topic...</option>
            <option v-for="topic in forumTopics" :key="topic.topic_id" :value="topic.topic_id">
              {{ topic.name }} (ID: {{ topic.topic_id }})
            </option>
            <option disabled>──────────</option>
            <option value="custom">Custom ID...</option>
          </select>
          <input v-if="form.topic_id === 'custom'" v-model="customTopicId" type="number" placeholder="Enter topic ID" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder-slate-500"/>
        </div>
        <div>
          <label class="mb-1 block text-xs text-slate-400">User ID</label>
          <input v-model="form.user_id" placeholder="Telegram user ID" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder-slate-500"/>
        </div>
        <div>
          <label class="mb-1 block text-xs text-slate-400">User Name (optional)</label>
          <input v-model="form.user_name" placeholder="e.g. VendorName" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder-slate-500"/>
        </div>
        <div>
          <label class="mb-1 block text-xs text-slate-400">Topic Name (optional)</label>
          <input v-model="form.topic_name" :placeholder="selectedTopicName || 'e.g. Sellers Topic'" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder-slate-500"/>
        </div>
      </div>
      <button @click="addPermission" :disabled="saving" class="mt-4 rounded-xl bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-500 disabled:opacity-60">
        {{ saving ? 'Adding...' : 'Add Permission' }}
      </button>
    </div>

    <div v-if="topics.length === 0 && !loading" class="py-12 text-center text-sm text-slate-400">No restricted topics yet. Add one above.</div>

    <div v-for="topic in topics" :key="topic.topic_id" class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold text-white">{{ topic.topic_name || 'Topic ' + topic.topic_id }}</h3>
          <p class="text-sm text-slate-400">Topic ID: {{ topic.topic_id }} &middot; {{ topic.allowed_users.length }} allowed user(s)</p>
        </div>
        <button @click="clearTopic(topic.topic_id)" class="rounded border border-red-700 px-3 py-1 text-xs text-red-400 hover:bg-red-500/20">Clear All</button>
      </div>

      <div v-if="topic.allowed_users.length" class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-slate-700 text-slate-400">
            <tr class="text-left">
              <th class="py-2 pr-4">User ID</th>
              <th class="py-2 pr-4">Name</th>
              <th class="py-2 pr-4">Added</th>
              <th class="py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in topic.allowed_users" :key="user.id" class="border-b border-slate-800 last:border-0">
              <td class="py-2 pr-4 text-slate-300"><code class="text-xs">{{ user.user_id }}</code></td>
              <td class="py-2 pr-4 text-white">{{ user.user_name || '-' }}</td>
              <td class="py-2 pr-4 text-slate-400">{{ formatDate(user.added_at) }}</td>
              <td class="py-2">
                <button @click="removePermission(user.id)" class="rounded border border-red-700 px-2 py-1 text-xs text-red-400 hover:bg-red-500/20">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="mt-2 text-sm text-slate-500">No users allowed yet.</div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const loading = ref(false)
const saving = ref(false)
const topics = ref([])
const forumTopics = ref([])
const customTopicId = ref('')
const form = ref({ topic_id: '', user_id: '', user_name: '', topic_name: '' })

const selectedTopicName = computed(() => {
  if (!form.value.topic_id || form.value.topic_id === 'custom') return ''
  const found = forumTopics.value.find(t => t.topic_id === parseInt(form.value.topic_id))
  return found?.name || ''
})

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString()
}

async function loadAll() {
  loading.value = true
  try {
    const [permRes, topicsRes] = await Promise.allSettled([
      api.get('/admin/peptide-vendors/permissions'),
      api.get('/admin/peptide-vendors/topics'),
    ])
    if (permRes.status === 'fulfilled') {
      topics.value = permRes.value.data?.topics || []
    }
    if (topicsRes.status === 'fulfilled') {
      forumTopics.value = topicsRes.value.data?.topics || []
    }
  } catch (e) {
    toast.error('Failed to load data')
  } finally {
    loading.value = false
  }
}

async function addPermission() {
  const topicId = form.value.topic_id === 'custom' ? customTopicId.value : form.value.topic_id
  if (!topicId || !form.value.user_id) {
    toast.error('Topic and User ID are required')
    return
  }
  saving.value = true
  try {
    const topicName = form.value.topic_name || selectedTopicName.value || null
    await api.post('/admin/peptide-vendors/permissions', {
      topic_id: parseInt(topicId),
      user_id: form.value.user_id,
      user_name: form.value.user_name || null,
      topic_name: topicName,
    })
    toast.success('Permission added')
    form.value = { topic_id: '', user_id: '', user_name: '', topic_name: '' }
    customTopicId.value = ''
    await loadAll()
  } catch (e) {
    toast.error(e.response?.data?.error || 'Failed to add permission')
  } finally {
    saving.value = false
  }
}

async function removePermission(id) {
  if (!confirm('Remove this user from the topic?')) return
  try {
    await api.delete(`/admin/peptide-vendors/permissions/${id}`)
    toast.success('User removed')
    await loadAll()
  } catch (e) {
    toast.error('Failed to remove')
  }
}

async function clearTopic(topicId) {
  if (!confirm(`Remove all users from this topic? Members will then be able to post freely.`)) return
  try {
    await api.post('/admin/peptide-vendors/permissions/clear-topic', { topic_id: topicId })
    toast.success('Topic permissions cleared')
    await loadAll()
  } catch (e) {
    toast.error('Failed to clear')
  }
}

onMounted(loadAll)
</script>
