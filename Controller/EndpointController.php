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
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\GetTelegramBotSettingsInterface;
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Telegram\Request\TelegramRequestInterface;
use BaksDev\Users\Profile\Group\Repository\ExistRoleByProfile\ExistRoleByProfileInterface;
use DateInterval;
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
//#[RoleSecurity('ROLE_USER')]
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
        ExistRoleByProfileInterface $existRoleByProfile,
        TelegramRequest $telegramRequest
    ): Response
    {
        if($telegramRequest->request() instanceof  TelegramRequestInterface)
        {
            $messageDispatch->dispatch(
                new TelegramEndpointMessage($telegramRequest->request()),
                transport: 'telegram-bot'
            );
        }

        return new JsonResponse(['success']);

        $content = json_decode($request->getContent(), true);

        // $logger->critical($request->getContent());

        if(!$content)
        {
            return new JsonResponse(['success']);
        }

        $AppCache = $cache->init('telegram-bot');


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


        $itemCallback = $AppCache->getItem('callback-'.$this->chat);
        $this->callback = $itemCallback->get();

        $itemIdentifier = $AppCache->getItem('identifier-'.$this->chat);
        $this->identifier = $itemIdentifier->get();


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
                $itemIdentifier->set($this->identifier);
                $itemIdentifier->expiresAfter(DateInterval::createFromDateString('1 day'));
                $AppCache->save($itemIdentifier);

                $logger->info(sprintf('Пользователь нажал кнопку с идентификатором: %s', $this->identifier), $content);
            }

            /**
             * Колбек вызова класса - присваиваем callback
             */
            else
            {
                /** Сохраняем Сallback */
                $this->callback = $content['callback_query']['data'];

                $itemCallback->set($this->callback);
                $itemCallback->expiresAfter(DateInterval::createFromDateString('1 day'));
                $AppCache->save($itemCallback);

                $logger->info(sprintf('Пользователь нажал кнопку Callback: %s', $this->callback), $content);

                /** Если класса Callback не существует */
                if(!class_exists($this->callback))
                {
                    $this->callback = null;
                }
            }
        }

        /**
         * Вызываем меню выбора раздела
         */

        if($this->text === '/start' || empty($this->callback))
        {
            $AppCache->delete('callback-'.$this->chat);
            $AppCache->delete('identifier-'.$this->chat);
            $AppCache->delete('fixed-'.$this->identifier);

            $menu = null;


            /** Получаем роли профиля пользователя */


            foreach($callback as $call)
            {

                $isExist = $existRoleByProfile->isExistRole($this->getProfileUid(), $call->getRole());

                if($isExist)
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

                    $itemIdentifier->set($this->identifier);
                    $itemIdentifier->expiresAfter(DateInterval::createFromDateString('1 day'));
                    $AppCache->save($itemIdentifier);

                    $logger->info(sprintf('Пользователь отправил QR с идентификатором: %s', $this->identifier), $content);
                }
            }

            if($QRdata === null)
            {
                $sendMessage
                    ->message('Не можем распознать')
                    ->send(false);

                $AppCache->delete('identifier-'.$this->chat);

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
            $lastMessage = $AppCache->getItem('last-'.$this->chat);
            $lastMessage->set($response['result']['message_id']);
            $itemIdentifier->expiresAfter(DateInterval::createFromDateString('1 day'));
            $AppCache->save($lastMessage);

            return new JsonResponse(['success']);
        }


        /**
         * Если имеется Callback и идентификатор - инициируем класс с идентификатором
         */

        $instance = $this->callback;
        $callbackClass = new $instance($this->identifier);
        $messageDispatch->dispatch(new TelegramCallbackMessage($callbackClass, $this->chat, $this->text));

        return new JsonResponse(['success']);

    }
}


