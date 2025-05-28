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
use BaksDev\Menu\Admin\Repository\MenuAdmin\MenuAdminInterface;
use BaksDev\Menu\Admin\Repository\MenuAdmin\MenuAdminPathResult;
use BaksDev\Menu\Admin\Type\Section\MenuAdminSectionUid;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramDeleteMessageHandler;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Bot\Repository\SecurityProfileIsGranted\TelegramSecurityInterface;
use BaksDev\Telegram\Builder\ReplyKeyboardMarkup\ReplyKeyboardButton;
use BaksDev\Telegram\Builder\ReplyKeyboardMarkup\ReplyKeyboardMarkup;
use BaksDev\Telegram\Request\Type\TelegramRequestCallback;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateInterval;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Разделы меню, к которым у пользователя есть доступ
 *
 * prev @see TelegramAuthorityHandler
 * next @see TelegramMenuSectionsHandler
 */
#[AsMessageHandler()]
final readonly class TelegramMenuAuthorityHandler
{
    public const string KEY = 'C22HJ3I9qtH';

    private CacheInterface $cache;

    public function __construct(
        #[Target('telegramLogger')] private LoggerInterface $logger,
        private AppCacheInterface $appCache,
        private TelegramSendMessages $telegramSendMessage,
        private ActiveProfileByAccountTelegramInterface $activeProfileByAccountTelegram,
        private TelegramSecurityInterface $telegramSecurity,
        private MenuAdminInterface $MenuAdmin,
        private ReplyKeyboardMarkup $keyboardMarkup,
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

        /** Профиль пользователя по id телеграм чата */
        $profile = $this->activeProfileByAccountTelegram->findByChat($telegramRequest->getChatId());

        if(false === ($profile instanceof UserProfileUid))
        {
            $this->logger->warning(__CLASS__.':'.__LINE__.'Запрос от не авторизированного пользователя', [
                '$profile' => $profile,
            ]);
            return;
        }

        /** Идентификатор профиля, к которому есть доступ */
        $authority = $telegramRequest->getIdentifier();

        if(is_null($authority))
        {
            $this->logger->warning(__CLASS__.':'.__LINE__.'Не передан идентификатор профиля $authority');
            return;
        }

        /** Всегда пересохраняем идентификатор в коревом разделе меню */
        $cacheKey = md5($telegramRequest->getChatId().$profile);
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->expiresAfter(DateInterval::createFromDateString('1 day'));
        $cacheItem->set($authority);
        $this->cache->save($cacheItem);

        /** Готовим сообщение для отправки */
        $this
            ->telegramSendMessage
            ->chanel($telegramRequest->getChatId());

        /** Строим корневое меню */
        $authorityMenu = $this->authorityRootMenu($profile, $authority);

        /** Меню пустое если у пользователя нет доступов */
        if(is_null($authorityMenu))
        {
            $this->logger->warning(__CLASS__.':'.__LINE__.'У данного профиля нет доступа к разделам меню в этом магазине', [
                '$profile' => $profile
            ]);

            $this->keyboardMarkup
                ->addNewRow(
                    (new ReplyKeyboardButton)
                        ->setText('Выход')
                        ->setCallbackData(TelegramDeleteMessageHandler::DELETE_KEY)
                );

            /** Сообщаем об ошибке */
            $this
                ->telegramSendMessage
                ->message('<b>Нет доступа к разделам меню в этом магазине</b>')
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
                        ->setText('Выход')
                        ->setCallbackData(TelegramDeleteMessageHandler::DELETE_KEY)
                );

            /** Сообщаем об ошибке */
            $this
                ->telegramSendMessage
                ->message('<b>Внутренняя ошибка сервера. Обратитесь к администратору</b>')
                ->markup($this->keyboardMarkup)
                ->delete([$telegramRequest->getId(), $telegramRequest->getLast()])
                ->send();

            $message->complete();
            return;
        }

        /** Отправляем действия с клавиатурой */
        $this
            ->telegramSendMessage
            ->message('<b>Выберите раздел</b>')
            ->markup($this->keyboardMarkup)
            ->delete([$telegramRequest->getId(), $telegramRequest->getLast()])
            ->send();

        $message->complete();
    }

    /** Корневое меню */
    private function authorityRootMenu(UserProfileUid|string $profile, UserProfileUid|string $authority): array|null
    {
        /** Получаем разделы и подразделы меню */
        $menu = $this->MenuAdmin->findAll();

        /**
         * Перестроенное меню из разделов, с учетом наличие доступов по ролям
         * @var array<string, string>|null $authorityMenu
         */
        $authorityMenu = null;

        foreach($menu as $menuRoot)
        {

            /**
             * Фильтруем секции в каждом из разделов меню
             */
            $rootSections = $menuRoot->getPath();

            if(is_null($rootSections))
            {
                continue;
            }

            /** @var MenuAdminPathResult $section */
            $authRootSections = array_filter($rootSections, function(object $section) use (
                $profile,
                $authority,
                $menuRoot,
            ) {

                /** Проверка доступа к секции меню по роли */
                return $this->telegramSecurity->isGranted(
                    $profile,
                    $section->getRole(),
                    $authority
                );
            });

            /** Если есть доступ по роли - добавляем раздел с доступными секциями меню */
            if(false === empty($authRootSections))
            {
                /**
                 * $menuRoot['name'] - название раздела
                 * $menuRoot['id'] - id раздела
                 */
                $authorityMenu[$menuRoot->getName()] = $menuRoot->getSectionId();
            }
        }

        return $authorityMenu;
    }

    private function keyboard(array $menu): bool
    {
        $this->keyboardMarkup->setMaxRowButtons(1);

        /**
         * @var string $menuName - название раздела меню
         * @var MenuAdminSectionUid $menuSection - идентификатор для обозначения принадлежности всех кнопок определенной секции меню
         */
        foreach($menu as $menuName => $menuSection)
        {
            $callbackData = TelegramMenuSectionsHandler::KEY.'|'.$menuSection;
            $callbackDataSize = strlen($callbackData);

            if($callbackDataSize > 64)
            {
                $this->logger->critical(
                    __CLASS__.':'.__LINE__.
                    'Ошибка создания клавиатуры для чата: Превышен максимальный размер callback_data',
                    [
                        '$callbackData' => $callbackData,
                        '$callbackDataSize' => $callbackDataSize,
                    ]);
                return false;
            }

            $button = new ReplyKeyboardButton;
            $button
                ->setText($menuName)
                ->setCallbackData($callbackData);

            $this->keyboardMarkup->addCurrentRow($button);
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

