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
use BaksDev\Users\Profile\Group\Repository\ExistProfileGroup\ExistProfileGroupInterface;
use BaksDev\Users\Profile\Group\Repository\ProfileGroup\ProfileGroupByUserProfileInterface;
use BaksDev\Users\Profile\Group\Type\Prefix\Group\GroupPrefix;
use BaksDev\Users\Profile\Group\Type\Prefix\Voter\RoleVoterPrefix;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class TelegramSecurityRepository implements TelegramSecurityInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;
    private ExistProfileGroupInterface $existProfileGroup;
    private ProfileGroupByUserProfileInterface $profileGroupByUserProfile;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        ExistProfileGroupInterface $existProfileGroup,
        ProfileGroupByUserProfileInterface $profileGroupByUserProfile,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
        $this->existProfileGroup = $existProfileGroup;
        $this->profileGroupByUserProfile = $profileGroupByUserProfile;
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

        $dbal
            ->select('profile_group_users.prefix AS profile_group_users')
            ->from(ProfileGroupUsers::class, 'profile_group_users')
            ->where('profile_group_users.profile = :current')
            ->setParameter('current', $current, UserProfileUid::TYPE);

        if(false === $authority->equals($current))
        {
            $dbal->join(
                'profile_group_users',
                ProfileGroup::class,
                'profile_group',
                'profile_group.prefix = profile_group_users.prefix AND profile_group.profile = :authority'
            )
                ->setParameter('authority', $authority, UserProfileUid::TYPE);
        }
        else
        {
            $dbal->leftJoin(
                'profile_group_users',
                ProfileGroup::class,
                'profile_group',
                'profile_group.prefix = profile_group_users.prefix'
            );

            $dbal->andWhere('profile_group_users.authority IS NULL');
        }

        $dbal
            ->leftJoin(
                'profile_group',
                ProfileRole::class,
                'profile_group_role',
                'profile_group_role.event = profile_group.event'

            );

        $dbal
            ->addSelect('profile_group_voter.prefix AS profile_group_voter_prefix')
            ->join(
                'profile_group_role',
                ProfileVoter::class,
                'profile_group_voter',
                'profile_group_voter.role = profile_group_role.id AND profile_group_voter.prefix = :voter'
            )
            ->setParameter('voter', $voter, RoleVoterPrefix::TYPE);

        $dbal->setMaxResults(1);

        $roles = $dbal->enableCache('telegram-bot', 3600)->fetchAssociative();

        if(empty($roles))
        {
            return false;
        }

        if($roles['profile_group_users'] === 'ROLE_ADMIN')
        {
            return true;
        }

        return $voter->equals($roles['profile_group_voter_prefix']);

    }

    public function isGranted(UserProfileUid|string $profile, string $role, UserProfileUid|string|null $authority = null): bool
    {
        if(!class_exists(BaksDevUsersProfileGroupBundle::class))
        {
            return false;
        }

        $profile = is_string($profile) ? new UserProfileUid($profile) : $profile;
        $authority = is_string($authority) ? new UserProfileUid($authority) : $authority;

        /** Проверяем, имеется ли у пользователя группа либо доверенность */
        $existGroup = $this->existProfileGroup->isExistsProfileGroup($profile);

        if($existGroup)
        {
            /** Получаем префикс группы профиля
             * $authority = false - если администратор ресурса
             * */
            $group = $this->profileGroupByUserProfile
                ->findProfileGroupByUserProfile($profile, $authority);

            if($group)
            {
                if($group->equals('ROLE_ADMIN'))
                {
                    return true;
                }

                /** Получаем список ролей и правил группы */
                $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

                $qb->select("
                   ARRAY(SELECT DISTINCT UNNEST(
                        ARRAY_AGG(profile_role.prefix) || 
                        ARRAY_AGG(profile_voter.prefix)
                    )) AS roles
                ");

                $qb->from(ProfileGroup::TABLE, 'profile_group');

                $qb->leftJoin(
                    'profile_group',
                    ProfileRole::TABLE,
                    'profile_role',
                    'profile_role.event = profile_group.event'
                );

                $qb->leftJoin(
                    'profile_role',
                    ProfileVoter::TABLE,
                    'profile_voter',
                    'profile_voter.role = profile_role.id'
                );

                $qb->andWhere('profile_group.prefix = :prefix')
                    ->setParameter('prefix', $group, GroupPrefix::TYPE);

                $qb->andWhere('profile_role.prefix IS NOT NULL');
                $qb->andWhere('profile_voter.prefix IS NOT NULL');


                if($authority)
                {
                    $qb->andWhere('profile_group.profile = :authority')
                        ->setParameter('authority', $authority, UserProfileUid::TYPE);
                }

                $roles = $qb
                    ->enableCache('telegram-bot', 60)
                    ->fetchOne();

                if($roles)
                {
                    $roles = trim($roles, "{}");

                    if(empty($roles))
                    {
                        return false;
                    }

                    $roles = explode(",", $roles);

                    $roles[] = 'ROLE_USER';
                }

                $roles = array_filter($roles);

                return in_array($role, $roles);
            }
        }

        return false;
    }

    public function isExistGranted(UserProfileUid|string $profile, string $role) : bool
    {
        if(!class_exists(BaksDevUsersProfileGroupBundle::class))
        {
            return false;
        }

        $profile = is_string($profile) ? new UserProfileUid($profile) : $profile;

        /** Проверяем, имеется ли у пользователя группа либо доверенность */
        $existGroup = $this->existProfileGroup->isExistsProfileGroup($profile);

        if($existGroup)
        {
            /** Получаем префикс группы профиля
             * $authority = false - если администратор ресурса
             * */
            $group = $this->profileGroupByUserProfile
                ->findProfileGroupByUserProfile($profile, false);

            if($group)
            {
                /** Получаем список ролей и правил группы */
                $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

                $qb->select("
                   ARRAY(SELECT DISTINCT UNNEST(
                        ARRAY_AGG(profile_role.prefix) || 
                        ARRAY_AGG(profile_voter.prefix)
                    )) AS roles
                ");

                $qb->from(ProfileGroup::TABLE, 'profile_group');

                $qb->leftJoin(
                    'profile_group',
                    ProfileRole::TABLE,
                    'profile_role',
                    'profile_role.event = profile_group.event'
                );

                $qb->leftJoin(
                    'profile_role',
                    ProfileVoter::TABLE,
                    'profile_voter',
                    'profile_voter.role = profile_role.id'
                );

                $qb->andWhere('profile_group.prefix = :prefix')
                    ->setParameter('prefix', $group, GroupPrefix::TYPE);

                $qb->andWhere('profile_role.prefix IS NOT NULL');
                $qb->andWhere('profile_voter.prefix IS NOT NULL');

                $roles = $qb
                    ->enableCache('telegram-bot', 60)
                    ->fetchOne();

                if($roles)
                {
                    $roles = trim($roles, "{}");

                    if(empty($roles))
                    {
                        return false;
                    }

                    $roles = explode(",", $roles);

                    $roles[] = 'ROLE_USER';
                }

                $roles = array_filter($roles);

                return in_array($role, $roles);
            }
        }

        return false;
    }

}