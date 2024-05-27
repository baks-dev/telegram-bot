<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Telegram\Bot\Repository\SecurityProfileIsGranted;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\Profile\Group\BaksDevUsersProfileGroupBundle;
use BaksDev\Users\Profile\Group\Entity\ProfileGroup;
use BaksDev\Users\Profile\Group\Entity\Role\ProfileRole;
use BaksDev\Users\Profile\Group\Entity\Role\Voter\ProfileVoter;
use BaksDev\Users\Profile\Group\Entity\Users\ProfileGroupUsers;
use BaksDev\Users\Profile\Group\Type\Prefix\Voter\RoleVoterPrefix;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class TelegramSecurity implements TelegramSecurityInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /**
     * Метод проверяет, имеется ли у профиля доверенность
     */
    public function isGrantedProfile(
        UserProfileUid|string $current,  // Профиль пользователя
        UserProfileUid|string $authority, // Профиль, кем выдана доверенность
        RoleVoterPrefix|string $voter
    ): bool
    {
        if(!class_exists(BaksDevUsersProfileGroupBundle::class))
        {
            return false;
        }

        $current = is_string($current) ? new UserProfileUid($current) : $current;
        $authority = is_string($authority) ? new UserProfileUid($authority) : $authority;
        $voter = is_string($voter) ? new RoleVoterPrefix($voter) : $voter;

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        /** Получаем группы профиля сущности */
        $dbal
            ->from(ProfileGroup::class, 'profile_group')
            ->where('profile_group.profile = :authority')
            ->setParameter('authority', $authority, UserProfileUid::TYPE);

        /** Получаем доверенность текущего профиля */
        $dbal
            ->join(
                'profile_group',
                ProfileGroupUsers::class,
                'profile_group_users',
                'profile_group_users.prefix = profile_group.prefix AND profile_group_users.profile = :current'

            )
            ->setParameter('current', $current, UserProfileUid::TYPE);

        $dbal
            ->join(
                'profile_group',
                ProfileRole::class,
                'profile_group_role',
                'profile_group_role.event = profile_group.event'

            );

        $dbal
            ->join(
                'profile_group_role',
                ProfileVoter::class,
                'profile_group_voter',
                'profile_group_voter.role = profile_group_role.id AND profile_group_voter.prefix = :voter'
            )
            ->setParameter('voter', $voter, RoleVoterPrefix::TYPE);


        return $dbal->enableCache('')->fetchExist();
    }
}