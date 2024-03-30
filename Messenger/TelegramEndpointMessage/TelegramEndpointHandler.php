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
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsMessageHandler(priority: -999)]
final class TelegramEndpointHandler
{
    private $security;
    private LoggerInterface $logger;
    private TelegramSendMessage $telegramSendMessage;
    private TelegramDeleteMessage $deleteMessage;

    public function __construct(
        LoggerInterface $telegramBotLogger,
        Security $security,
        TelegramSendMessage $telegramSendMessage,
        TelegramDeleteMessage $deleteMessage
    )
    {
        $this->security = $security;
        $this->logger = $telegramBotLogger;
        $this->telegramSendMessage = $telegramSendMessage;
        $this->deleteMessage = $deleteMessage;
    }


    public function __invoke(TelegramEndpointMessage $message): void
    {
        $TelegramRequest = $message->getTelegramRequest();

        if($TelegramRequest !== null)
        {
            $this
                ->telegramSendMessage
                ->chanel($TelegramRequest->getChatId())
                ->message('Здравствуйте! Напишите, чем я могу вам помочь?')
                ->send()
            ;
        }

        $this->logger->debug(self::class, [$message]);

        if(Kernel::isTestEnvironment())
        {
            dump(sprintf('TelegramEndpointHandler %s', __FILE__));
        }

        return;
    }

    public function invoke(TelegramEndpointMessage $message): void
    {
        if(!$this->security->isGranted('ROLE_ADMIN'))
        {
            $this->logger->warning('Пользователь не имеет прав');
        }

        $TelegramRequest = $message->getTelegramRequest();

        $this
            ->telegramSendMessage
            ->chanel($TelegramRequest->getChatId());

        $this->deleteMessage->chanel($TelegramRequest->getChatId());


        if($TelegramRequest instanceof TelegramRequestMessage)
        {
            $this->logger->debug('TelegramRequestMessage', [
                $TelegramRequest->getText()
            ]);

            //$this->deleteMessage->delete($TelegramRequest->getSystem())->send();

//            $this
//                ->telegramSendMessage
//                ->delete([
//                    $TelegramRequest->getId(),
//                    $TelegramRequest->getLast(),
//                    $TelegramRequest->getSystem()
//                ])
//                ->message($TelegramRequest->getText())
//                ->send()
//            ;
        }

        if($TelegramRequest instanceof TelegramRequestIdentifier)
        {
            $this->logger->debug('TelegramRequestIdentifier', [
                'id' =>  $TelegramRequest->getIdentifier(),
                'last' => $TelegramRequest->getLast(),
                'system' => $TelegramRequest->getSystem(),
            ]);

            //$this->deleteMessage->delete($TelegramRequest->getSystem())->send();

            $menu[] = [
                'text' => 'Кнопка',
                'callback_data' => 'cancel|018dacc1-ccf0-73cc-b573-fe10d3863d9e'
            ];

            $markup = json_encode([
                'inline_keyboard' => array_chunk($menu, 1),
            ]);


            $this
                ->telegramSendMessage
                ->delete([
                    //$TelegramRequest->getLast(),
                    $TelegramRequest->getSystem(),
                    $TelegramRequest->getId(),
                ])
                ->message($TelegramRequest->getIdentifier(). ' - ' .$TelegramRequest->getId())
                ->markup($markup)
                ->send()
            ;
        }

        if($TelegramRequest instanceof TelegramRequestCallback)
        {
            $this->logger->debug('TelegramRequestCallback', [
                'last' => $TelegramRequest->getLast(),
                'system' => $TelegramRequest->getSystem(),
                'call' => $TelegramRequest->getCall(),
                'id' => $TelegramRequest->getIdentifier(),
            ]);


            $this
                ->telegramSendMessage
                ->delete([
                    $TelegramRequest->getLast(),
                    $TelegramRequest->getSystem(),
                    //$TelegramRequest->getId(),
                ])
                ->message($TelegramRequest->getCall(). ' - ' .$TelegramRequest->getIdentifier())
                ->send()
            ;

        }
    }
}

