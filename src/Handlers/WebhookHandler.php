<?php

/** @noinspection PhpDocMissingThrowsInspection */

/** @noinspection PhpUnused */

/** @noinspection PhpUnhandledExceptionInspection */

namespace DefStudio\Telegraph\Handlers;

use DefStudio\Telegraph\DTO\CallbackQuery;
use DefStudio\Telegraph\DTO\Chat;
use DefStudio\Telegraph\DTO\Message;
use DefStudio\Telegraph\Exceptions\TelegramWebhookException;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class WebhookHandler
{
    protected TelegraphBot $bot;
    protected TelegraphChat $chat;

    protected int $messageId;
    protected int $callbackQueryId;

    protected Request $request;
    protected Message|null $message = null;
    protected CallbackQuery|null $callbackQuery = null;

    protected Collection $data;

    protected Keyboard $originalKeyboard;

    public function __construct()
    {
        $this->originalKeyboard = Keyboard::make();
    }

    private function handleCallbackQuery(): void
    {
        $this->extractCallbackQueryData();

        if (config('telegraph.debug_mode')) {
            Log::debug('Telegraph webhook callback', $this->data->toArray());
        }

        /** @var string $action */
        $action = $this->callbackQuery?->data()->get('action') ?? '';

        if (!$this->canHandle($action)) {
            report(TelegramWebhookException::invalidAction($action));
            $this->reply('Недопустимое действие');

            return;
        }

        $this->$action();
    }

    private function handleCommand(Stringable $text): void
    {
        $command = (string) $text->after('/')->before(' ')->before('@');

        if (!$this->canHandle($command)) {
            if ($this->message?->chat()?->type() === Chat::TYPE_PRIVATE) {
                report(TelegramWebhookException::invalidCommand($command));
                $this->chat->html("Неизвестная команда")->send();
            }

            return;
        }

        $this->$command();
    }

    private function handleMessage(): void
    {
        $this->extractMessageData();

        if (config('telegraph.debug_mode')) {
            Log::debug('Telegraph webhook message', $this->data->toArray());
        }

        $text = Str::of($this->message?->text() ?? '');

        if ($text->startsWith('/')) {
            $this->handleCommand($text);
        } else {
            $this->handleChatMessage($text);
        }
    }

    private function handleContact(): void
    {
        $this->extractContactData();

        if (config('telegraph.debug_mode')) {
            Log::debug('Telegraph webhook contact', $this->data->toArray());
        }

        $this->contact();
    }

    abstract protected function contact();

    private function handleLocation(): void
    {
        $this->extractLocationData();

        if (config('telegraph.debug_mode')) {
            Log::debug('Telegraph webhook location', $this->data->toArray());
        }

        $this->location();
    }

    abstract protected function location();

    protected function canHandle(string $action): bool
    {
        if (!method_exists($this, $action)) {
            return false;
        }

        $reflector = new ReflectionMethod($this::class, $action);
        if (!$reflector->isPublic()) {
            return false;
        }

        return true;
    }

    protected function extractCallbackQueryData(): void
    {
        try {
            /** @var TelegraphChat $chat */
            $chat = $this->bot->chats->where('chat_id', $this->request->input('callback_query.message.chat.id'))->firstOrFail();
            $this->chat = $chat;
        } catch (ItemNotFoundException) {
            throw new NotFoundHttpException();
        }

        assert($this->callbackQuery !== null);

        $this->messageId = $this->callbackQuery->message()?->id() ?? throw TelegramWebhookException::invalidData('message id missing');

        $this->callbackQueryId = $this->callbackQuery->id();

        /** @phpstan-ignore-next-line */
        $this->originalKeyboard = $this->callbackQuery->message()?->keyboard() ?? Keyboard::make();

        $this->data = $this->callbackQuery->data();
    }

    protected function extractMessageData(): void
    {
        /** @var TelegraphChat $chat */
        $chat = $this->bot->chats()->where('chat_id', $this->request->input('message.chat.id'))->firstOrNew();

        $this->chat = $chat;

        assert($this->message !== null);

        $this->messageId = $this->message->id();

        $this->data = collect([
            'text' => $this->message->text(),
        ]);
    }

    protected function extractContactData(): void
    {
        /** @var TelegraphChat $chat */
        $chat = $this->bot->chats()->where('chat_id', $this->request->input('message.chat.id'))->firstOrNew();

        $this->chat = $chat;

        assert($this->message !== null);

        $this->messageId = $this->message->id();

        $this->data = collect([
            'contact' => $this->message->contact(),
        ]);
    }

    protected function extractLocationData(): void
    {
        /** @var TelegraphChat $chat */
        $chat = $this->bot->chats()->where('chat_id', $this->request->input('message.chat.id'))->firstOrNew();

        $this->chat = $chat;

        assert($this->message !== null);

        $this->messageId = $this->message->id();

        $this->data = collect([
            'location' => $this->message->location(),
        ]);
    }

    protected function handleChatMessage(Stringable $text): void
    {
        // .. do nothing
    }

    protected function replaceKeyboard(Keyboard $newKeyboard): void
    {
        $this->chat->replaceKeyboard($this->messageId, $newKeyboard)->send();
    }

    protected function deleteKeyboard(): void
    {
        $this->chat->deleteKeyboard($this->messageId)->send();
    }

    protected function reply(string $message): void
    {
        $this->bot->replyWebhook($this->callbackQueryId, $message)->send();
    }

    public function chatid(): void
    {
        $this->chat->html("Chat ID: {$this->chat->chat_id}")->send();
    }

    public function handle(Request $request, TelegraphBot $bot): void
    {
        $this->bot = $bot;

        $this->request = $request;

        if ($this->request->has('message.contact')) {
            /* @phpstan-ignore-next-line */
            $this->message = Message::fromArray($this->request->input('message'));
            $this->handleContact();

            return;
        }

        if ($this->request->has('message.location')) {
            /* @phpstan-ignore-next-line */
            $this->message = Message::fromArray($this->request->input('message'));
            $this->handleLocation();

            return;
        }

        if ($this->request->has('message')) {
            /* @phpstan-ignore-next-line */
            $this->message = Message::fromArray($this->request->input('message'));
            $this->handleMessage();

            return;
        }

        if ($this->request->has('channel_post')) {
            /* @phpstan-ignore-next-line */
            $this->message = Message::fromArray($this->request->input('channel_post'));
            $this->handleMessage();

            return;
        }


        if ($this->request->has('callback_query')) {
            /* @phpstan-ignore-next-line */
            $this->callbackQuery = CallbackQuery::fromArray($this->request->input('callback_query'));
            $this->handleCallbackQuery();
        }
    }
}
