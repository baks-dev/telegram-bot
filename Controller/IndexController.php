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

use BaksDev\Auth\Telegram\Bot\Login\Email\LoginTelegram;
use BaksDev\Auth\Telegram\Repository\ExistAccountTelegram\ExistAccountTelegramInterface;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramHandler;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Services\Messenger\MessageDispatchInterface;
use BaksDev\Telegram\Api\TelegramDeleteMessage;
use BaksDev\Telegram\Api\TelegramGetFile;
use BaksDev\Telegram\Api\TelegramSendMessage;
use BaksDev\Telegram\Bot\Messenger\Callback\Ping\TelegramChatPingUid;
use BaksDev\Telegram\Bot\Messenger\Callback\Pong\TelegramChatPongUid;
use BaksDev\Telegram\Bot\Messenger\Callback\TelegramCallbackMessage;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\GetTelegramBotSettingsInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Cache\ItemInterface;
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
     * Кешированные данные
     */
    private ?array $data = null;


    /**
     * Конечный адрес для обратных сообщений Telegram
     */
    #[Route('/telegram/endpoint', name: 'telegram.endpoint', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MessageDispatchInterface $messageDispatch,
        LocaleSwitcher $localeSwitcher,
        ExistAccountTelegramInterface $existAccountTelegram,
        GetTelegramBotSettingsInterface $UsersTableTelegramSettings,
        LoggerInterface $logger,
        TelegramSendMessage $sendMessage,
        TelegramDeleteMessage $deleteMessage,
        TelegramGetFile $telegramGetFile,
        LoginTelegram $loginTelegram,
        AccountTelegramHandler $AccountTelegramHandler,
    ): Response
    {

        //return new JsonResponse(['success']);

        $content = json_decode($request->getContent(), true);

        if(!$content)
        {
            return new JsonResponse(['success']);
        }

        if(isset($content['callback_query']))
        {
            $this->chat = $content['callback_query']['message']['from']['id'];
            $this->text = $content['callback_query']['data'];
        }
        else
        {
            /* Устанавливаем локаль согласно чату */
            //$language = $content['message']['from']['language_code'];
            //$localeSwitcher->setLocale($language);
            //$id = $content['message']['message_id'];


            $this->chat = $content['message']['from']['id'];
            $this->text = $content['message']['text'] ?? null;

        }


        /* Получаем действия пользователя */
        $ApcuAdapter = new ApcuAdapter();
        $this->data = $ApcuAdapter->get((string) $this->chat, function(ItemInterface $item) {
            $item->expiresAfter(60 * 60 * 24);
            return $this->data;
        });


        /** Инициируем настройки Telegram Bot */
        $UsersTableTelegramSettings->settings();


        /** Если передан QR  */
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

                if($QRdata)
                {
                    $this->data['callback'] = $QRdata;
                }
            }

            if($QRdata === null)
            {
                $sendMessage
                    ->message('Не можем распознать')
                    ->send(false);

                unset($this->data['callback']);
            }
        }


        if($this->text === '/ping')
        {
            $this->data['callback'] = TelegramChatPingUid::class.':'.TelegramChatPingUid::TEST;
        }

        if($this->text === '/pong')
        {
            $this->data['callback'] = TelegramChatPongUid::class.':'.TelegramChatPongUid::TEST;
        }

        if(isset($this->data['callback']))
        {
            $TelegramCallbackMessage = new TelegramCallbackMessage($this->data['callback'], $this->chat, $this->text);
            $messageDispatch->dispatch($TelegramCallbackMessage, transport: 'telegram');
        }

        return new JsonResponse(['success']);


        //$logger->critical($request->getContent());

        $settings = $UsersTableTelegramSettings->settings();

        //        /** Проверяем токен запроса */
        //        if(!$settings->equalsSecret($request->headers->get('X-Telegram-Bot-Api-Secret-Token')))
        //        {
        //            throw new RouteNotFoundException('Page Not Found');
        //        }


        $sendMessage
            ->token($settings->getToken())
            ->chanel($this->chat)
            ->message($this->getUser()->getUserIdentifier())
            ->send();

        return new JsonResponse(['success']);


        //        /* Получаем действия пользователя */
        //        $ApcuAdapter = new ApcuAdapter();
        //        $this->data = $ApcuAdapter->get((string)$this->chat, function(ItemInterface $item) {
        //            $item->expiresAfter(60 * 60 * 24);
        //            return $this->data;
        //        });
        //
        //        $sendMessage
        //            ->token($settings->getToken())
        //            ->chanel($this->chat);
        //
        //        $deleteMessage
        //            ->token($settings->getToken())
        //            ->chanel($this->chat);
        //
        //
        //        /** Удаляем предыдущее сообщение пользователя */
        //        if(isset($this->data['last']))
        //        {
        //            $deleteMessage
        //                ->delete($id)
        //                ->send();
        //
        //            unset($this->data['last']);
        //        }
        //
        //        /** Удаляем предыдущее отправленное системой сообщение */
        //        if(isset($this->data['request']))
        //        {
        //            $deleteMessage
        //                ->delete($this->data['request'])
        //                ->send();
        //
        //            unset($this->data['request']);
        //        }
        //
        //        /** Сохраняем сообщение пользователя для последующего удаления*/
        //        $this->data['last'] = $id;
        //
        //        /** Проверяем пользователя  */
        //        $existAccountTelegram->isExistAccountTelegram($this->chat);

        /** Если пользователь не зарегистрирован - проходим Авторизацию */
        //        if($text && !$existAccountTelegram->isExistAccountTelegram($this->chat))
        //        {
        //            $loginTelegram
        //                ->chat($this->chat)
        //                ->handle($text);
        //
        //            /** Сохраняем пользователя в случае успешной авторизации */
        //            if($loginTelegram->getStep() instanceof LoginStepSuccess)
        //            {
        //                $AccountTelegramDTO = new AccountTelegramDTO();
        //                $AccountTelegramDTO->setAccount($loginTelegram->getStep()->getUser());
        //                $AccountTelegramDTO->setChat($content['message']['chat']['id']);
        //
        //                if(isset($content['message']['chat']['username']))
        //                {
        //                    $AccountTelegramDTO->setUsername($content['message']['chat']['username']);
        //                }
        //
        //                if(isset($content['message']['chat']['first_name']))
        //                {
        //                    $AccountTelegramDTO->setFirstname($content['message']['chat']['first_name']);
        //                }
        //
        //                $AccountTelegram = $AccountTelegramHandler->handle($AccountTelegramDTO);
        //
        //                if(!$AccountTelegram instanceof AccountTelegram)
        //                {
        //                    $sendMessage
        //                        ->message(sprintf('%s: Ошибка авторизации', $AccountTelegram))
        //                        ->send();
        //                }
        //                else
        //                {
        //                    $request = $loginTelegram->getSendMessage()?->send(false);
        //
        //                    if($request)
        //                    {
        //                        $this->data['request'] = $request['result']['message_id'];
        //                    }
        //                }
        //            }
        //            else
        //            {
        //                $request = $loginTelegram->getSendMessage()?->send(false);
        //
        //                if($request)
        //                {
        //                    $this->data['request'] = $request['result']['message_id'];
        //                }
        //            }
        //
        //            $this->resetCache();
        //            return new JsonResponse(['success']);
        //
        //        }


    }

    /**
     * Сбрасываем и сохраняем кешированные данные
     */
    public function resetCache(): void
    {
        $ApcuAdapter = new ApcuAdapter();

        $ApcuAdapter->delete((string) $this->chat);
        $ApcuAdapter->get((string) $this->chat, function(ItemInterface $item) {
            $item->expiresAfter(60 * 60 * 24);
            return $this->data;
        });
    }
}
