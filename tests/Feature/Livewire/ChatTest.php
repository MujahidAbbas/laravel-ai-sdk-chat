<?php

namespace Tests\Feature\Livewire;

use App\Ai\Agents\ChatAssistant;
use App\Livewire\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_page_requires_authentication(): void
    {
        $response = $this->get('/chat');

        $response->assertRedirect('/login');
    }

    public function test_chat_page_is_displayed_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/chat');

        $response->assertOk();
    }

    public function test_chat_component_renders_empty_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->assertSee('How can I help you today?');
    }

    public function test_empty_message_is_not_sent(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->set('message', '')
            ->call('sendMessage')
            ->assertSet('messages', []);
    }

    public function test_whitespace_only_message_is_not_sent(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->set('message', '   ')
            ->call('sendMessage')
            ->assertSet('messages', []);
    }

    public function test_new_conversation_resets_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->set('message', 'hello')
            ->set('messages', [['role' => 'user', 'content' => 'hello']])
            ->set('conversationId', 'some-id')
            ->call('newConversation')
            ->assertSet('message', '')
            ->assertSet('messages', [])
            ->assertSet('conversationId', null);
    }

    public function test_conversations_are_loaded_on_mount(): void
    {
        $user = User::factory()->create();

        DB::table('agent_conversations')->insert([
            'id' => 'conv-1',
            'user_id' => $user->id,
            'title' => 'Test Conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->assertSee('Test Conversation');
    }

    public function test_selecting_conversation_loads_messages(): void
    {
        $user = User::factory()->create();

        DB::table('agent_conversations')->insert([
            'id' => 'conv-1',
            'user_id' => $user->id,
            'title' => 'Test Conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agent_conversation_messages')->insert([
            'id' => 'msg-1',
            'conversation_id' => 'conv-1',
            'user_id' => $user->id,
            'agent' => 'chat',
            'role' => 'user',
            'content' => 'Hello there',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agent_conversation_messages')->insert([
            'id' => 'msg-2',
            'conversation_id' => 'conv-1',
            'user_id' => $user->id,
            'agent' => 'chat',
            'role' => 'assistant',
            'content' => 'Hi! How can I help?',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->call('selectConversation', 'conv-1')
            ->assertSet('conversationId', 'conv-1')
            ->assertSee('Hello there')
            ->assertSee('Hi! How can I help?');
    }

    public function test_other_users_conversations_are_not_visible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        DB::table('agent_conversations')->insert([
            'id' => 'conv-other',
            'user_id' => $otherUser->id,
            'title' => 'Secret Conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->assertDontSee('Secret Conversation');
    }

    public function test_send_message_adds_user_message_and_clears_input(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(Chat::class)
            ->set('message', 'Hello AI')
            ->call('sendMessage');

        $messages = $component->get('messages');

        $this->assertCount(1, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('Hello AI', $messages[0]['content']);
        $component->assertSet('message', '');
    }

    public function test_chat_displays_new_chat_button(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->assertSee('New Chat');
    }

    public function test_suggestion_chips_are_displayed_on_empty_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->assertSee('Explain quantum computing in simple terms')
            ->assertSee('What are some creative date night ideas?')
            ->assertSee('Help me write a professional email')
            ->assertSee('What are the best practices for REST APIs?');
    }

    public function test_select_suggestion_sets_message_and_sends(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(Chat::class)
            ->call('selectSuggestion', 'Hello world');

        $messages = $component->get('messages');

        $this->assertCount(1, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('Hello world', $messages[0]['content']);
        $component->assertSet('message', '');
    }

    public function test_get_current_conversation_title_returns_new_chat_when_no_conversation(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(Chat::class);

        $this->assertSame('New Chat', $component->instance()->getCurrentConversationTitle());
    }

    public function test_get_current_conversation_title_returns_conversation_title(): void
    {
        $user = User::factory()->create();

        DB::table('agent_conversations')->insert([
            'id' => 'conv-title-test',
            'user_id' => $user->id,
            'title' => 'My Chat Title',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(Chat::class)
            ->set('conversationId', 'conv-title-test');

        $this->assertSame('My Chat Title', $component->instance()->getCurrentConversationTitle());
    }

    public function test_get_current_conversation_title_returns_chat_when_conversation_not_found(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(Chat::class)
            ->set('conversationId', 'nonexistent-id');

        $this->assertSame('Chat', $component->instance()->getCurrentConversationTitle());
    }

    public function test_grouped_conversations_returns_today_group(): void
    {
        $user = User::factory()->create();

        DB::table('agent_conversations')->insert([
            'id' => 'conv-today',
            'user_id' => $user->id,
            'title' => 'Today Conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(Chat::class);

        $grouped = $component->instance()->getGroupedConversations();

        $this->assertArrayHasKey('Today', $grouped);
        $this->assertCount(1, $grouped['Today']);
        $this->assertSame('Today Conversation', $grouped['Today'][0]->title);
    }

    public function test_grouped_conversations_returns_yesterday_group(): void
    {
        $user = User::factory()->create();

        DB::table('agent_conversations')->insert([
            'id' => 'conv-yesterday',
            'user_id' => $user->id,
            'title' => 'Yesterday Conversation',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(Chat::class);

        $grouped = $component->instance()->getGroupedConversations();

        $this->assertArrayHasKey('Yesterday', $grouped);
        $this->assertCount(1, $grouped['Yesterday']);
    }

    public function test_grouped_conversations_returns_older_group(): void
    {
        $user = User::factory()->create();

        DB::table('agent_conversations')->insert([
            'id' => 'conv-old',
            'user_id' => $user->id,
            'title' => 'Old Conversation',
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        $component = Livewire::actingAs($user)
            ->test(Chat::class);

        $grouped = $component->instance()->getGroupedConversations();

        $this->assertArrayHasKey('Older', $grouped);
        $this->assertCount(1, $grouped['Older']);
    }

    public function test_grouped_conversations_returns_empty_when_no_conversations(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(Chat::class);

        $grouped = $component->instance()->getGroupedConversations();

        $this->assertEmpty($grouped);
    }

    public function test_ask_streams_response_and_adds_assistant_message(): void
    {
        $user = User::factory()->create();

        ChatAssistant::fake(['Hello from the assistant!']);

        $component = Livewire::actingAs($user)
            ->test(Chat::class)
            ->set('message', 'Hi there')
            ->call('sendMessage')
            ->call('ask');

        $messages = $component->get('messages');

        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('Hi there', $messages[0]['content']);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertSame('Hello from the assistant!', $messages[1]['content']);
    }

    public function test_ask_handles_errors_gracefully(): void
    {
        $user = User::factory()->create();

        ChatAssistant::fake(function () {
            throw new \RuntimeException('API is down');
        });

        $component = Livewire::actingAs($user)
            ->test(Chat::class)
            ->set('message', 'Hi there')
            ->call('sendMessage')
            ->call('ask');

        $messages = $component->get('messages');

        $this->assertCount(2, $messages);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertSame('Sorry, something went wrong. Please try again.', $messages[1]['content']);
    }

    public function test_sidebar_shows_date_group_headers(): void
    {
        $user = User::factory()->create();

        DB::table('agent_conversations')->insert([
            'id' => 'conv-group-header',
            'user_id' => $user->id,
            'title' => 'Grouped Conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->assertSee('Today')
            ->assertSee('Grouped Conversation');
    }
}
