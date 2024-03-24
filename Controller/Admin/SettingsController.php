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

namespace BaksDev\Telegram\Bot\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Telegram\Api\Webhook\TelegramSetWebhook;
use BaksDev\Telegram\Bot\Entity\TelegramBotSettings;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\TelegramBotSettingsInterface;
use BaksDev\Telegram\Bot\UseCase\Settings\TelegramBotSettingsDTO;
use BaksDev\Telegram\Bot\UseCase\Settings\UsersTableTelegramSettingsForm;
use BaksDev\Telegram\Bot\UseCase\Settings\UsersTableTelegramSettingsHandler;
use BaksDev\Telegram\Exception\TelegramRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[RoleSecurity('ROLE_TELEGRAM_SETTINGS')]
final class SettingsController extends AbstractController
{
    #[Route('/admin/telegram/settings', name: 'admin.settings', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TelegramSetWebhook $telegramSetWebhook,
        TelegramBotSettingsInterface $UsersTableTelegramSettingsRepository,
        UsersTableTelegramSettingsHandler $UsersTableTelegramSettingsHandler,
    ): Response
    {
        //#[MapEntity] UsersTableTelegramSettingsEvent $UsersTableTelegramSettingsEvent,

        $Settings = $UsersTableTelegramSettingsRepository->getCurrentTelegramSettingsEvent();
        $UsersTableTelegramSettingsDTO = new TelegramBotSettingsDTO();
        $Settings?->getDto($UsersTableTelegramSettingsDTO);

        // Форма
        $form = $this->createForm(UsersTableTelegramSettingsForm::class, $UsersTableTelegramSettingsDTO, [
            'action' => $this->generateUrl('telegram-bot:admin.settings'),
        ]);


        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('telegram_bot_settings'))
        {
            try
            {
                /**
                 * Сохраняем настройки боту
                 */

                $telegramSetWebhook
                    ->token($UsersTableTelegramSettingsDTO->getToken())
                    ->secret($UsersTableTelegramSettingsDTO->getSecret())
                    ->connections($UsersTableTelegramSettingsDTO->getConnect())
                    ->url($this->generateUrl('telegram-bot:telegram.endpoint', [], UrlGeneratorInterface::ABSOLUTE_URL));

                $telegramSetWebhook->send(false);

                /**
                 * Сохраняем настройки
                 */

                $UsersTableTelegramSettings = $UsersTableTelegramSettingsHandler->handle($UsersTableTelegramSettingsDTO);

                if($UsersTableTelegramSettings instanceof TelegramBotSettings)
                {
                    $this->addFlash('success', 'admin.success.settings', 'telegram.bot');

                    return $this->redirectToReferer();
                }

                $this->addFlash('admin.page.settings', 'admin.danger.settings', 'telegram.bot', $UsersTableTelegramSettings);


            } catch(TelegramRequestException $exception)
            {
                $this->addFlash('admin.page.settings', 'admin.danger.settings', 'telegram.bot', $exception->getMessage());

            }


            return $this->redirectToReferer();
        }

        return $this->render(['form' => $form->createView()]);
    }
}
