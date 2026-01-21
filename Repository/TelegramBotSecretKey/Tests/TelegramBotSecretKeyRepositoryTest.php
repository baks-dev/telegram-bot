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

namespace BaksDev\Telegram\Bot\Repository\TelegramBotSecretKey\Tests;

use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Repository\TelegramBotSecretKey\TelegramBotSecretKeyInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('telegram-bot')]
#[When(env: 'test')]
class TelegramBotSecretKeyRepositoryTest extends KernelTestCase
{

    public function testRepository(): void
    {

        $entityManager = self::getContainer()->get(entityManagerInterface::class);

        $profileUid = $_SERVER['TEST_PROJECT_PROFILE'] ?? UserProfileUid::TEST;

        $events = $entityManager->getRepository(TelegramBotSettingsEvent::class)
            ->createQueryBuilder('e')
            ->innerJoin('e.profile', 'p')
            ->where('p.value = :profile')
            ->setParameter('profile', $profileUid)
            ->getQuery()
            ->getResult();

        /** @var TelegramBotSettingsEvent $Event */

        if(false === empty($events))
        {
            $Event = reset($events);

            /** @var TelegramBotSecretKeyInterface $TelegramBotSecretKeyRepository */
            $TelegramBotSecretKeyRepository = self::getContainer()->get(TelegramBotSecretKeyInterface::class);

            $secret_key = $TelegramBotSecretKeyRepository->findKey($Event->getId());

//            dd($secret_key);

            self::assertEquals("fdsafdsfsadfdsfsafsfasdf", $secret_key);
        }

        self::assertTrue(true);

    }

}

