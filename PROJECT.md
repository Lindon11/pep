# Project: Chat System Refactoring (Enterprise Quality)

## Architecture
- **Backend Framework**: Laravel (PHP) inside Docker container `laravel_app` / `laravelcp_backend`.
- **Frontend Framework**: Vue 3 + TypeScript inside Docker container `laravelcp_frontend`.
- **Cache/Store**: Redis via Laravel `Redis` facade directly (`Redis::lpush`, `Redis::ltrim`, `Redis::lrange`, `Redis::hset`, `Redis::hdel`, `Redis::hgetall`).
- **Real-time Engine**: `WebSocketService` broadcasting Staff Chat (`staff-chat` channel) and Community Rooms (`room.{slug}` channel) events.
- **Data Access**: Eloquent models with cursor pagination (`cursorPaginate()`) for historical message retrieval.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Exploration & Analysis | Map codebase, identify WebSocketService, StaffChatController, Community Rooms, Vue components, tests | None | DONE |
| 2 | Cache Race Condition Fix | Refactor WebSocketService to use Redis atomic operations (lpush, ltrim, hset, hdel) + unit tests | M1 | DONE |
| 3 | Staff Chat WebSockets & Schema Clean | Broadcast new messages in StaffChatController via WebSocketService, remove dynamic Schema checks + feature tests | M1 | DONE |
| 4 | Cursor Pagination (Backend & Frontend) | Implement cursor pagination in Staff Chat & Community Rooms backend API and Vue components (FloatingChatWidget.vue) | M2, M3 | DONE |
| 5 | E2E Testing, Verification & Forensic Audit | Run PHP feature tests in container, verify Vue build, forensic audit | M4 | DONE |

## Interface Contracts & Requirements
- **WebSocketService**:
  - Fallback message queue: Redis list operations (`lpush`, `ltrim`, `lrange`). (DONE)
  - Presence tracking: Redis hash operations (`hset`, `hdel`, `hgetall`). (DONE)
- **StaffChatController**:
  - Broadcast new messages on creation via `WebSocketService::broadcast('staff-chat', 'chat.message', ...)`. (DONE)
  - Remove all dynamic `Schema::hasTable` checks. (DONE)
- **Pagination API**:
  - Staff Chat (`/api/admin/staff-chat/messages`) and Community Rooms (`/api/v1/community/chat/rooms/{room}`) history endpoints accept `cursor` parameter and return cursor pagination metadata (`next_cursor`, `prev_cursor`, `per_page`, `data`). (DONE)
- **Vue Components**:
  - `FloatingChatWidget.vue` and related components support infinite scrolling via cursor pagination, prepending older messages on top scroll. (DONE)
  - TS type check (`npm run type-check`) and Vite build (`npm run build`) pass cleanly. (DONE)
