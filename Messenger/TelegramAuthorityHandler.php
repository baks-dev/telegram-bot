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
use BaksDev\Menu\Admin\Repository\MenuAuthority\MenuAuthorityInterface;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Builder\ReplyKeyboardMarkup\ReplyKeyboardButton;
use BaksDev\Telegram\Builder\ReplyKeyboardMarkup\ReplyKeyboardMarkup;
use BaksDev\Telegram\Request\Type\TelegramBotCommands;
use BaksDev\Telegram\Request\Type\TelegramRequestCallback;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Выбор профиля (магазина), к которому есть доступ у другого профиля
 *
 * newt @see TelegramMenuAuthorityHandler
 */
#[AsMessageHandler()]
final readonly class TelegramAuthorityHandler
{
    public const string DELETE_KEY = 'cSD9nph';

    public const string KEY = 'WZjeBkwY';

    public function __construct(
        #[Target('telegramLogger')] private LoggerInterface $logger,
        private TelegramSendMessages $telegramSendMessage,
        private ActiveProfileByAccountTelegramInterface $activeProfileByAccountTelegram,
        private MenuAuthorityInterface $menuAuthority,
    ) {}

    /** */
    public function __invoke(TelegramEndpointMessage $message): void
    {

        /** @var TelegramRequestMessage $telegramRequest */
        $telegramRequest = $message->getTelegramRequest();

        $this
            ->telegramSendMessage
            ->chanel($telegramRequest->getChatId());

        if(false === ($telegramRequest instanceof TelegramRequestMessage))
        {
            return;
        }

        /** Проверка текста сообщения по значению триггера */
        if(false === (in_array($telegramRequest->getText(), TelegramBotCommands::MENU->command())))
        {
            return;
        }

        /** Профиль пользователя по id телеграм чата */
        $profile = $this->activeProfileByAccountTelegram
            ->findByChat($telegramRequest->getChatId());

        if(false === ($profile instanceof UserProfileUid))
        {
            $this->logger->critical('Запрос от не авторизированного пользователя', [$profile]);
            return;
        }

        /** Профили, к которым у текущего профиля есть доверенности */
        $userProfileMenu = $this->menuAuthority->findAll($profile);

        if(is_null($userProfileMenu))
        {
            $this->logger->critical('Отсутствуют доверенности', [$profile]);
            return;
        }

        /** Клавиатура с выбором профилей */
        $inlineKeyboard = $this->keyboard($userProfileMenu);

        if(is_null($inlineKeyboard))
        {
            $this->logger->critical('Ошибка создания клавиатуры для чата');

            /** Сообщаем об ошибке */
            $this
                ->telegramSendMessage
                ->message('<b>Внутренняя ошибка сервера. Обратитесь к администратору</b>')
                ->send();

            $message->complete();

            return;
        }

        /** Отправляем действие с клавиатурой */
        $this
            ->telegramSendMessage
            ->message('<b>Выберите профиль</b>')
            ->markup($inlineKeyboard)
            ->delete([$telegramRequest->getId(), $telegramRequest->getLast()])
            ->send();

        $message->complete();
    }

    /** Собираем клавиатуру с кнопками */
    private function keyboard(array $menu): array|null
    {
        $inlineKeyboard = new ReplyKeyboardMarkup();

        foreach($menu as $menuSection)
        {
            $callbackData = self::KEY.'|'.$menuSection['authority'];
            $callbackDataSize = strlen($callbackData);

            if($callbackDataSize > 64)
            {
                $this->logger->critical('Ошибка создания клавиатуры для чата: Превышен максимальный размер callback_data', [$callbackData, $callbackDataSize]);
                return null;
            }

            $button = new ReplyKeyboardButton;

            $button
                ->setText($menuSection['authority_username'])
                ->setCallbackData($callbackData);

            $inlineKeyboard->addNewRow($button);
        }

        return $inlineKeyboard->build();
    }
}

