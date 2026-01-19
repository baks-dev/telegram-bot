<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Telegram\Bot\Repository\TelegramBotSecretKey;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Telegram\Bot\Entity\Active\TelegramBotSettingsActive;
use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Entity\Profile\TelegramBotSettingsProfile;
use BaksDev\Telegram\Bot\Type\Settings\Event\TelegramBotSettingsEventUid;


final class TelegramBotSecretKeyRepository implements TelegramBotSecretKeyInterface
{
    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function findKey(TelegramBotSettingsEventUid $eventUid): string|false
    {

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->addSelect('event.secret')
            ->from(TelegramBotSettingsEvent::class, 'event');

        /* Profile */
        $dbal->join(
            'event',
            TelegramBotSettingsProfile::class,
            'profile',
            'profile.event = event.id AND event.id = :eventId'
        )
            ->setParameter(
                'eventId',
                $eventUid,
                TelegramBotSettingsEventUid::TYPE
            );

        /* Настройка должна быть активна */
        $dbal->join(
            'event',
            TelegramBotSettingsActive::class,
            'active',
            'active.event = event.id AND active.value = true'
        );

        $result = $dbal->fetchAssociative();

        return false === empty($result) ? $result['secret'] : false;

    }
}