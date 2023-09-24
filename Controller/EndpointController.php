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

use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Telegram\Api\TelegramGetFile;
use BaksDev\Telegram\Api\TelegramSendMessage;
use BaksDev\Telegram\Bot\Messenger\Callback\TelegramCallbackMessage;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\GetTelegramBotSettingsInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zxing\QrReader;

#[AsController]
#[RoleSecurity('ROLE_USER')]
final class EndpointController extends AbstractController
{
    /**
     * Идентификатор чата
     */
    private ?string $chat = null;

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


    private CacheInterface $cache;


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
        AppCacheInterface $cache,
    ): Response
    {

        $content = json_decode($request->getContent(), true);

        if(!$content)
        {
            return new JsonResponse(['success']);
        }

        $this->cache = $cache->init('TelegramBot');


        /**
         * Определяем идентификатор чата
         */

        if(isset($content['callback_query']))
        {
            $this->chat = (string) $content['callback_query']['message']['chat']['id'];
            $this->text = $content['callback_query']['data'];
        }

        if(isset($content['message']['from']['id']))
        {
            $this->chat = (string) $content['message']['from']['id']; // идентификатор чата
            $this->text = $content['message']['text'] ?? null; // текст полученного сообщения
        }

        $this->callback = $this->cache->getItem('callback-'.$this->chat)->get();

        /**
         * Если пользователь кликнул по кнопке
         */
        if(isset($content['callback_query']))
        {
            /**
             * Колбек вернул идентификатор - присваиваем identifier не сбрасывая callback
             */
            if(preg_match('{^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$}Di', $content['callback_query']['data']))
            {
                /** Сохраняем идентификатор */
                $this->identifier = $content['callback_query']['data'];
                $identifier = $this->cache->getItem('identifier-'.$this->chat);
                $identifier->set($this->identifier);
                $identifier->expiresAfter(60 * 60 * 24);
                $this->cache->save($identifier);

                $logger->info(sprintf('Пользователь нажал кнопку с идентификатором: %s', $this->identifier), $content);
            }

            /**
             * Колбек вызова класса - присваиваем callback
             */
            else
            {
                /** Сохраняем Сallback */
                $this->callback = $content['callback_query']['data'];
                $callbackClass = $this->cache->getItem('callback-'.$this->chat);
                $callbackClass->set($this->callback);
                $callbackClass->expiresAfter(60 * 60 * 24);
                $this->cache->save($callbackClass);

                $logger->info(sprintf('Пользователь нажал кнопку Callback: %s', $this->callback), $content);

                /** Если класса Callback не существует */
                if(!class_exists($this->callback))
                {
                    return new JsonResponse(['success']);
                }
            }
        }





        /**
         * Вызываем меню выбора раздела
         */

        if($this->text === '/start' || empty($this->callback))
        {
            $this->cache->delete('callback-'.$this->chat);
            $this->cache->delete('identifier-'.$this->chat);

            $menu = null;

            foreach($callback as $call)
            {
                //if($this->isGranted($call->getRole()) || $this->isGranted('ROLE_ADMIN'))
                //{
                $menu[] = [
                    'text' => $translator->trans($call->getRole().'.name', domain: 'security'),
                    'callback_data' => $call->getClass(),
                ];
                //}
            }


            if($menu)
            {
                $markup = json_encode([
                    'inline_keyboard' => array_chunk($menu, 1),
                ], JSON_THROW_ON_ERROR);

                /** Отправляем пользовательское сообщение  */
                $sendMessage
                    ->message('<b>Выберите пожалуйста раздел:</b>')
                    ->markup($markup)
                    ->send();


                /** Сбрасываем фиксацию  */


                return new JsonResponse(['success']);
            }

            /** Отправляем пользовательское сообщение  */
            $sendMessage
                ->message('К сожалению нам нечего Вам предложить')
                ->send(false);

            return new JsonResponse(['success']);
        }

        $logger->critical($this->callback);



        /**
         * Если пользователем передан QR
         */

        if(isset($content['message']['photo']))
        {
            /** Получаем файл изображения QR */
            $telegramGetFile->token($UsersTableTelegramSettings->getToken());

            $QRdata = null;

            /** Скачиваем по порядку фото для анализа  */
            foreach($content['message']['photo'] as $photo)
            {
                $file = $telegramGetFile
                    ->file($photo['file_id'])
                    ->send(false);

                $qrcode = new QrReader($file['tmp_file']);
                $QRdata = (string) $qrcode->text(); // декодированный текст из QR-кода

                /** Удаляем временный файл после анализа */
                unlink($file['tmp_file']);

                if($QRdata && preg_match('{^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$}Di', $QRdata))
                {
                    /** Сохраняем идентификатор */
                    $this->identifier = $QRdata;
                    $identifier = $this->cache->getItem('identifier-'.$this->chat);
                    $identifier->set($this->identifier);
                    $identifier->expiresAfter(60 * 60 * 24);
                    $this->cache->save($identifier);

                    $logger->info(sprintf('Пользователь отправил QR с идентификатором: %s', $this->identifier), $content);
                }
            }

            if($QRdata === null)
            {
                $sendMessage
                    ->message('Не можем распознать')
                    ->send(false);

                $this->cache->delete('identifier-'.$this->chat);

                return new JsonResponse(['success']);
            }
        }



        /**
         * Если в запросе отсутствует идентификатор - отправляем сообщение с требованием отправить QR
         */


        if(!$this->identifier)
        {
            /* Определяем раздел, в котором находится пользователь */
            $section = null;
            foreach($callback as $call)
            {
                if($call->getClass() === $this->callback)
                {
                    $section = $translator->trans($call->getRole().'.name', domain: 'security');
                    break;
                }
            }

            $message = $section ? '<b>'.$section.':</b>' : '';
            $message .= $section ? "\n\n" : '';
            $message .= 'Отправьте пожалуйста QR код:';
            $message .= "\n";
            $message .= '1. выберите скрепку в строке сообщения;';
            $message .= "\n";
            $message .= '2. нажмите фотоаппарат и сделайте фото;';
            $message .= "\n";
            $message .= '3. отправьте фото QR-кода.';

            $response = $sendMessage
                ->message($message)
                ->send();
            

            /** Сохраняем последнее сообщение */
            $lastMessage = $this->cache->getItem('last-'.$this->chat);
            $lastMessage->set($response['result']['message_id']);
            $lastMessage->expiresAfter(60 * 60 * 24);
            $this->cache->save($lastMessage);

            return new JsonResponse(['success']);
        }


        /**
         * Если имеется Callback и идентификатор - инициируем класс с идентификатором
         */

        $instance = $this->callback;
        $callbackClass = new $instance($this->identifier);
        $messageDispatch->dispatch(
            new TelegramCallbackMessage($callbackClass, $this->chat, $this->text),
            transport: 'telegram'
        );

        return new JsonResponse(['success']);

    }
}


