<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Telegram\Bot\Messenger\Notifier;


use BaksDev\Telegram\Api\TelegramSendMessage;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\GetTelegramBotSettingsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NotifierTelegramBotHandler
{
    private TelegramSendMessage $sendMessage;
    private GetTelegramBotSettingsInterface $telegramBotSettings;
    private int|string $chat;

    public function __construct(
        #[Autowire(env: 'TELEGRAM_NOTIFIER')] int|string $chat = null,
        GetTelegramBotSettingsInterface $telegramBotSettings,
        TelegramSendMessage $sendMessage
    ) {

        $this->sendMessage = $sendMessage;
        $this->telegramBotSettings = $telegramBotSettings;
        $this->chat = $chat;
    }


    public function __invoke(NotifierTelegramBotMessage $message)
    {
        $this->telegramBotSettings->settings();

        $this->sendMessage
            ->token($this->telegramBotSettings->getToken())
            ->chanel($this->chat)
            ->message($message->getMessage())
        ;

        /* Если указана ссылка - добавляем кнопку для перехода */
        if($message->getLink())
        {
            $menu[] = [
                'text' => 'Перейти на страницу',
                'url' => $message->getLink()
            ];

            $markup = json_encode([
                'inline_keyboard' => array_chunk($menu, 1),
            ], JSON_THROW_ON_ERROR);

            $this->sendMessage->markup($markup);
        }

        $this->sendMessage->send();
    }
}
