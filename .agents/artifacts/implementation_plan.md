# Implementation Plan: AI Conversations Module with Laravel AI SDK & TALL Stack

Build a full-featured, persistent Conversations module using the **TALL Stack** (Tailwind CSS, Alpine.js, Laravel 11/12, Livewire 4) integrated with **Laravel AI SDK** (`Laravel\Ai\`) configured with **Ollama** (`minimax-m3` model). All prompts, user messages, and AI responses will be automatically stored in `agent_conversations` and `agent_conversation_messages` tables with capabilities to restore, continue, edit, and delete conversations.

## Proposed Changes

### AI Agent & Storage Layer

#### [MODIFY] [ContentStrategist.php](file:///c:/laragon/www/rnd-omniformat/app/Ai/Agents/ContentStrategist.php)
- Remove empty `messages(): iterable` method override so the agent inherits `RemembersConversations::messages()` from Laravel AI SDK to automatically load past database messages during turns.
- Ensure the agent properly remembers conversations when initialized with `.forUser($user)` or `.continue($conversationId, as: $user)`.

#### [MODIFY] [ContentController.php](file:///c:/laragon/www/rnd-omniformat/app/Http/Controllers/ContentController.php)
- Update `analyze()` method to associate the prompt with the authenticated user (`$agent->forUser($user)` or `$agent->continue($conversationId, as: $user)`).
- Return the created/updated `conversation_id` in the JSON response so the content generator can link directly to the conversation history.

---

### UI & Livewire Layer (TALL Stack)

#### [MODIFY] [⚡conversation.blade.php](file:///c:/laragon/www/rnd-omniformat/resources/views/components/%E2%9A%A1conversation.blade.php)
- Build a responsive 2-column ChatGPT-like interface:
  - **Left Sidebar / Drawer**:
    - "New Chat" button to quickly start fresh conversations.
    - Search input to filter conversations in real time.
    - List of conversations with title, timestamp (relative time), and message counts.
    - Contextual menu on items to **Edit Title** (inline or prompt modal) and **Delete Conversation** with confirmation.
    - Active conversation indicator.
  - **Main Conversation Area**:
    - **Header**: Active conversation title with quick-edit inline icon, model badge (`minimax-m3 via Ollama`), and delete button.
    - **Message Stream & Thread**:
      - User message bubble with avatar/initials, formatted text, timestamp, and quick-copy button.
      - Assistant message bubble with agent avatar, formatted response (supporting code blocks, lists, and line breaks), and copy button.
      - Dynamic loading state with animated indicator while generating responses.
      - Empty state with quick starter prompts when starting a new conversation.
    - **Chat Input Box**:
      - Multi-line auto-resizing textarea with Enter-to-send (Shift+Enter for newline).
      - Quick buttons to send or clear.
- Full Livewire reactive methods:
  - `selectConversation($id)`: restores full conversation history from `agent_conversation_messages`.
  - `startNewConversation()`: resets state for a new conversation.
  - `sendMessage()`: invokes `ContentStrategist` with `forUser()` or `continue($id, as: $user)`, automatically appending user and AI messages to `agent_conversation_messages` and `agent_conversations`.
  - `updateTitle($id, $newTitle)`: edits conversation title in `agent_conversations`.
  - `deleteConversation($id)`: deletes conversation and cascades its messages.

#### [MODIFY] [conversation.blade.php](file:///c:/laragon/www/rnd-omniformat/resources/views/conversation.blade.php)
- Embed the updated `<livewire:conversation />` component in the app layout.

#### [MODIFY] [⚡content-magic.blade.php](file:///c:/laragon/www/rnd-omniformat/resources/views/components/%E2%9A%A1content-magic.blade.php)
- Pass the authenticated user context to `ContentController::analyze()`.
- Display a "View in Conversations" link/button when content generation completes, allowing instant transition into the conversation view.

#### [MODIFY] [sidebar.blade.php](file:///c:/laragon/www/rnd-omniformat/resources/views/layouts/app/sidebar.blade.php)
- Update Conversations nav link icon to a conversation/chat icon (`chat-bubble-left-right`) and verify navigation active states.

---

### Routing & Controller Layer

#### [MODIFY] [ConversationController.php](file:///c:/laragon/www/rnd-omniformat/app/Http/Controllers/ConversationController.php)
- Clean up controller to render the `conversation` view with query parameter support (e.g. `?c=UUID` to deep link into a specific conversation).

#### [MODIFY] [web.php](file:///c:/laragon/www/rnd-omniformat/routes/web.php)
- Ensure `/conversation` is cleanly routed with auth middleware and named `conversation`.

---

## Verification Plan

### Automated Tests
- Create `tests/Feature/ConversationTest.php` with Pest 4:
  - `test('authenticated user can view conversation page')`
  - `test('sending a prompt creates records in agent_conversations and agent_conversation_messages')`
  - `test('user can restore and continue an existing conversation')`
  - `test('user can edit conversation title')`
  - `test('user can delete a conversation')`
  - `test('user cannot access or delete other users conversations')`
- Run `php artisan test --compact --filter=ConversationTest`
- Run full test suite: `php artisan test --compact`
- Run Pint code formatting: `vendor/bin/pint --dirty --format agent`

### Manual Verification
- Open `/conversation` in the browser.
- Start a new conversation, send a prompt, and verify that the user message and AI response appear in the UI and are stored in `agent_conversations` and `agent_conversation_messages`.
- Rename the conversation and verify persistence.
- Select past conversations from the sidebar and verify full message history restoration.
- Verify content generation in `/content` stores conversations in the database and links to `/conversation`.
