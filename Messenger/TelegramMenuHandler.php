<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Telegram\Bot\Messenger;

use App\Kernel;
use BaksDev\Auth\Email\Repository\AccountEventActiveByEmail\AccountEventActiveByEmailInterface;
use BaksDev\Auth\Email\Type\Email\AccountEmail;
use BaksDev\Auth\Telegram\Repository\AccountTelegramEvent\AccountTelegramEventInterface;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\Collection\AccountTelegramStatusCollection;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramHandler;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Manufacture\Part\Telegram\Type\ManufacturePartDone;
use BaksDev\Telegram\Api\TelegramDeleteMessage;
use BaksDev\Telegram\Api\TelegramSendMessage;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Telegram\Request\Type\TelegramRequestCallback;
use BaksDev\Telegram\Request\Type\TelegramRequestIdentifier;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsMessageHandler]
final class TelegramMenuHandler
{
    private $security;
    private TelegramSendMessage $telegramSendMessage;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        Security $security,
        TelegramSendMessage $telegramSendMessage,
        UrlGeneratorInterface $urlGenerator
    )
    {
        $this->security = $security;
        $this->telegramSendMessage = $telegramSendMessage;
        $this->urlGenerator = $urlGenerator;
    }


    /**
     * Отправляет сообщение пользователю с требованием зарегистрироваться
     */
    public function __invoke(TelegramEndpointMessage $message): void
    {
        if(Kernel::isTestEnvironment())
        {
            return;
        }

        /** @var TelegramRequestMessage $TelegramRequest */
        $TelegramRequest = $message->getTelegramRequest();

        if(!($TelegramRequest instanceof TelegramRequestMessage) || $TelegramRequest->getText() !== '/menu')
        {
            return;
        }

        if(!$this->security->isGranted('ROLE_USER'))
        {
            $menu[] = [
                'text' => 'Регистрация по QR',
                'url' => $this->urlGenerator->generate('auth-telegram:user.auth', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)
            ];

            $markup = json_encode([
                'inline_keyboard' => array_chunk($menu, 1),
            ]);

            if(Kernel::isTestEnvironment())
            {
                $markup = null;
            }

            $TelegramRequest->getSystem() ? $delete[] = $TelegramRequest->getSystem() : false;
            $TelegramRequest->getId() ? $delete[] = $TelegramRequest->getSystem() : false;

            $msg = '<b>Не удалось убедиться, что этот аккаунт принадлежит Вам.</b>'.PHP_EOL;
            $msg .= PHP_EOL;
            $msg .= 'Отправьте свой <b>E-mail</b>, с которого Вы регистрировались для привязки к существующему аккаунту, либо зарегистрируйтесь с помощью <b>QR-кода</b> на странице регистрации.';

            $this
                ->telegramSendMessage
                ->chanel($TelegramRequest->getChatId())
                ->delete($delete)
                ->message($msg)
                ->markup($markup)
                ->send();

            $message->complete();
        }
    }
}

