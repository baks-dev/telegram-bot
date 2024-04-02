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

namespace BaksDev\Telegram\Bot\Entity\Event;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Telegram\Bot\Entity\Modify\TelegramBotSettingsModify;
use BaksDev\Telegram\Bot\Entity\TelegramBotSettings;
use BaksDev\Telegram\Bot\Type\Settings\Event\TelegramBotSettingsEventUid;
use BaksDev\Telegram\Bot\Type\Settings\Id\UsersTableTelegramSettingsIdentificator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;


/* UsersTableSettingsEvent */

#[ORM\Entity]
#[ORM\Table(name: 'telegram_bot_settings_event')]
class TelegramBotSettingsEvent extends EntityEvent
{
    public const TABLE = 'telegram_bot_settings_event';

    /**
     * Идентификатор события
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: TelegramBotSettingsEventUid::TYPE)]
    private TelegramBotSettingsEventUid $id;

    /**
     * Идентификатор настройки
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: UsersTableTelegramSettingsIdentificator::TYPE)]
    private ?UsersTableTelegramSettingsIdentificator $main = null;


    /**
     * Модификатор
     */
    #[ORM\OneToOne(targetEntity: TelegramBotSettingsModify::class, mappedBy: 'event', cascade: ['all'])]
    private TelegramBotSettingsModify $modify;


    /**
     * Ссылка Telegram-бота
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string $url;

    /**
     * Токен авторизации Telegram-бота
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING)]
    private string $token;

    /**
     * Системный токен, для проверки подлинности запроса от бота
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING)]
    private string $secret;

    /**
     * Максимально допустимое количество одновременных подключений для доставки обновлений
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 100)]
    #[ORM\Column(type: Types::SMALLINT)]
    private int $connect = 50;

    public function __construct()
    {
        $this->id = new TelegramBotSettingsEventUid();
        $this->modify = new TelegramBotSettingsModify($this);

    }

    public function __clone()
    {
        $this->id = clone $this->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): TelegramBotSettingsEventUid
    {
        return $this->id;
    }

    public function setMain(UsersTableTelegramSettingsIdentificator|TelegramBotSettings $main): void
    {
        $this->main = $main instanceof TelegramBotSettings ? $main->getId() : $main;
    }


    public function getMain(): ?UsersTableTelegramSettingsIdentificator
    {
        return $this->main;
    }

    public function getDto($dto): mixed
    {
        $dto = is_string($dto) && class_exists($dto) ? new $dto() : $dto;

        if($dto instanceof TelegramBotSettingsEventInterface || $dto instanceof self)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof TelegramBotSettingsEventInterface || $dto instanceof self)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }
}