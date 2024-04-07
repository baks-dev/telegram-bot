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

namespace BaksDev\Telegram\Bot\Listeners\Events;

use App\Kernel;
use BaksDev\Auth\Telegram\Repository\AccountTelegramAdmin\AccountTelegramAdminInterface;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Telegram\Api\TelegramSendMessage;
use DateInterval;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
#[When(env: 'prod')]
final class NotifierExceptionListener
{
    private TelegramSendMessage $telegramSendMessage;
    private AccountTelegramAdminInterface $accountTelegramAdmin;
    private string $HOST;
    private CacheInterface $cache;

    public function __construct(
        #[Autowire(env: 'HOST')] string $HOST,
        AccountTelegramAdminInterface $accountTelegramAdmin,
        TelegramSendMessage $telegramSendMessage,
        AppCacheInterface $cache
    )
    {
        $this->telegramSendMessage = $telegramSendMessage;
        $this->accountTelegramAdmin = $accountTelegramAdmin;
        $this->HOST = $HOST;
        $this->cache = $cache->init('telegram-bot');
    }


    public function onKernelException(ExceptionEvent $event): void
    {
        $chat = $this->accountTelegramAdmin->find();

        if(!$chat)
        {
            return;
        }

        $Throwable = $event->getThrowable();

        if($Throwable->getMessage() === 'Full authentication is required to access this resource')
        {
            return;
        }

        /** Кешируем ошибку */
        $md5 = md5($Throwable->getMessage());
        /** @var CacheItemInterface $cacheItem */
        $cacheItem = $this->cache->getItem($md5);

        if($cacheItem->get() === 1)
        {
            return;
        }

        $cacheItem->set(1);
        $cacheItem->expiresAfter(DateInterval::createFromDateString('10 minutes'));
        $this->cache->save($cacheItem);

        $PATH = $Throwable->getFile();

        $substring = strstr($Throwable->getFile(), $this->HOST);

        if($substring !== false)
        {

            $PATH = substr($substring, strlen($this->HOST));
        }

        $msg = sprintf('<b>%s</b>', $Throwable->getMessage());
        $msg .= PHP_EOL;
        $msg .= PHP_EOL;
        $msg .= sprintf('<code>%s:%s</code>', $PATH, $Throwable->getLine());


        /** Символ Удалить  */
        $char = "\u274C";
        $decoded = json_decode('["'.$char.'"]');
        $remove = mb_convert_encoding($decoded[0], 'UTF-8');

        $menu[] = [
            'text' => $remove.' Удалить сообщение',
            'callback_data' => 'telegram-delete-message'
        ];

        $markup = json_encode([
            'inline_keyboard' => array_chunk($menu, 1),
        ]);

        $this
            ->telegramSendMessage
            ->chanel($chat)
            ->message($msg)
            ->markup($markup)
            ->send();

    }
}
