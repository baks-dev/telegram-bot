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

namespace BaksDev\Telegram\Bot\Controller;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Services\Messenger\MessageDispatchInterface;
use BaksDev\Telegram\Api\TelegramGetFile;
use BaksDev\Telegram\Api\TelegramSendMessage;
use BaksDev\Telegram\Bot\Messenger\Callback\Ping\TelegramChatPingUid;
use BaksDev\Telegram\Bot\Messenger\Callback\Start\TelegramChatStart;
use BaksDev\Telegram\Bot\Messenger\Callback\TelegramCallbackMessage;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\GetTelegramBotSettingsInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zxing\QrReader;

#[RoleSecurity('ROLE_USER')]
final class IndexController extends AbstractController
{
    /**
     * Идентификатор чата
     */
    private ?int $chat = null;

    /**
     * Текст полученного сообщения
     */
    private ?string $text = null;

    /**
     * Значение раздела, в котором происходят задания
     */
    private mixed $callback = null;

    /**
     * Идентификатор задания (QR)
     */
    private mixed $identifier = null;

    /**
     * Идентификатор последнего сообщения
     */
    private mixed $last;


    private ApcuAdapter $cache;


    /**
     * Конечный адрес для обратных сообщений Telegram
     */
    #[Route('/telegram/endpoint', name: 'telegram.endpoint', methods: ['GET', 'POST'])]
    public function index(
        #[TaggedIterator('baks.telegram.callback')] iterable $callback,
        Request $request,
        MessageDispatchInterface $messageDispatch,
        GetTelegramBotSettingsInterface $UsersTableTelegramSettings,
        TelegramSendMessage $sendMessage, // Установки токена и чата в TelegramBotAuthenticator
        TelegramGetFile $telegramGetFile,
        TranslatorInterface $translator,
        LoggerInterface $logger,

    ): Response
    {

        $content = json_decode($request->getContent(), true);

        if(!$content)
        {
            return new JsonResponse(['success']);
        }


        //return new JsonResponse(['success']);

        $this->cache = new ApcuAdapter('TelegramBot');

        /**
         * Если пользователь кликнул по кнопке
         */
        if(isset($content['callback_query']))
        {
            $this->chat = (int) $content['callback_query']['message']['chat']['id'];

            /**
             * Колбек вернул идентификатор - присваиваем identifier не сбрасывая callback
             */
            if(preg_match('{^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$}Di', $content['callback_query']['data']))
            {
                $this->identifier = $content['callback_query']['data'];

                $this->cache->delete('identifier-'.$this->chat);
                $this->cache->get('identifier-'.$this->chat, function(ItemInterface $item) {
                    $item->expiresAfter(60 * 60 * 24);
                    return $this->identifier;
                });
            }

            /**
             * Колбек вызова класса - присваиваем callback
             */
            else
            {
                $this->callback = $content['callback_query']['data'];

                $this->cache->delete('callback-'.$this->chat);
                $this->cache->get('callback-'.$this->chat, function(ItemInterface $item) {
                    $item->expiresAfter(60 * 60 * 24);
                    return $this->callback;
                });
            }
        }


        /**
         * Если пользователем передан текст сообщения
         */
        if(!isset($content['callback_query']))
        {
            /* Устанавливаем локаль согласно чату */
            //$language = $content['message']['from']['language_code'];
            //$localeSwitcher->setLocale($language);
            //$id = $content['message']['message_id'];


            $this->chat = $content['message']['from']['id'];
            $this->text = $content['message']['text'] ?? null;

            /**
             * Если текст сообщения = идентификатор - присваиваем
             */
            if($this->text && preg_match('{^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$}Di', $this->text))
            {
                $this->identifier = $this->text;

                $this->cache->delete('identifier-'.$this->chat);
                $this->cache->get('identifier-'.$this->chat, function(ItemInterface $item) {
                    $item->expiresAfter(60 * 60 * 24);
                    return $this->identifier;
                });
            }

        }


        /** Если пользователем передан QR  */
        if(isset($content['message']['photo']))
        {
            /** Получаем файл изображения QR */
            $telegramGetFile->token($UsersTableTelegramSettings->getToken());

            $QRdata = null;

            /** Скачиваем по порядку фото для анализа  */
            foreach($content['message']['photo'] as $photo)
            {

                $file = $telegramGetFile
                    //->token($settings->getToken())
                    ->file($photo['file_id'])
                    ->send(false);

                $qrcode = new QrReader($file['tmp_file']);
                $QRdata = (string) $qrcode->text(); // декодированный текст из QR-кода

                /** Удаляем файл после анализа */
                unlink($file['tmp_file']);

                if($QRdata && preg_match('{^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$}Di', $QRdata))
                {
                    $this->identifier = $QRdata;
                    $this->cache->delete('identifier-'.$this->chat);
                    $this->cache->get('identifier-'.$this->chat, function(ItemInterface $item) {
                        $item->expiresAfter(60 * 60 * 24);
                        return $this->identifier;
                    });
                }
            }

            if($QRdata === null)
            {
                $sendMessage
                    ->message('Не можем распознать')
                    ->send(false);

                $this->cache->delete('identifier-'.$this->chat);
            }
        }


        $this->callback = $this->cache->getItem('callback-'.$this->chat)->get();
        $this->identifier = $this->cache->getItem('identifier-'.$this->chat)->get();


        if($this->text === '/ping')
        {
            $messageDispatch->dispatch(new TelegramChatPingUid(TelegramChatPingUid::TEST), transport: 'telegram');
            return new JsonResponse(['success']);

        }

        /**
         * Вызываем меню выбора раздела
         */
        if($this->text === '/start' || $this->callback === null)
        {
            $this->cache->delete('callback-'.$this->chat);
            $this->cache->delete('identifier-'.$this->chat);

            $menu = null;

            foreach($callback as $call)
            {
                if($this->isGranted($call->getRole()))
                {
                    $menu[] = [
                        'text' => $translator->trans($call->getRole().'.name', domain: 'security'),
                        'callback_data' => $call->getClass(),
                    ];
                }
            }


            if($menu)
            {
                $markup = json_encode([
                    'inline_keyboard' => array_chunk($menu, 1),
                ]);

                /** Отправляем пользовательское сообщение  */
                $sendMessage
                    ->message('<b>Выберите пожалуйста раздел:</b>')
                    ->markup($markup)
                    ->send();

                return new JsonResponse(['success']);
            }

            /** Отправляем пользовательское сообщение  */
            $sendMessage
                ->message('К сожалению нам нечего Вам предложить')
                ->send(false);
        }


        /**
         * Если имеется Callback и идентификатор - инициируем класс с идентификатором
         */
        if($this->callback && $this->identifier)
        {
            $instance = $this->callback;
            $callbackClass = new $instance($this->identifier);
            $messageDispatch->dispatch(new TelegramCallbackMessage($callbackClass, $this->chat, $this->text), transport: 'telegram');
            return new JsonResponse(['success']);
        }


        if($this->callback && !$this->identifier)
        {

            $section = null;
            foreach($callback as $call)
            {
                if($call->getClass() === $this->callback)
                {
                    $section = $translator->trans($call->getRole().'.name', domain: 'security');
                    break;
                }
            }

            /**
             * Передается ID продукта
             * либо CONST торгового предложения
             * либо CONST множественного варианта предложения
             * либо CONST модификации множественного варианта предложения
             */
            $response = $sendMessage
                ->message(sprintf(" %s \nВышлите QR продукта, либо его идентификатор",
                    $section ? ' <b>'.$section.':</b>' : ''))
                ->send(false);


            $this->last = $response['result']['message_id'];
            $this->cache->delete('last-'.$this->chat);
            $this->cache->get('last-'.$this->chat, function(ItemInterface $item) {
                $item->expiresAfter(60 * 60 * 24);
                return $this->last;
            });

        }

        return new JsonResponse(['success']);
    }
}
