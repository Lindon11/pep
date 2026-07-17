<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">Daily Questions</h1>
        <p class="mt-1 text-sm text-slate-400">The bot posts a random question to Peptides discussions each day.</p>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5">
      <h2 class="mb-4 text-lg font-semibold text-white">Add Question</h2>
      <div class="flex gap-3">
        <input v-model="newQuestion" @keyup.enter="addQuestion" placeholder="e.g. What's everyone running this week?" class="flex-1 rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder-slate-500"/>
        <button @click="addQuestion" :disabled="saving" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-500 disabled:opacity-60">
          {{ saving ? 'Adding...' : 'Add' }}
        </button>
      </div>
    </div>

    <div v-if="questions.length === 0" class="py-12 text-center text-sm text-slate-400">No questions yet. Add some above.</div>

    <div v-else class="space-y-3">
      <div v-for="q in questions" :key="q.id" class="flex items-center gap-3 rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4">
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white">{{ q.question }}</p>
          <p class="mt-1 text-xs text-slate-500">
            {{ q.enabled ? 'Active' : 'Disabled' }}
            <span v-if="q.last_posted_at"> &middot; Last posted {{ formatDate(q.last_posted_at) }}</span>
          </p>
        </div>
        <label class="flex cursor-pointer items-center gap-2">
          <span class="text-xs text-slate-400">On</span>
          <input type="checkbox" :checked="q.enabled" @change="toggleQuestion(q)" class="h-4 w-4 rounded border-slate-600 bg-slate-700 text-emerald-500 focus:ring-emerald-500"/>
        </label>
        <button @click="deleteQuestion(q.id)" class="rounded border border-red-700 px-2 py-1 text-xs text-red-400 hover:bg-red-500/20">Delete</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const questions = ref([])
const newQuestion = ref('')
const saving = ref(false)

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString()
}

async function loadQuestions() {
  try {
    const res = await api.get('/admin/peptide-vendors/questions')
    questions.value = res.data?.questions || []
  } catch (e) {
    toast.error('Failed to load')
  }
}

async function addQuestion() {
  if (!newQuestion.value.trim()) return
  saving.value = true
  try {
    await api.post('/admin/peptide-vendors/questions', { question: newQuestion.value.trim() })
    toast.success('Question added')
    newQuestion.value = ''
    await loadQuestions()
  } catch (e) {
    toast.error('Failed to add')
  } finally {
    saving.value = false
  }
}

async function toggleQuestion(q) {
  try {
    await api.put(`/admin/peptide-vendors/questions/${q.id}`, { enabled: !q.enabled })
    await loadQuestions()
  } catch (e) {
    toast.error('Failed to update')
  }
}

async function deleteQuestion(id) {
  if (!confirm('Delete this question?')) return
  try {
    await api.delete(`/admin/peptide-vendors/questions/${id}`)
    toast.success('Deleted')
    await loadQuestions()
  } catch (e) {
    toast.error('Failed to delete')
  }
}

onMounted(loadQuestions)
</script>
