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

namespace BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage;

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
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Telegram\Request\Type\TelegramRequestCallback;
use BaksDev\Telegram\Request\Type\TelegramRequestIdentifier;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Cache\CacheInterface;


#[AsMessageHandler(priority: -999)]
final class TelegramEndpointHandler
{
    private TelegramSendMessage $telegramSendMessage;

    public function __construct(TelegramSendMessage $telegramSendMessage)
    {
        $this->telegramSendMessage = $telegramSendMessage;
    }

    /**
     * В случае, если никакой из хендлеров не отработал - отправляем сообщение с вопросом
     */
    public function __invoke(TelegramEndpointMessage $message): void
    {
        $TelegramRequest = $message->getTelegramRequest();

        if($TelegramRequest instanceof TelegramRequestMessage)
        {
            if(in_array($TelegramRequest->getText(), ['menu', '/menu', 'start', '/start']))
            {
                return;
            }

            $this
                ->telegramSendMessage
                ->chanel($TelegramRequest->getChatId())
                ->message('Здравствуйте! Напишите, чем я могу вам помочь?')
                ->send()
            ;
        }
    }
}

