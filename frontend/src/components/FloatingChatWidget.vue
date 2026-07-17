<template>
  <div class="pv-floating-chat">
    <button v-if="!isOpen" @click="toggleChat" class="pv-floating-btn" aria-label="Open Chat">
      <PvIcon name="radio-tower" />
    </button>
    <div v-else class="pv-chat-window">
      <header class="pv-chat-header">
        <div class="pv-chat-header-title">
          <PvIcon name="radio-tower" /> GLOBAL COMMS
        </div>
        <button class="pv-chat-close" @click="toggleChat" aria-label="Close Chat">
          <PvIcon name="close" />
        </button>
      </header>

      <nav class="pv-chat-tabs">
        <button 
          v-for="room in rooms" 
          :key="room.slug"
          :class="{ active: activeRoom === room.slug }"
          @click="switchRoom(room.slug)"
        >
          {{ room.name }}
        </button>
      </nav>

      <div class="pv-chat-messages" ref="messagesContainer">
        <div v-if="loading" class="pv-chat-notice">Loading comms...</div>
        <div v-else-if="error" class="pv-chat-notice error">{{ error }}</div>
        <div v-else-if="messages.length === 0" class="pv-chat-notice">No messages yet. Start the transmission.</div>
        
        <div v-for="msg in messages" :key="msg.id" class="pv-chat-message">
          <div class="pv-chat-message-header">
            <span class="pv-chat-sender" :class="{'system': !msg.sender}">{{ msg.sender ? msg.sender.username : 'SYSTEM' }}</span>
            <span class="pv-chat-time">{{ msg.time }}</span>
          </div>
          <div class="pv-chat-message-body">{{ msg.text }}</div>
        </div>
      </div>

      <form class="pv-chat-input-area" @submit.prevent="sendMessage">
        <input 
          type="text" 
          v-model="newMessage" 
          placeholder="ENTER TRANSMISSION..." 
          :disabled="loading || !!error"
          maxlength="1000"
        />
        <button type="submit" :disabled="!newMessage.trim() || loading || !!error" class="pv-chat-send-btn">
          <PvIcon name="send" />
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { websocketService } from '@/services/websocket'
import api from '@/services/api'
import PvIcon from '@/components/peptide/PvIcon.vue'
import { useAuthStore } from '@/stores/auth'

const isOpen = ref(false)
const loading = ref(false)
const error = ref('')
const activeRoom = ref('global')
const messages = ref<any[]>([])
const newMessage = ref('')
const messagesContainer = ref<HTMLElement | null>(null)

const rooms = [
  { slug: 'global', name: 'GLOBAL' },
  { slug: 'premium-lounge', name: 'PREMIUM' },
  { slug: 'vendors', name: 'VENDORS' },
]

let unsubscribe: (() => void) | null = null

const toggleChat = () => {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    switchRoom(activeRoom.value)
  } else {
    unsubscribeRoom()
  }
}

const switchRoom = async (slug: string) => {
  activeRoom.value = slug
  messages.value = []
  error.value = ''
  loading.value = true

  unsubscribeRoom()

  try {
    const res = await api.get(`/community/chat/rooms/${slug}`)
    messages.value = res.data.data || []
    scrollToBottom()
    subscribeRoom(slug)
  } catch (err: any) {
    if (err.response?.status === 403) {
      error.value = "ACCESS DENIED: Insufficient clearance for this channel."
    } else {
      error.value = "COMMUNICATION FAILURE: Unable to connect."
    }
  } finally {
    loading.value = false
  }
}

const subscribeRoom = (slug: string) => {
  const channel = `room.${slug}`
  websocketService.subscribe(channel)
  unsubscribe = websocketService.on('chat.message', (data: any, msg: any) => {
    if (msg.channel === channel) {
      messages.value.push(data.message)
      scrollToBottom()
    }
  })
}

const unsubscribeRoom = () => {
  if (unsubscribe) {
    unsubscribe()
    unsubscribe = null
  }
  rooms.forEach(r => websocketService.unsubscribe(`room.${r.slug}`))
}

const sendMessage = async () => {
  if (!newMessage.value.trim() || !!error.value) return
  
  const text = newMessage.value.trim()
  newMessage.value = ''
  
  try {
    // The optimistic update will be handled by the websocket, but we could add it optimistically too.
    // For now we'll wait for the websocket broadcast to append it.
    await api.post(`/community/chat/rooms/${activeRoom.value}/messages`, {
      body: text
    })
  } catch (err: any) {
    error.value = "FAILED TO SEND TRANSMISSION."
  }
}

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

onUnmounted(() => {
  unsubscribeRoom()
})
</script>

<style scoped>
.pv-floating-chat {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  font-family: Inter, Roboto, sans-serif;
}

.pv-floating-btn {
  background: linear-gradient(135deg, var(--pv-purple), var(--pv-blue));
  color: white;
  border: none;
  border-radius: 50%;
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 16px var(--pv-purple-soft);
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}
.pv-floating-btn:hover {
  transform: scale(1.08);
  box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
}
.pv-floating-btn svg {
  width: 28px;
  height: 28px;
}

.pv-chat-window {
  width: 340px;
  height: 480px;
  background-color: var(--pv-panel);
  backdrop-filter: blur(12px);
  border-radius: var(--pv-radius);
  border: 1px solid var(--pv-border);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.pv-chat-header {
  background-color: var(--pv-panel-strong);
  color: var(--pv-text);
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--pv-border);
}
.pv-chat-header-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 14px;
  letter-spacing: 0.5px;
  color: var(--pv-text);
}
.pv-chat-header-title svg {
  width: 18px;
  height: 18px;
}
.pv-chat-close {
  background: transparent;
  border: none;
  color: var(--pv-muted);
  cursor: pointer;
  padding: 4px;
}
.pv-chat-close:hover {
  color: var(--pv-text);
}

.pv-chat-tabs {
  display: flex;
  background-color: transparent;
  border-bottom: 1px solid var(--pv-border);
}
.pv-chat-tabs button {
  flex: 1;
  background: transparent;
  border: none;
  color: var(--pv-muted);
  padding: 10px 0;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: color 0.2s;
  border-bottom: 2px solid transparent;
}
.pv-chat-tabs button:hover {
  color: var(--pv-text);
}
.pv-chat-tabs button.active {
  color: var(--pv-blue);
  border-bottom-color: var(--pv-blue);
}

.pv-chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background-color: transparent;
}
.pv-chat-messages::-webkit-scrollbar {
  width: 6px;
}
.pv-chat-messages::-webkit-scrollbar-thumb {
  background: var(--pv-border);
  border-radius: 3px;
}

.pv-chat-notice {
  text-align: center;
  color: var(--pv-dim);
  font-size: 12px;
  margin-top: auto;
  margin-bottom: auto;
}
.pv-chat-notice.error {
  color: var(--pv-red);
}

.pv-chat-message {
  background-color: var(--pv-panel-muted);
  border: 1px solid var(--pv-border);
  border-radius: 6px;
  padding: 10px;
}
.pv-chat-message-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;
  font-size: 11px;
}
.pv-chat-sender {
  font-weight: 700;
  color: var(--pv-blue);
  text-transform: uppercase;
}
.pv-chat-sender.system {
  color: var(--pv-red);
}
.pv-chat-time {
  color: var(--pv-dim);
}
.pv-chat-message-body {
  color: var(--pv-text);
  font-size: 13px;
  line-height: 1.4;
  word-wrap: break-word;
}

.pv-chat-input-area {
  display: flex;
  padding: 12px;
  background-color: var(--pv-panel-strong);
  border-top: 1px solid var(--pv-border);
}
.pv-chat-input-area input {
  flex: 1;
  background-color: var(--pv-bg-soft);
  border: 1px solid var(--pv-border);
  border-radius: 20px;
  padding: 8px 16px;
  color: var(--pv-text);
  font-size: 13px;
  outline: none;
}
.pv-chat-input-area input:focus {
  border-color: var(--pv-purple);
}
.pv-chat-input-area input:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.pv-chat-send-btn {
  background: linear-gradient(135deg, var(--pv-purple), var(--pv-blue));
  border: none;
  color: white;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  margin-left: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}
.pv-chat-send-btn:disabled {
  background: var(--pv-border);
  color: var(--pv-muted);
  cursor: not-allowed;
}
.pv-chat-send-btn svg {
  width: 16px;
  height: 16px;
}
</style>
