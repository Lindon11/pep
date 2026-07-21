import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FloatingChatWidget from '@/components/FloatingChatWidget.vue'
import api from '@/services/api'
import { websocketService } from '@/services/websocket'

// Mock dependencies
vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn()
  }
}))

vi.mock('@/services/websocket', () => ({
  websocketService: {
    subscribe: vi.fn(),
    unsubscribe: vi.fn(),
    on: vi.fn((event, callback) => {
      return vi.fn() // mock unsubscribe function
    })
  }
}))

// Mock Pinia store (if used)
vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(() => ({
    user: { id: 1, username: 'testuser' }
  }))
}))

// Mock PvIcon component to avoid warnings
const PvIconMock = {
  template: '<span class="pv-icon-mock"></span>'
}

describe('FloatingChatWidget', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders closed by default with open button', () => {
    const wrapper = mount(FloatingChatWidget, {
      global: { components: { PvIcon: PvIconMock } }
    })
    
    expect(wrapper.find('.pv-floating-btn').exists()).toBe(true)
    expect(wrapper.find('.pv-chat-window').exists()).toBe(false)
  })

  it('opens chat window when button is clicked and fetches messages', async () => {
    // Setup API mock
    ;(api.get as any).mockResolvedValue({
      data: { data: [{ id: 1, text: 'Hello', time: '12:00', sender: { username: 'user1' } }] }
    })

    const wrapper = mount(FloatingChatWidget, {
      global: { components: { PvIcon: PvIconMock } }
    })
    
    // Click open button
    await wrapper.find('.pv-floating-btn').trigger('click')
    
    // It should now show the window
    expect(wrapper.find('.pv-chat-window').exists()).toBe(true)
    expect(wrapper.find('.pv-floating-btn').exists()).toBe(false)
    
    // Check loading state
    expect(wrapper.find('.pv-chat-notice').text()).toBe('Loading comms...')
    
    // Wait for API resolution
    await flushPromises()
    
    // Should have called API for 'global' room
    expect(api.get).toHaveBeenCalledWith('/api/v1/community/chat/rooms/global')
    
    // Should have subscribed to websocket
    expect(websocketService.subscribe).toHaveBeenCalledWith('room.global')
    
    // Should display the message
    const messages = wrapper.findAll('.pv-chat-message')
    expect(messages.length).toBe(1)
    expect(messages[0].text()).toContain('Hello')
  })

  it('can send a message', async () => {
    ;(api.get as any).mockResolvedValue({ data: { data: [] } })
    ;(api.post as any).mockResolvedValue({})

    const wrapper = mount(FloatingChatWidget, {
      global: { components: { PvIcon: PvIconMock } }
    })
    
    // Open chat
    await wrapper.find('.pv-floating-btn').trigger('click')
    await flushPromises()
    
    // Type in input
    const input = wrapper.find('input[type="text"]')
    await input.setValue('Testing message')
    
    // Submit form
    await wrapper.find('form').trigger('submit.prevent')
    
    expect(api.post).toHaveBeenCalledWith('/api/v1/community/chat/rooms/global/messages', {
      body: 'Testing message'
    })
    
    // Input should be cleared
    expect((input.element as HTMLInputElement).value).toBe('')
  })
})
