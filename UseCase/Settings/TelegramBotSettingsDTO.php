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

namespace BaksDev\Telegram\Bot\UseCase\Settings;

use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEventInterface;
use BaksDev\Telegram\Bot\Type\Settings\Event\TelegramBotSettingsEventUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see TelegramBotSettingsEvent */
final class TelegramBotSettingsDTO implements TelegramBotSettingsEventInterface
{
    /** Идентификатор события */
    #[Assert\Uuid]
    private ?TelegramBotSettingsEventUid $id = null;

    /**
     * Ссылка Telegram-бота
     */
    #[Assert\NotBlank]
    #[Assert\Url]
    private string $url;

    /**
     * Токен авторизации Telegram-бота.
     */
    #[Assert\NotBlank]
    private string $token;

    /**
     * Системный токен, для проверки подлинности запроса от бота.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 256)]
    #[Assert\Regex(pattern: '/^[\w\-]+$/iu')]
    private string $secret;

    /**
     * Максимально допустимое количество одновременных подключений для доставки обновлений.
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 100)]
    private int $connect = 50;

    public function setId(?TelegramBotSettingsEventUid $id): void
    {
        $this->id = $id;
    }

    public function getEvent(): ?TelegramBotSettingsEventUid
    {
        return $this->id;
    }

    /**
     * Токен авторизации Telegram-бота.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Системный токен, для проверки подлинности запроса от бота.
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    /**
     * Максимально допустимое количество одновременных подключений для доставки обновлений.
     */
    public function getConnect(): int
    {
        return $this->connect;
    }

    public function setConnect(int $connect): void
    {
        $this->connect = $connect;
    }

    /**
     * Url
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }


}
