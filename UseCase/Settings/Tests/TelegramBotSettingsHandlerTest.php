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

namespace BaksDev\Telegram\Bot\UseCase\Settings\Tests;

use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Entity\TelegramBotSettings;
use BaksDev\Telegram\Bot\Type\Settings\Id\TelegramBotSettingsUid;
use BaksDev\Telegram\Bot\UseCase\Settings\Active\TelegramBotSettingsActiveDTO;
use BaksDev\Telegram\Bot\UseCase\Settings\Message\TelegramBotSettingsMessageDTO;
use BaksDev\Telegram\Bot\UseCase\Settings\Profile\TelegramBotSettingsProfileDTO;
use BaksDev\Telegram\Bot\UseCase\Settings\TelegramBotSettingsDTO;
use BaksDev\Telegram\Bot\UseCase\Settings\TelegramBotSettingsHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('telegram-bot')]
#[When(env: 'test')]
class TelegramBotSettingsHandlerTest extends KernelTestCase
{

    public static function setUpBeforeClass(): void
    {

        $container = self::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $TelegramBotSetting = $em->getRepository(TelegramBotSettings::class)
            ->find(TelegramBotSettingsUid::TEST);

        if($TelegramBotSetting)
        {
            $em->remove($TelegramBotSetting);
        }

        $TelegramBotSettingsEvent = $em->getRepository(TelegramBotSettingsEvent::class)
            ->findBy(['main' => TelegramBotSettingsUid::TEST]);

        foreach($TelegramBotSettingsEvent as $remove)
        {
            $em->remove($remove);
        }

        $em->flush();
    }

    public function testUseCase(): void
    {

        $profileUid = $_SERVER['TEST_PROJECT_PROFILE'] ?? UserProfileUid::TEST;

        $TelegramBotSettingsDTO = new TelegramBotSettingsDTO();

        $TelegramBotSettingsDTO->setToken('test_token');
        $TelegramBotSettingsDTO->setConnect(50);
        $TelegramBotSettingsDTO->setUrl('https://t.me/baks_dev_bot_test');
        $TelegramBotSettingsDTO->setSecret('fdsafdsfsadfdsfsafsfasdf');

        /* Profile */
        $TelegramBotSettingsProfileDTO = new TelegramBotSettingsProfileDTO();
        $TelegramBotSettingsProfileDTO->setValue(new UserProfileUid($profileUid));
        $TelegramBotSettingsDTO->setProfile($TelegramBotSettingsProfileDTO);

        /* Message */
        $TelegramBotSettingsMessageDTO = new TelegramBotSettingsMessageDTO();
        $TelegramBotSettingsMessageDTO->setMessage("Test Message");

        $TelegramBotSettingsDTO->addMessage($TelegramBotSettingsMessageDTO);

        /* Active */
        $TelegramBotSettingsActiveDTO = new TelegramBotSettingsActiveDTO();
        $TelegramBotSettingsActiveDTO->setValue(true);

        $TelegramBotSettingsDTO->setActive($TelegramBotSettingsActiveDTO);

        self::bootKernel();

        $SettinsgHandler = self::getContainer()->get(TelegramBotSettingsHandler::class);

        $handle = $SettinsgHandler->handle($TelegramBotSettingsDTO);


        self::assertTrue(($handle instanceof TelegramBotSettings), $handle.': Ошибка TelegramBotSettings');

    }

}