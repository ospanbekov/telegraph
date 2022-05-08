<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace DefStudio\Telegraph\Controllers;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController
{
    public function handle(Request $request, string $token): Response
    {
        $bot = TelegraphBot::fromToken($token);

        /** @var class-string $handler */
        $handler = config('telegraph.webhook_handler.' . $token);

        if (is_null($handler)) $handler = config('telegraph.webhook_handler.default');

        /** @var WebhookHandler $handler */
        $handler = app($handler);

        $handler->handle($request, $bot);

        return \response()->noContent();
    }
}
