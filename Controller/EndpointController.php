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
 *
 */

declare(strict_types=1);

namespace BaksDev\Telegram\Bot\Controller;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Telegram\Request\TelegramRequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class EndpointController extends AbstractController
{
    /**
     * Конечный адрес для обратных сообщений Telegram
     */
    #[Route('/telegram/endpoint', name: 'telegram.endpoint', methods: ['GET', 'POST'])]
    public function index(
        MessageDispatchInterface $messageDispatch,
        TelegramRequest $telegramRequest,
        DeduplicatorInterface $deduplicator,
        Request $request,
    ): Response
    {
        if($telegramRequest->request() instanceof TelegramRequestInterface)
        {
            $Deduplicator = $deduplicator
                ->namespace('telegram')
                ->deduplication([
                    var_export($telegramRequest->request()->getChat(), true),
                    $telegramRequest->request()->getId(),
                ]);

            if($Deduplicator->isExecuted())
            {
                return new JsonResponse(['success']);
            }

            $Deduplicator->save();

            $messageDispatch->dispatch(new TelegramEndpointMessage($telegramRequest->request()));
        }

        return new JsonResponse(['success']);
    }
}


