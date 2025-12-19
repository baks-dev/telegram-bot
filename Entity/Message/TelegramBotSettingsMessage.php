<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Telegram\Bot\Entity\Message;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Type\Settings\Id\TelegramBotSettingsMessage\TelegramBotSettingsMessageUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;


/* TelegramBotSettingsMessage */

#[ORM\Entity]
#[ORM\Table(name: 'telegram_bot_settings_message')]
class TelegramBotSettingsMessage extends EntityEvent
{

    /** ID  */
    #[ORM\Id]
    #[ORM\Column(type: TelegramBotSettingsMessageUid::TYPE)]
    private TelegramBotSettingsMessageUid $id;


    /**
     * Идентификатор События
     */
    #[ORM\ManyToOne(targetEntity: TelegramBotSettingsEvent::class, inversedBy: 'message')]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id')]
    private TelegramBotSettingsEvent $event;

    /*
     * Текст сообщения
     */
    #[Assert\NotBlank(message: 'Сообщение обязательно для заполнения')]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;


    public function __construct(TelegramBotSettingsEvent $event)
    {
        $this->id = new TelegramBotSettingsMessageUid();
        $this->event = $event;

    }


    public function __toString(): string
    {
        return (string) $this->event;
    }

    public function getId(): TelegramBotSettingsMessageUid
    {
        return $this->id;
    }


    public function getDto($dto): mixed
    {
        if($dto instanceof TelegramBotSettingsMessageInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof TelegramBotSettingsMessageInterface)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

}