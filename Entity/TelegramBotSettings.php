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

namespace BaksDev\Telegram\Bot\Entity;

use BaksDev\Core\Entity\EntityState;
use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Type\Settings\Event\TelegramBotSettingsEventUid;
use BaksDev\Telegram\Bot\Type\Settings\Id\UsersTableTelegramSettingsIdentificator;
use Doctrine\ORM\Mapping as ORM;

/**
 * Настройки Telegram бота
*/

#[ORM\Entity]
#[ORM\Table(name: 'telegram_bot_settings')]
class TelegramBotSettings extends EntityState
{
    /** ID */
    #[ORM\Id]
    #[ORM\Column(type: UsersTableTelegramSettingsIdentificator::TYPE)]
    private UsersTableTelegramSettingsIdentificator $id;

    /** ID События */
    #[ORM\Column(name: 'event', type: TelegramBotSettingsEventUid::TYPE, unique: true, nullable: false)]
    private TelegramBotSettingsEventUid $event;

    public function __construct()
    {
        $this->id = new UsersTableTelegramSettingsIdentificator();
    }

    public function getId(): UsersTableTelegramSettingsIdentificator
    {
        return $this->id;
    }

    public function setEvent(TelegramBotSettingsEvent|TelegramBotSettingsEventUid $event): void
    {
        $this->event = $event instanceof TelegramBotSettingsEvent ? $event->getId() : $event;
    }

    public function getEvent(): TelegramBotSettingsEventUid
    {
        return $this->event;
    }
}
