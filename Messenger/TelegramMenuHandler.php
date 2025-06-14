<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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
 *
 */

declare(strict_types=1);

namespace BaksDev\Telegram\Bot\Messenger;

use BaksDev\Auth\Telegram\Repository\ActiveProfileByAccountTelegram\ActiveProfileByAccountTelegramInterface;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\Type\TelegramBotCommands;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler(priority: 999)]
final readonly class TelegramMenuHandler
{
    public function __construct(
        #[Autowire(env: 'HOST')] private string $HOST,
        private TelegramSendMessages $telegramSendMessage,
        private UrlGeneratorInterface $urlGenerator,
        private ActiveProfileByAccountTelegramInterface $activeProfileByAccountTelegram,
    ) {}


    /**
     * Отправляет сообщение пользователю с требованием зарегистрироваться
     */
    public function __invoke(TelegramEndpointMessage $message): void
    {

        /** @var TelegramRequestMessage $TelegramRequest */
        $TelegramRequest = $message->getTelegramRequest();

        if(false === ($TelegramRequest instanceof TelegramRequestMessage))
        {
            return;
        }

        /** Проверка текста сообщения по соответствию установленной команде бота */
        if(false === (in_array($TelegramRequest->getText(), TelegramBotCommands::START->commands())))
        {
            return;
        }

        $profile = $this->activeProfileByAccountTelegram
            ->findByChat($TelegramRequest->getChatId());

        if(false === ($profile instanceof UserProfileUid))
        {
            return;
        }

        $this->handle($TelegramRequest);
        $message->complete();
    }

    public function handle(TelegramRequestMessage $TelegramRequest): void
    {
        $menu[] = [
            'text' => 'Регистрация по QR',
            'url' => 'https://'.$this->HOST.$this->urlGenerator->generate('auth-telegram:public.auth')
        ];

        $markup = json_encode([
            'inline_keyboard' => array_chunk($menu, 1),
        ], JSON_THROW_ON_ERROR);

        $delete = [];

        $TelegramRequest->getSystem() ? $delete[] = $TelegramRequest->getSystem() : false;
        $TelegramRequest->getId() ? $delete[] = $TelegramRequest->getSystem() : false;

        $msg = '<b>Не удалось убедиться, что этот аккаунт принадлежит Вам.</b>'.PHP_EOL;
        $msg .= PHP_EOL;
        $msg .= sprintf('Отправьте свой <b>E-mail</b>, с которого Вы регистрировались для привязки к существующему аккаунту %s, либо зарегистрируйтесь с помощью <b>QR-кода</b> на странице регистрации.', $this->HOST);

        $this
            ->telegramSendMessage
            ->chanel($TelegramRequest->getChatId())
            ->delete($delete)
            ->message($msg)
            ->markup($markup)
            ->send();

    }
}

