# PRD: Laravel AI SDK + Livewire Chat

A standalone reference implementation of a ChatGPT-style chat interface built with the official Laravel AI SDK and Livewire. Accompanies the blog post at `/blog/laravel-ai-sdk-livewire-chat`.

## Goals

1. Provide a working, self-contained Laravel project that readers can clone and run
2. Demonstrate the Laravel AI SDK's streaming and conversation memory with Livewire (not React/Inertia)
3. Support all SDK providers (OpenAI, Anthropic, etc.) through the SDK's provider abstraction
4. Ship core chat functionality only — no tool calling, file attachments, or typing indicators

## Non-goals

- Admin panel or user management UI (use Laravel Breeze scaffolding)
- Multi-user real-time chat (Echo/Reverb territory)
- Tool calling visualization
- File upload/attachments
- Typing indicators or presence
- Custom theme system or design polish beyond functional Tailwind

## Tech stack

| Layer | Choice |
|-------|--------|
| Framework | Laravel 12 |
| AI | `laravel/ai` (official Laravel AI SDK) |
| Frontend reactivity | Livewire 4 |
| Streaming | `wire:stream` (chunked transfer encoding) |
| Conversation memory | `RemembersConversations` trait + SDK migrations |
| Client-side markdown | marked.js via CDN |
| Styling | Tailwind CSS |
| Auth scaffolding | Laravel Breeze (Blade stack) |

## Data model

The SDK publishes two tables via `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"`. No custom migrations are needed.

### `agent_conversations`

Managed by the SDK. Stores conversation metadata per user.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | Primary key |
| user_id | bigint | Foreign key to `users` |
| title | string, nullable | Conversation title |
| created_at | timestamp | |
| updated_at | timestamp | |

### `agent_conversation_messages`

Managed by the SDK. Stores individual messages within a conversation.

| Column | Type | Notes |
|--------|------|-------|
| id | uuid | Primary key |
| conversation_id | uuid | Foreign key to `agent_conversations` |
| role | string | `user` or `assistant` |
| content | text | Message body |
| created_at | timestamp | |

Note: The exact column names and types are defined by the SDK's published migrations. The table above reflects the columns used in the blog post's queries. Verify against the actual published migration files when building.

## Architecture

### Agent

```
App\Ai\Agents\ChatAssistant
├── implements Agent, Conversational
├── uses Promptable, RemembersConversations
└── instructions(): "You are a helpful assistant. Be concise and direct."
```

Provider is configured via the `#[Provider]` attribute or the SDK's default config. No provider-specific code in the agent class.

### Livewire component

```
App\Livewire\Chat
├── Properties
│   ├── message: string (input binding)
│   ├── messages: array (displayed message list)
│   ├── conversationId: ?string (current conversation UUID)
│   └── conversations: array (sidebar list)
├── Methods
│   ├── mount() → loadConversations()
│   ├── sendMessage() → push user message, clear input, trigger ask() via $this->js()
│   ├── ask() → stream AI response, persist conversationId, reload sidebar
│   ├── loadConversations() → query agent_conversations for sidebar
│   ├── selectConversation(id) → load messages from agent_conversation_messages
│   └── newConversation() → reset state
└── View: livewire/chat.blade.php
```

### Streaming lifecycle

Two separate Livewire requests per user message:

1. **Request 1 — `sendMessage()`**: Fires on form submit. Pushes user message to `$messages`, clears input, calls `$this->js('$wire.ask()')`. Returns immediately. Component re-renders with the user's message visible.

2. **Request 2 — `ask()`**: Triggered by the JS call after Request 1 renders. Creates or continues conversation via SDK. Iterates the stream, sending each token to the browser via `$this->stream(to: 'response', content: $event)`. On completion, stores `conversationId` and appends the full response to `$messages`. Reloads the sidebar conversation list.

### Blade template structure

```
div.flex.h-[600px]
├── div.w-64 (sidebar)
│   ├── button "New chat" → wire:click="newConversation"
│   └── @foreach conversations → wire:click="selectConversation(id)"
└── div.flex-1.flex.flex-col (main area)
    ├── div#chat-messages (scrollable message list)
    │   ├── @foreach messages → user/assistant bubbles
    │   │   └── assistant messages rendered via Str::markdown()
    │   └── div[wire:loading.block][wire:target="ask"] (streaming bubble)
    │       ├── span#stream-raw[wire:stream="response"].hidden
    │       └── div#stream-rendered.prose.prose-sm
    ├── form[wire:submit="sendMessage"]
    │   ├── input[wire:model="message"]
    │   └── button "Send"
    └── @script (MutationObserver for markdown + auto-scroll)
```

### Client-side JavaScript

A `MutationObserver` on `#chat-messages`:
- On every mutation, reads `#stream-raw` text content
- Parses it with `marked.parse()` and writes HTML to `#stream-rendered`
- Sets `scrollTop = scrollHeight` for auto-scroll

`marked.min.js` loaded via CDN in the app layout.

## Routes

```php
// routes/web.php
Route::get('/chat', \App\Livewire\Chat::class)->middleware('auth');
```

Single route. Breeze handles `/login`, `/register`, `/dashboard`.

## Provider configuration

The SDK reads provider API keys from `.env`. The project's `.env.example` includes placeholders for all supported providers:

```env
# Set the provider you want to use
AI_PROVIDER=openai

# Provider API keys (configure whichever provider you're using)
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
```

The exact env variable names are defined by the SDK's published config. The `ChatAssistant` agent uses the SDK default provider unless overridden with the `#[Provider]` attribute.

## Error handling

- `ask()` wraps the streaming call in try/catch
- On failure: logs the exception via `report()`, appends an error message to `$messages` so the component stays usable
- No retry logic — the user sends another message to retry

## Production considerations

These are documented in the blog post and should be addressed in the project's README:

| Issue | Fix |
|-------|-----|
| Apache + PHP-FPM buffering | Flush output buffers at start of `ask()`: `while (ob_get_level()) { ob_end_flush(); }` |
| Nginx proxy buffering | `proxy_buffering off;` and `add_header X-Accel-Buffering no;` in server block |
| Laravel Octane | `wire:stream` unsupported. Use `broadcastOnQueue()` with Reverb instead |
| PHP max_execution_time | `set_time_limit(300)` at start of `ask()` |
| symfony/http-foundation v7.3 | Pin to `~7.2.0` if responses truncate |

## File structure

```
laravel-ai-sdk-livewire-chat/
├── app/
│   ├── Ai/
│   │   └── Agents/
│   │       └── ChatAssistant.php
│   ├── Livewire/
│   │   └── Chat.php
│   └── Models/
│       └── User.php (default Breeze)
├── resources/
│   └── views/
│       ├── components/
│       │   └── layouts/
│       │       └── app.blade.php (adds marked.js CDN script)
│       └── livewire/
│           └── chat.blade.php
├── routes/
│   └── web.php (adds /chat route)
├── .env.example (provider API key placeholders)
└── README.md (setup instructions, provider config, production notes)
```

Everything else is default Laravel 12 + Breeze scaffolding. The custom code is ~3 files: the agent, the Livewire component, and the Blade template.

## Setup instructions (for README)

```bash
git clone <repo-url>
cd laravel-ai-sdk-livewire-chat
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# Configure your database in .env
php artisan migrate
# Add your AI provider API key to .env
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate
php artisan serve
# Register a user at /register, then visit /chat
```

## Scope boundary

This project implements exactly what the blog post describes. Nothing more.

**In scope:**
- ChatAssistant agent with `RemembersConversations`
- Livewire Chat component with two-request streaming lifecycle
- Conversation sidebar (list, switch, new chat)
- Markdown rendering during streaming via MutationObserver + marked.js
- Auto-scroll during streaming
- Error handling (try/catch with user-facing error message)
- Output buffer flushing and `set_time_limit` for production

**Out of scope:**
- Tool calling
- File attachments
- Typing indicators
- Conversation deletion
- Conversation title generation
- Message editing or regeneration
- Rate limiting
- Usage tracking or token counting
- Tests (separate concern, may add later)
- Dark mode or theme switching
- Mobile-responsive sidebar
