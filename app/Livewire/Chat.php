<?php

namespace App\Livewire;

use App\Ai\Agents\ChatAssistant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Chat extends Component
{
    public string $message = '';

    public array $messages = [];

    public ?string $conversationId = null;

    public array $conversations = [];

    public array $suggestions = [
        'Explain quantum computing in simple terms',
        'What are some creative date night ideas?',
        'Help me write a professional email',
        'What are the best practices for REST APIs?',
    ];

    public function mount(): void
    {
        $this->loadConversations();
    }

    public function sendMessage(): void
    {
        $text = trim($this->message);

        if ($text === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->message = '';

        $this->js('$wire.ask()');
    }

    public function ask(): void
    {
        set_time_limit(300);

        $userMessage = end($this->messages)['content'];

        try {
            $agent = ChatAssistant::make();

            if ($this->conversationId) {
                $agent->continue($this->conversationId, as: Auth::user());
            } else {
                $agent->forUser(Auth::user());
            }

            $stream = $agent->stream($userMessage);

            $fullText = '';

            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    $fullText .= $event->delta;

                    $this->stream(
                        content: $event->delta,
                        el: '#stream-target',
                    );
                }
            }

            if (! $this->conversationId && $agent->currentConversation()) {
                $this->conversationId = $agent->currentConversation();
            }

            $this->messages[] = ['role' => 'assistant', 'content' => $fullText];

            $this->loadConversations();
        } catch (\Throwable $e) {
            report($e);

            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Sorry, something went wrong. Please try again.',
            ];
        }
    }

    public function loadConversations(): void
    {
        $this->conversations = DB::table('agent_conversations')
            ->where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at'])
            ->toArray();
    }

    public function getGroupedConversations(): array
    {
        $groups = [
            'Today' => [],
            'Yesterday' => [],
            'Previous 7 days' => [],
            'Previous 30 days' => [],
            'Older' => [],
        ];

        $now = Carbon::now();

        foreach ($this->conversations as $conversation) {
            $conv = (object) $conversation;
            $updatedAt = Carbon::parse($conv->updated_at);

            if ($updatedAt->isToday()) {
                $groups['Today'][] = $conv;
            } elseif ($updatedAt->isYesterday()) {
                $groups['Yesterday'][] = $conv;
            } elseif ($updatedAt->greaterThanOrEqualTo($now->copy()->subDays(7)->startOfDay())) {
                $groups['Previous 7 days'][] = $conv;
            } elseif ($updatedAt->greaterThanOrEqualTo($now->copy()->subDays(30)->startOfDay())) {
                $groups['Previous 30 days'][] = $conv;
            } else {
                $groups['Older'][] = $conv;
            }
        }

        return array_filter($groups);
    }

    public function selectConversation(string $id): void
    {
        $this->conversationId = $id;

        $this->messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $id)
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->messages = [];
        $this->message = '';
    }

    public function selectSuggestion(string $suggestion): void
    {
        $this->message = $suggestion;
        $this->sendMessage();
    }

    public function getCurrentConversationTitle(): string
    {
        if (! $this->conversationId) {
            return 'New Chat';
        }

        foreach ($this->conversations as $conversation) {
            $conv = (object) $conversation;
            if ($conv->id === $this->conversationId) {
                return $conv->title ?? 'Untitled';
            }
        }

        return 'Chat';
    }

    public function render()
    {
        return view('livewire.chat');
    }
}
