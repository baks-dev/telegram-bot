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

use BaksDev\Telegram\Bot\Type\Settings\Event\TelegramBotSettingsEventUid;
use BaksDev\Telegram\Bot\Type\Settings\Id\TelegramBotSettingsUid;
use BaksDev\Users\Profile\UserProfile\Type\Event\UserProfileEventUid;


final readonly class AllTelegramBotSettingsPaginatorResult
{
    public function __construct(
        private string $id,
        private string $event,
        private string $url,
        private int $connect,

        private ?bool $active,
        private ?string $users_profile_event,
        private ?string $users_profile_username,
        private ?string $users_profile_avatar,
        private ?string $users_profile_avatar_ext,
        private ?bool $users_profile_avatar_cdn,
    ) {}

    public function getConnect(): int
    {
        return $this->connect;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getId(): TelegramBotSettingsUid
    {
        return new TelegramBotSettingsUid($this->id);
    }

    public function getEvent(): TelegramBotSettingsEventUid
    {
        return new TelegramBotSettingsEventUid($this->event);
    }

    public function isActive(): bool
    {
        return $this->active === true;
    }

    public function getUsersProfileEvent(): ?UserProfileEventUid
    {
        return new UserProfileEventUid($this->users_profile_event);
    }

    public function getUsersProfileUsername(): ?string
    {
        return $this->users_profile_username;
    }

    public function getUsersProfileAvatar(): ?string
    {
        return $this->users_profile_avatar;
    }

    public function getUsersProfileAvatarExt(): ?string
    {
        return $this->users_profile_avatar_ext;
    }

    public function getUsersProfileAvatarCdn(): ?bool
    {
        return $this->users_profile_avatar_cdn === true;
    }

}