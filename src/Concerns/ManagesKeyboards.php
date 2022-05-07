<?php

/** @noinspection PhpDocMissingThrowsInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace DefStudio\Telegraph\Concerns;

use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Telegraph;

/**
 * @mixin Telegraph
 */
trait ManagesKeyboards
{
    protected Keyboard|null $keyboard = null;

    /**
     * @param array<array<array<string, string>>>|Keyboard|callable(Keyboard):Keyboard $keyboard
     */
    public function keyboard(callable|array|Keyboard $keyboard, bool $remove = false): Telegraph
    {
        $telegraph = clone $this;

        if (is_callable($keyboard)) {
            $keyboard = $keyboard(Keyboard::make());
        }

        if (is_array($keyboard)) {
            $keyboard = Keyboard::fromArray($keyboard);
        }

        if ($remove) {
            $replyMarkup = [
                'remove_keyboard' => true,
            ];
        } else {
            $replyMarkup = [
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
                $keyboard->getReplyMarkup() => $keyboard->toArray(),
            ];
        }

        $telegraph->data['reply_markup'] = $replyMarkup;

        return $telegraph;
    }

    /**
     * @param Keyboard|callable(Keyboard):Keyboard $newKeyboard
     */
    public function replaceKeyboard(int $messageId, Keyboard|callable $newKeyboard): Telegraph
    {
        $telegraph = clone $this;

        if (is_callable($newKeyboard)) {
            $newKeyboard = $newKeyboard(Keyboard::make());
        }

        if ($newKeyboard->isEmpty()) {
            $replyMarkup = '';
        } else {
            $replyMarkup = [$newKeyboard->getReplyMarkup() => $newKeyboard->toArray()];
        }

        $telegraph->endpoint = self::ENDPOINT_REPLACE_KEYBOARD;
        $telegraph->data = [
            'chat_id' => $telegraph->getChat()->chat_id,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ];

        return $telegraph;
    }

    public function deleteKeyboard(int $messageId): Telegraph
    {
        $telegraph = clone $this;

        return $telegraph->replaceKeyboard($messageId, Keyboard::make());
    }
}
