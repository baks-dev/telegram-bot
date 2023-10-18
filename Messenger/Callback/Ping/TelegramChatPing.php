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

namespace BaksDev\Telegram\Bot\Messenger\Callback\Ping;
//namespace BaksDev\Telegram\Messenger;

use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Telegram\Api\TelegramSendMessage;
use BaksDev\Telegram\Bot\Messenger\Callback\TelegramCallbackMessage;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\GetTelegramBotSettingsInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TelegramChatPing
{
    private TelegramSendMessage $telegramSendMessage;
    private GetTelegramBotSettingsInterface $settings;
    private AppCacheInterface $cache;

    public function __construct(
        TelegramSendMessage $telegramSendMessage,
        GetTelegramBotSettingsInterface $settings,
        AppCacheInterface $cache
    )
    {
        $this->telegramSendMessage = $telegramSendMessage;
        $this->settings = $settings;
        $this->cache = $cache;
    }

    public function __invoke(TelegramCallbackMessage $message)
    {
        if($message->getClass() instanceof TelegramChatPingUid)
        {
            $settings = $this->settings->settings();

            /**
             * Отправляем идентификатор
             */
            $this->telegramSendMessage
                ->token($settings->getToken())
                ->chanel($message->getChat())
                ->message('PING: '.$message->getClass()->getValue())
                ->send();

            /**
             * Сбрасываем состояние диалога
             */
            $AppCache = $this->cache->init('telegram-bot');
            $AppCache->delete($message->getChat());
        }
    }
}