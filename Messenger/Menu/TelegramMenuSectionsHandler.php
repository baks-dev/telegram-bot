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

namespace BaksDev\Telegram\Bot\Messenger\Menu;

use BaksDev\Auth\Telegram\Repository\ActiveProfileByAccountTelegram\ActiveProfileByAccountTelegramInterface;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Menu\Admin\Repository\MenuAdmin\MenuAdminPathResult;
use BaksDev\Menu\Admin\Repository\MenuAdminBySectionId\MenuAdminBySectionIdInterface;
use BaksDev\Menu\Admin\Repository\MenuAdminBySectionId\MenuAdminBySectionsResult;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramDeleteMessageHandler;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Bot\Repository\SecurityProfileIsGranted\TelegramSecurityInterface;
use BaksDev\Telegram\Builder\ReplyKeyboardMarkup\ReplyKeyboardButton;
use BaksDev\Telegram\Builder\ReplyKeyboardMarkup\ReplyKeyboardMarkup;
use BaksDev\Telegram\Request\Type\TelegramRequestCallback;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Секции выбранного раздела, к которым у пользователя есть доступ
 *
 * prev @see TelegramMenuAuthorityHandler
 * next @see TelegramUserTableHandler
 */
#[AsMessageHandler()]
final class TelegramMenuSectionsHandler
{
    public const string KEY = 'Vt5J0sVV69M';

    /** Идентификатор раздела */
    private string|null $sectionId = null;

    private CacheInterface $cache;

    public function __construct(
        #[Target('telegramLogger')] private readonly LoggerInterface $logger,
        private readonly ActiveProfileByAccountTelegramInterface $activeProfileByAccountTelegram,
        private readonly TelegramSecurityInterface $telegramSecurity,
        private readonly MenuAdminBySectionIdInterface $menuAdminBySection,
        private readonly ReplyKeyboardMarkup $keyboardMarkup,
        private readonly TelegramSendMessages $telegramSendMessage,
        AppCacheInterface $appCache,
    )
    {
        $this->cache = $appCache->init('telegram');
    }

    public function __invoke(TelegramEndpointMessage $message): void
    {
        $telegramRequest = $message->getTelegramRequest();

        /** Проверка на тип запроса */
        if(false === ($telegramRequest instanceof TelegramRequestCallback))
        {
            return;
        }

        /** Проверка идентификатора кнопки */
        if(false === ($telegramRequest->getCall() === self::KEY))
        {
            return;
        }

        /** Проверка идентификатора секции меню из callback_data */
        if(empty($telegramRequest->getIdentifier()))
        {
            $this->logger->warning(
                'telegram-bot: Ошибка получения идентификатора раздела',
                ['$telegramRequest->getIdentifier()' => $telegramRequest->getIdentifier(), self::class.':'.__LINE__],
            );

            return;
        }

        /** Присваиваем идентификатора секции меню из callback_data */
        $this->sectionId = $telegramRequest->getIdentifier();

        /** Профиль пользователя по id телеграм чата */
        $profile = $this->activeProfileByAccountTelegram
            ->findByChat($telegramRequest->getChatId());

        if(false === ($profile instanceof UserProfileUid))
        {
            $this->logger->warning('telegram-bot: Запрос от не авторизированного пользователя', [self::class.':'.__LINE__]);

            return;
        }

        $cacheKey = md5($telegramRequest->getChatId().$profile);

        /**
         * Идентификатор профиля, к которому есть доступ
         *
         * @var string|null $authority
         */
        $authority = $this->cache->getItem($cacheKey)->get();

        if(is_null($authority))
        {
            $this->logger->warning('telegram-bot: Не найден идентификатор $authority', [self::class.':'.__LINE__]);

            return;
        }

        /** Готовим сообщение для отправки */
        $this
            ->telegramSendMessage
            ->chanel($telegramRequest->getChatId());

        /** Строим меню с разделами */
        $authorityMenu = $this->authorityMenuSections($profile, $authority);

        /** Меню пустое - если у пользователя нет доступов по ролям */
        if(is_null($authorityMenu))
        {
            $this->logger->warning(
                'telegram-bot: У данного профиля нет доступа к разделу меню',
                ['$profile' => $profile, self::class.':'.__LINE__],
            );

            $this->keyboardMarkup
                ->addNewRow(
                    (new ReplyKeyboardButton)
                        ->setText('Выход')
                        ->setCallbackData(TelegramDeleteMessageHandler::DELETE_KEY),
                );

            /** Сообщаем об ошибке */
            $this
                ->telegramSendMessage
                ->message('<b>Нет доступа к разделам</b>')
                ->markup($this->keyboardMarkup)
                ->delete([$telegramRequest->getId(), $telegramRequest->getLast()])
                ->send();

            $message->complete();

            return;
        }

        /** Если ошибка при построении клавиатуры */
        if(false === $this->keyboard($authorityMenu))
        {
            $this->keyboardMarkup
                ->addNewRow(
                    (new ReplyKeyboardButton)
                        ->setText('Назад')
                        ->setCallbackData(TelegramDeleteMessageHandler::DELETE_KEY),
                );

            /** Сообщаем об ошибке */
            $this
                ->telegramSendMessage
                ->message('<b>Внутренняя ошибка сервера. Обратитесь к администратору</b>')
                ->markup($this->keyboardMarkup)
                ->send();

            $message->complete();

            return;
        }

        /** Отправляем действия с клавиатурой */
        $description = $this->keyboardMarkup->getDescription();
        $this
            ->telegramSendMessage
            ->message("<b>$description</b>")
            ->markup($this->keyboardMarkup)
            ->delete([$telegramRequest->getId(), $telegramRequest->getLast()])
            ->send();

        $message->complete();
    }

    /**
     * @return array<int, MenuAdminPathResult>|null
     */
    private function authorityMenuSections(UserProfileUid|string $profile, UserProfileUid|string $authority): array|null
    {
        /** Получаем раздел по идентификатору секции, полученной из callback_data */
        $menuSections = $this->menuAdminBySection
            ->onSectionId($this->sectionId)
            ->findOne();

        /** Прерываем, если раздел по идентификатору секции не найден */
        if(false === ($menuSections instanceof MenuAdminBySectionsResult))
        {
            return null;
        }

        $sections = $menuSections->getPath();

        /** Прерываем, если разделов нет */
        if(is_null($sections))
        {
            return null;
        }

        /**
         * Перестроенное меню из разделов, с учетом наличие доступов по ролям
         *
         * @var array<int, MenuAdminPathResult>|null $authorityMenu
         */
        $authoritySections = null;

        foreach($menuSections->getPath() as $section)
        {
            /** Доступ к секции по роли */
            $isGranted = $this->telegramSecurity->isGranted(
                $profile,
                $section->getRole(),
                $authority,
            );

            /** Если есть доступ по роли это не заголовок секции - формируем меню из разделов */
            if($isGranted and $section->isNotSectionHeader())
            {
                /** Название секции */
                $this->keyboardMarkup->setDescription($section->getName(), 'Выберите раздел');

                /** Разделы меню */
                $authoritySections[] = $section;
            }
        }

        return $authoritySections;
    }

    /**
     * @param array<int, MenuAdminPathResult> $menu
     */
    private function keyboard(array $menu): bool
    {
        foreach($menu as $menuSection)
        {
            $callbackData = $menuSection->getKey().'| ';

            $callbackDataSize = strlen($callbackData);

            if($callbackDataSize > 64)
            {
                $this->logger->critical(
                    'telegram-bot: Ошибка создания клавиатуры для чата: Превышен максимальный размер callback_data',
                    [
                        '$callbackData' => $callbackData,
                        '$callbackDataSize' => $callbackDataSize,
                        self::class.':'.__LINE__,
                    ]);

                return false;
            }

            $button = new ReplyKeyboardButton;
            $button
                ->setText($menuSection->getName()) // название раздела меню
                ->setCallbackData($callbackData);

            $this->keyboardMarkup->addNewRow($button);
        }

        /** Кнопка назад */
        $backButton = new ReplyKeyboardButton;
        $backButton
            ->setText('Выход')
            ->setCallbackData(TelegramDeleteMessageHandler::DELETE_KEY);

        $this->keyboardMarkup->addNewRow($backButton);

        return true;
    }
}

