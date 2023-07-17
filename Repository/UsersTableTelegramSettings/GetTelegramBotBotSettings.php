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

namespace BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings;

use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Entity\TelegramBotSettings;
use BaksDev\Telegram\Bot\Type\Settings\Id\UsersTableTelegramSettingsIdentificator;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;

final class GetTelegramBotBotSettings implements GetTelegramBotSettingsInterface
{
    /**
     * Токен авторизации Telegram-бота.
     */
    private string $token;

    /**
     * Системный токен, для проверки подлинности запроса от бота.
     */
    private string $secret;



    private Connection $connection;

    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    )
    {
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
    }

    public function getUsersTableTelegramSettingsEvent(): TelegramBotSettingsEvent
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('event');

        $qb->from(TelegramBotSettings::class, 'settings');

        $qb->where('settings.id = :identificator');

        $qb->setParameter(
            'identificator',
            new UsersTableTelegramSettingsIdentificator(),
            UsersTableTelegramSettingsIdentificator::TYPE
        );

        $qb->leftJoin(
            TelegramBotSettingsEvent::class,
            'event',
            'WITH',
            'event.id = settings.event'
        );

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result ?: new TelegramBotSettingsEvent();

    }


    public function settings(): self|bool
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('event.token');
        $qb->addSelect('event.secret');

        $qb->from(TelegramBotSettings::TABLE, 'settings');

        $qb->leftJoin(
            'settings',
            TelegramBotSettingsEvent::TABLE,
            'event',
            'event.id = settings.event'
        );

        $qb->where('settings.id = :identificator');

        $qb->setParameter(
            'identificator',
            new UsersTableTelegramSettingsIdentificator(),
            UsersTableTelegramSettingsIdentificator::TYPE
        );

        /* Кешируем результат запроса DBAL */
        $cache = new ApcuAdapter('TelegramBot');
        $config = $this->connection->getConfiguration();
        $config?->setResultCache($cache);

        $settings = $this->connection->executeCacheQuery(
            $qb->getSQL(),
            $qb->getParameters(),
            $qb->getParameterTypes(),
            new QueryCacheProfile((60 * 60 * 24 * 30 * 12))
        )->fetchAssociative();

        if($settings)
        {
            $this->token = $settings['token'];
            $this->secret = $settings['secret'];

            return $this;
        }

        return false;
    }
    
    /**
     * Token.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Secret.
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * Secret.
     */
    public function equalsSecret(string $secret): bool
    {
        return $this->secret === $secret;
    }
}
