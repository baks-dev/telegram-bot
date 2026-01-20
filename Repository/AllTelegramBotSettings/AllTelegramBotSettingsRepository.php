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

declare(strict_types=1);

namespace BaksDev\Telegram\Bot\Repository\AllTelegramBotSettings;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Telegram\Bot\Entity\Active\TelegramBotSettingsActive;
use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Entity\Profile\TelegramBotSettingsProfile;
use BaksDev\Telegram\Bot\Entity\TelegramBotSettings;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Avatar\UserProfileAvatar;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;


final class AllTelegramBotSettingsRepository implements AllTelegramBotSettingsInterface
{
    private ?SearchDTO $search = null;

    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage,

    ) {}


    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    /**
     * Profile
     */
    public function profile(UserProfile|UserProfileUid|string $profile): self
    {

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    /**
     * Метод возвращает пагинатор
     */
    public function findPaginator(): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('settings.id')
            ->addSelect('settings.event')
            ->from(TelegramBotSettings::class, 'settings');


        $dbal
            ->join(
                'settings',
                TelegramBotSettingsProfile::class,
                'profile',
                'profile.event = settings.event
                      AND
                      profile.value = :profile',
            )
            ->setParameter(
                key: 'profile',
                value: ($this->profile instanceof UserProfileUid) ? $this->profile : $this->UserProfileTokenStorage->getProfile(),
                type: UserProfileUid::TYPE,
            );


        $dbal
            ->addSelect('event.url')
            ->addSelect('event.connect')
            ->join(
                'settings',
                TelegramBotSettingsEvent::class,
                'event',
                'event.id = settings.event',
            );

        /* Active */
        $dbal
            ->addSelect('active.value AS active')
            ->leftJoin(
                'event',
                TelegramBotSettingsActive::class,
                'active',
                'active.event = event.id',
            );


        /* UserProfile */
        $dbal
            ->addSelect('users_profile.event as users_profile_event')
            ->leftJoin(
                'profile',
                UserProfile::class,
                'users_profile',
                'users_profile.id = profile.value',
            );

        $dbal->leftJoin(
            'users_profile',
            UserProfileEvent::class,
            'users_profile_event',
            'users_profile_event.id = users_profile.event',
        );

        /* Personal */
        $dbal->addSelect('users_profile_personal.username AS users_profile_username');

        $dbal->leftJoin(
            'users_profile_event',
            UserProfilePersonal::class,
            'users_profile_personal',
            'users_profile_personal.event = users_profile_event.id',
        );

        /* Avatar img */
        $dbal->addSelect("
                CASE
                WHEN users_profile_avatar.name IS NOT NULL
                THEN CONCAT ( '/upload/".$dbal->table(UserProfileAvatar::class)."' , '/', users_profile_avatar.name)
                ELSE NULL
                END AS users_profile_avatar
            ");

        $dbal->addSelect("users_profile_avatar.ext AS users_profile_avatar_ext");
        $dbal->addSelect('users_profile_avatar.cdn AS users_profile_avatar_cdn');

        $dbal->leftJoin(
            'users_profile_event',
            UserProfileAvatar::class,
            'users_profile_avatar',
            'users_profile_avatar.event = users_profile_event.id',
        );


        /* Search */
        if(true === ($this->search instanceof SearchDTO) && $this->search->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('users_profile_personal.username')
                ->addSearchLike('event.url');
        }

        return $this->paginator->fetchAllHydrate($dbal, AllTelegramBotSettingsPaginatorResult::class);

    }
}
