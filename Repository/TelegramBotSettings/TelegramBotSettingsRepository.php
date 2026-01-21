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

namespace BaksDev\Telegram\Bot\Repository\TelegramBotSettings;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Entity\Message\TelegramBotSettingsMessage;
use BaksDev\Telegram\Bot\Entity\Profile\TelegramBotSettingsProfile;
use BaksDev\Telegram\Bot\Entity\TelegramBotSettings;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TelegramBotSettingsRepository implements TelegramBotSettingsInterface
{

    private ?string $url = null;

    /**
     * Токен авторизации Telegram-бота.
     */
    private string $token;

    /**
     * Системный токен, для проверки подлинности запроса от бота.
     */
    private bool|string $secret = false;

    /**
     * Сообщения в настройках.
     */
    private array|false $messages = [];

    private UserProfileUid|false $profile = false;


    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        #[Autowire(env: 'PROJECT_PROFILE')] private readonly ?string $projectProfile = null,
    ) {}


    public function settings(): self|bool
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('event.token')
            ->addSelect('event.secret')
            ->addSelect('event.url')
            ->from(TelegramBotSettings::class, 'settings');

        $dbal->leftJoin(
            'settings',
            TelegramBotSettingsEvent::class,
            'event',
            'event.id = settings.event'
        );

        $dbal->leftJoin(
            'settings',
            TelegramBotSettingsMessage::class,
            'message',
            'message.event = settings.event'
        );


        /* Если профиль не задан и не указан PROJECT_PROFILE в .env */
        if($this->profile === false && $this->projectProfile === null)
        {
            return false;
        }

        /* Profile */
        $dbal
            ->addSelect('profile.value as profile')
            ->join(
                'settings',
                TelegramBotSettingsProfile::class,
                'profile',
                'profile.event = settings.event AND profile.value = :project_profile'
            );

        $dbal->setParameter(
            key: 'project_profile',
            value: $this->profile instanceof UserProfileUid ? $this->profile : new UserProfileUid($this->projectProfile),
            type: UserProfileUid::TYPE,
        );


        $dbal->addSelect("JSON_AGG
			( DISTINCT
					message.message
			) FILTER (WHERE message.message IS NOT NULL) AS messages");


        $dbal->allGroupByExclude();

        /* Кешируем результат DBAL */
        $settings = $dbal
            ->enableCache('telegram', '5 minutes')
            ->fetchAssociative();

        if(isset($settings['token']))
        {
            $this->token = $settings['token'];
            $this->secret = $settings['secret'];
            $this->url = $settings['url'];

            $this->messages = $settings['messages'] ?
                json_decode($settings['messages'], false, 512, JSON_THROW_ON_ERROR) : false;

            $this->profile = new UserProfileUid($settings['profile']);

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
    public function equalsSecret(?string $secret): bool
    {
        if(!$secret || !$this->secret)
        {
            return false;
        }

        return $this->secret === $secret;
    }

    /**
     * Url
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Messages
     */
    public function getMessages(): array|false
    {
        return $this->messages;
    }

    /**
     * Profile
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

}
