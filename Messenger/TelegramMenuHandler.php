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
use BaksDev\Auth\Telegram\Repository\ActiveProfileByAccountTelegram\ActiveProfileByAccountTelegramInterface;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\Collection\AccountTelegramStatusCollection;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramHandler;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Manufacture\Part\Telegram\Type\ManufacturePartDone;
use BaksDev\Telegram\Api\TelegramDeleteMessages;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Telegram\Request\Type\TelegramRequestCallback;
use BaksDev\Telegram\Request\Type\TelegramRequestIdentifier;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler(priority: 999)]
final class TelegramMenuHandler
{
    private TelegramSendMessages $telegramSendMessage;
    private UrlGeneratorInterface $urlGenerator;
    private ActiveProfileByAccountTelegramInterface $activeProfileByAccountTelegram;
    private string $HOST;

    public function __construct(
        #[Autowire(env: 'HOST')] string $HOST,
        TelegramSendMessages $telegramSendMessage,
        UrlGeneratorInterface $urlGenerator,
        ActiveProfileByAccountTelegramInterface $activeProfileByAccountTelegram,
    )
    {
        $this->HOST = $HOST;
        $this->telegramSendMessage = $telegramSendMessage;
        $this->urlGenerator = $urlGenerator;
        $this->activeProfileByAccountTelegram = $activeProfileByAccountTelegram;
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

        $profile = $this->activeProfileByAccountTelegram->findByChat($TelegramRequest->getChatId());

        if($profile !== null)
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
            'url' => 'https://'.$this->HOST.$this->urlGenerator->generate('auth-telegram:user.auth')
        ];

        $markup = json_encode([
            'inline_keyboard' => array_chunk($menu, 1),
        ]);

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

