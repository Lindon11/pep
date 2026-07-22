<template>
  <div class="pv-floating-chat" :class="{ 'pv-floating-chat--open': isOpen }">
    <button v-if="!isOpen" @click="toggleChat" class="pv-floating-btn" aria-label="Open Chat">
      <PvIcon name="radio-tower" />
    </button>
    <div v-else class="pv-chat-window">
      <header class="pv-chat-header">
        <div class="pv-chat-header-title">
          <PvIcon name="message-square" />
          <span>Global Chat</span>
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

      <div class="pv-chat-messages" ref="messagesContainer" @scroll="handleScroll">
        <div v-if="loadingMore" class="pv-chat-notice loading-more">Loading older messages...</div>
        <div v-if="loading" class="pv-chat-notice">Loading messages...</div>
        <div v-else-if="error" class="pv-chat-error">{{ error }}</div>
        <div v-else-if="messages.length === 0" class="pv-chat-notice">No messages yet. Start the conversation.</div>
        
        <div v-for="msg in messages" :key="msg.id" class="pv-chat-message">
          <div class="pv-chat-message-header">
            <span class="pv-chat-sender" :class="{'system': !msg.sender}">{{ msg.sender ? (msg.sender.username || msg.sender.name) : 'SYSTEM' }}</span>
            <span class="pv-chat-time">{{ msg.time }}</span>
          </div>
          <div class="pv-chat-message-body">{{ msg.text || msg.body }}</div>
        </div>
      </div>

      <form class="pv-chat-input-area" @submit.prevent="sendMessage">
        <input 
          type="text" 
          v-model="newMessage" 
          placeholder="Type your message..." 
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
import { ref, computed, onUnmounted, nextTick } from 'vue'
import { websocketService } from '@/services/websocket'
import api from '@/services/api'
import PvIcon from '@/components/peptide/PvIcon.vue'
import { useAuthStore } from '@/stores/auth'
import { hasAnyRole } from '@/composables/usePermission'

export interface ChatUser {
  id: number
  name?: string
  username: string
  avatar_url?: string
  color?: string
  initial?: string
  role?: string
}

export interface ChatMessage {
  id: number
  room?: string
  text?: string
  body?: string
  time?: string
  sent_at?: string
  created_at?: string
  sender?: ChatUser | null
}

export interface ChatApiResponse {
  data: ChatMessage[]
  next_cursor?: string | null
  prev_cursor?: string | null
  has_more?: boolean
  per_page?: number
  meta?: {
    next_cursor?: string | null
    prev_cursor?: string | null
    has_more?: boolean
    per_page?: number
  }
}

export interface PostMessageResponse {
  data: ChatMessage
}

export interface Room {
  slug: string
  name: string
}

const isOpen = ref(false)
const loading = ref(false)
const loadingMore = ref(false)
const next_cursor = ref<string | null>(null)
const hasMore = ref(false)
const error = ref('')
const activeRoom = ref('global')
const messages = ref<ChatMessage[]>([])
const newMessage = ref('')
const messagesContainer = ref<HTMLElement | null>(null)

const authStore = useAuthStore()

const isAdmin = computed(() => hasAnyRole(['admin']))

const rooms = computed<Room[]>(() => {
  const allRooms: Room[] = [
    { slug: 'global', name: 'GLOBAL' },
    { slug: 'premium-lounge', name: 'PREMIUM' },
    { slug: 'vendors', name: 'VENDORS' },
  ]
  return allRooms.filter(room => {
    if (room.slug === 'global') return true
    if (room.slug === 'premium-lounge') return authStore.user?.tier === 'premium' || isAdmin.value
    if (room.slug === 'vendors') return authStore.user?.is_approved_vendor || isAdmin.value
    return false
  })
})

let unsubscribe: (() => void) | null = null

const toggleChat = () => {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    switchRoom(activeRoom.value)
  } else {
    unsubscribeRoom()
  }
}

const parseMessageResponse = (
  resData: any
): { msgs: ChatMessage[]; nextCursor: string | null; hasMoreMessages: boolean } => {
  let rawMsgs: ChatMessage[] = []
  let nextCursor: string | null = null
  let hasMoreMessages = false

  if (Array.isArray(resData)) {
    rawMsgs = resData
  } else if (resData && typeof resData === 'object') {
    rawMsgs = Array.isArray(resData.data) ? resData.data : []
    nextCursor = resData.next_cursor ?? resData.meta?.next_cursor ?? null

    if (typeof resData.has_more === 'boolean') {
      hasMoreMessages = resData.has_more
    } else if (typeof resData.meta?.has_more === 'boolean') {
      hasMoreMessages = resData.meta.has_more
    } else {
      hasMoreMessages = Boolean(nextCursor)
    }
  }

  const msgs = [...rawMsgs]
  if (msgs.length > 1) {
    const firstId = Number(msgs[0]?.id)
    const lastId = Number(msgs[msgs.length - 1]?.id)
    if (!isNaN(firstId) && !isNaN(lastId) && firstId > lastId) {
      msgs.reverse()
    }
  }

  return { msgs, nextCursor, hasMoreMessages }
}

const switchRoom = async (slug: string) => {
  activeRoom.value = slug
  messages.value = []
  next_cursor.value = null
  hasMore.value = false
  loadingMore.value = false
  error.value = ''
  loading.value = true

  unsubscribeRoom()

  try {
    const res = await api.get<ChatApiResponse | ChatMessage[]>(`/api/v1/community/chat/rooms/${slug}`)
    const { msgs, nextCursor, hasMoreMessages } = parseMessageResponse(res.data)
    messages.value = msgs
    next_cursor.value = nextCursor
    hasMore.value = hasMoreMessages
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

const fetchOlderMessages = async () => {
  if (!hasMore.value || loadingMore.value || !next_cursor.value || loading.value) return

  loadingMore.value = true
  const container = messagesContainer.value
  const previousScrollHeight = container ? container.scrollHeight : 0

  try {
    const res = await api.get<ChatApiResponse | ChatMessage[]>(`/api/v1/community/chat/rooms/${activeRoom.value}`, {
      params: { cursor: next_cursor.value }
    })
    const { msgs: olderMsgs, nextCursor, hasMoreMessages } = parseMessageResponse(res.data)

    if (olderMsgs.length > 0) {
      const existingIds = new Set(messages.value.map(m => m.id))
      const newOlderMsgs = olderMsgs.filter(m => !existingIds.has(m.id))
      messages.value = [...newOlderMsgs, ...messages.value]
    }

    next_cursor.value = nextCursor
    hasMore.value = hasMoreMessages

    await nextTick()
    if (container && previousScrollHeight > 0) {
      container.scrollTop = container.scrollHeight - previousScrollHeight
    }
  } catch (err: any) {
    console.error('Failed to fetch older chat messages:', err)
  } finally {
    loadingMore.value = false
  }
}

const handleScroll = (e: Event) => {
  const target = e.target as HTMLElement
  if (target && target.scrollTop <= 10 && hasMore.value && !loadingMore.value && !loading.value) {
    fetchOlderMessages()
  }
}

const subscribeRoom = (slug: string) => {
  const channel = `room.${slug}`
  websocketService.subscribe(channel)
  unsubscribe = websocketService.on('chat.message', (data: any, msg: any) => {
    const targetChannel = msg?.channel || channel
    if (targetChannel === channel) {
      const incomingMsg: ChatMessage = data?.message || data
      if (incomingMsg && incomingMsg.id != null) {
        if (!messages.value.some(m => m.id === incomingMsg.id)) {
          const container = messagesContainer.value
          const isNearBottom = container
            ? container.scrollHeight - container.scrollTop - container.clientHeight <= 80
            : true

          messages.value.push(incomingMsg)

          if (isNearBottom) {
            scrollToBottom()
          }
        }
      }
    }
  })
}

const unsubscribeRoom = () => {
  if (unsubscribe) {
    unsubscribe()
    unsubscribe = null
  }
  rooms.value.forEach((r: Room) => websocketService.unsubscribe(`room.${r.slug}`))
}

const sendMessage = async () => {
  if (!newMessage.value.trim() || !!error.value) return

  const text = newMessage.value.trim()
  newMessage.value = ''

  try {
    const res = await api.post<PostMessageResponse | { data: ChatMessage }>(`/api/v1/community/chat/rooms/${activeRoom.value}/messages`, {
      body: text
    })
    const postedMsg: ChatMessage | undefined = (res.data as any)?.data || (res.data as any)
    if (postedMsg && postedMsg.id != null) {
      if (!messages.value.some(m => m.id === postedMsg.id)) {
        messages.value.push(postedMsg)
        scrollToBottom()
      }
    }
  } catch (err: any) {
    error.value = "Failed to send message."
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
  max-width: calc(100vw - 32px);
  max-height: calc(100vh - 32px);
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
  display: flex !important;
  grid-template-columns: none;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--pv-border);
}
.pv-chat-header-title {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 14px;
  letter-spacing: 0.5px;
  color: var(--pv-text);
}
.pv-chat-header-title span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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
  min-height: 0;
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
.pv-chat-notice.loading-more {
  margin-top: 0;
  margin-bottom: 4px;
}
.pv-chat-notice.error {
  color: var(--pv-red);
}

.pv-chat-message {
  min-width: 0;
  background-color: var(--pv-panel-muted);
  border: 1px solid var(--pv-border);
  border-radius: 6px;
  padding: 10px;
}
.pv-chat-message-header {
  display: flex;
  gap: 10px;
  justify-content: space-between;
  margin-bottom: 6px;
  font-size: 11px;
}
.pv-chat-sender {
  min-width: 0;
  font-weight: 700;
  color: var(--pv-blue);
  text-transform: uppercase;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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
  align-items: center;
  padding: 12px;
  background-color: var(--pv-panel-strong);
  border-top: 1px solid var(--pv-border);
}
.pv-chat-input-area input {
  flex: 1;
  min-width: 0;
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

@media (max-width: 640px) {
  .pv-floating-chat {
    right: 14px;
    bottom: calc(14px + env(safe-area-inset-bottom, 0px));
  }

  .pv-floating-chat--open {
    top: var(--pv-topbar, 116px);
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    padding: 8px 10px calc(10px + env(safe-area-inset-bottom, 0px));
    background: rgba(3, 6, 12, 0.68);
    backdrop-filter: blur(10px);
  }

  .pv-floating-btn {
    width: 54px;
    height: 54px;
  }

  .pv-chat-window {
    width: 100%;
    height: 100%;
    max-width: none;
    max-height: none;
    border-radius: 14px;
  }

  .pv-chat-header {
    min-height: 52px;
    padding: 10px 12px;
  }

  .pv-chat-header-title {
    font-size: 13px;
    letter-spacing: 0.2px;
  }

  .pv-chat-close {
    width: 36px;
    height: 36px;
    display: inline-grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 8px;
  }

  .pv-chat-tabs button {
    min-height: 40px;
    padding: 0 6px;
    font-size: 10px;
    letter-spacing: 0.2px;
  }

  .pv-chat-messages {
    padding: 12px;
    gap: 10px;
  }

  .pv-chat-message {
    padding: 10px;
  }

  .pv-chat-message-header {
    font-size: 10px;
  }

  .pv-chat-message-body {
    font-size: 12.5px;
    line-height: 1.38;
  }

  .pv-chat-input-area {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 42px;
    gap: 8px;
    padding: 10px;
  }

  .pv-chat-input-area input {
    min-height: 42px;
    padding: 9px 14px;
    font-size: 13px;
  }

  .pv-chat-send-btn {
    width: 42px;
    height: 42px;
    margin-left: 0;
  }
}

@media (max-width: 360px) {
  .pv-floating-chat--open {
    padding-inline: 8px;
  }
}
</style>
