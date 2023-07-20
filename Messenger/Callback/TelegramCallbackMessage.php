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

namespace BaksDev\Telegram\Bot\Messenger\Callback;


use Exception;

final class TelegramCallbackMessage
{
    /**
     * Callback класс
     */
    private ?object $class = null;

    /**
     * Идентификатор чата
     */
    private int $chat;


    /**
     * Текст пользовательского сообщение
     */
    private ?string $data;

    public function __construct(string $callback, int $chat, string $data = null)
    {
        $call = explode(':', $callback);
        $class = current($call);

        if(class_exists($class))
        {
            $parameter = $call[1] ?? null;

            if($parameter)
            {
                try
                {
                    $array = json_decode($parameter, false, 512, JSON_THROW_ON_ERROR);

                    if($array)
                    {
                        $this->class = new $class(...$array);
                    }

                } catch(Exception $e)
                {

                    $this->class = new $class($parameter);
                }
            }
            else
            {
                $this->class = new $class();
            }
        }

        $this->data = $data;
        $this->chat = $chat;
    }

    /**
     * Class
     */
    public function getClass(): ?object
    {
        return $this->class;
    }

    /**
     * Data
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    /**
     * Chat
     */
    public function getChat(): int
    {
        return $this->chat;
    }

}
