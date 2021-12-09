<?php

/** @noinspection PhpDocMissingThrowsInspection */

/** @noinspection PhpUnused */

/** @noinspection PhpUnhandledExceptionInspection */

namespace DefStudio\Telegraph\Handlers;

use DefStudio\Telegraph\Exceptions\TelegramWebhookException;
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
    protected string $messageId;
    protected string $callbackQueryId;
    protected Request $request;
    protected Collection $data;
    protected Collection $originalKeyboard;

    protected function reply(string $message): void
    {
        $this->bot->replyWebhook($this->callbackQueryId, $message)->send();
    }

    /**
     * @param array<array<array<non-empty-string, non-empty-string>>> $newKeyboard
     */
    protected function replaceKeyboard(array $newKeyboard): void
    {
        $this->chat->replaceKeyboard($this->messageId, $newKeyboard)->send();
    }

    protected function deleteKeyboard(): void
    {
        $this->chat->replaceKeyboard($this->messageId, [])->send();
    }

    protected function canHandle(string $action): bool
    {
        if (!method_exists($this, $action)) {
            return false;
        }

        $reflector = new ReflectionMethod($this::class, $action);
        if (!$reflector->isPublic()) {
            return false;
        }

        if (method_exists(WebhookHandler::class, $action)) {
            throw TelegramWebhookException::invalidActionName($action);
        }

        return true;
    }

    public function handle(Request $request, TelegraphBot $bot): void
    {
        $this->bot = $bot;
        $this->request = $request;

        if ($this->request->has('message') || $this->request->has('channel_post')) {
            $this->handleMessage();
        }


        if ($this->request->has('callback_query')) {
            $this->handleCallbackQuery();
        }
    }

    protected function handleCallbackQuery(): void
    {
        $this->extractCallbackQueryData();

        if (config('telegraph.debug_mode')) {
            Log::debug('Telegraph webhook callback', $this->data->toArray());
        }

        $action = $this->data->get('action');

        if (!$this->canHandle($action)) {
            report(TelegramWebhookException::invalidAction($action));
            $this->reply('Invalid action');

            return;
        }

        $this->$action();
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

        $this->messageId = $this->request->input('callback_query.message.message_id'); //@phpstan-ignore-line
        $this->callbackQueryId = $this->request->input('callback_query.id'); //@phpstan-ignore-line
        $this->originalKeyboard = collect($this->request->input('callback_query.message.reply_markup.inline_keyboard', []))->flatten(1); //@phpstan-ignore-line
        $this->data = Str::of($this->request->input('callback_query.data'))->explode(';') //@phpstan-ignore-line
        ->mapWithKeys(function (string $entity) {
            $entity = explode(':', $entity);
            $key = $entity[0];
            $value = $entity[1];

            return [$key => $value];
        });
    }

    private function handleMessage(): void
    {
        $this->extractMessageData();

        if (config('telegraph.debug_mode')) {
            Log::debug('Telegraph webhook message', $this->data->toArray());
        }

        $text = Str::of($this->data->get('text'));

        if ($text->startsWith('/')) {
            $this->handleCommand($text);
        } else {
            $this->handleChatMessage($text);
        }
    }

    protected function extractMessageData(): void
    {
        /** @var TelegraphChat $chat */
        $chat = $this->bot->chats()->where('chat_id', $this->request->input('message.chat.id'))->firstOrNew();

        $this->chat = $chat;

        $this->messageId = $this->request->input('message.message_id', $this->request->input('channel_post.message_id')); //@phpstan-ignore-line

        $this->data = collect([
            'text' => $this->request->input('message.text', $this->request->input('channel_post.text')), //@phpstan-ignore-line
        ]);
    }

    protected function chatid(): void
    {
        $this->chat->html("Chat ID: {$this->chat->chat_id}")->send();
    }

    private function handleCommand(Stringable $text)
    {
        $command = $text->after('/');


        if (!$this->canHandle($command)) {
            report(TelegramWebhookException::invalidCommand($command));
            $this->chat->html("Unknown command")->send();

            return;
        }

        $this->$command();
    }

    protected function handleChatMessage(Stringable $text): void
    {
        // .. do nothing
    }
}
