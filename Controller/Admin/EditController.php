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

namespace BaksDev\Telegram\Bot\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Telegram\Api\Webhook\TelegramSetWebhook;
use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Entity\TelegramBotSettings;
use BaksDev\Telegram\Bot\UseCase\Settings\TelegramBotSettingsDTO;
use BaksDev\Telegram\Bot\UseCase\Settings\TelegramBotSettingsForm;
use BaksDev\Telegram\Bot\UseCase\Settings\TelegramBotSettingsHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[RoleSecurity('ROLE_TELEGRAM_SETTINGS_EDIT')]
final class EditController extends AbstractController
{

    #[Route('/admin/telegram/settings/edit/{event}', name: 'admin.newedit.edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TelegramSetWebhook $telegramSetWebhook,
        TelegramBotSettingsHandler $TelegramSettingsHandler,
        #[MapEntity] TelegramBotSettingsEvent $event,
    ): Response
    {
        $TelegramBotSettingsDTO = new TelegramBotSettingsDTO();
        $event->getDto($TelegramBotSettingsDTO);


        /* Форма */
        $form = $this->createForm(TelegramBotSettingsForm::class, $TelegramBotSettingsDTO, [
            'action' => $this->generateUrl('telegram-bot:admin.newedit.edit', ['event' => $event->getId()]),
        ]);

        $form->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('telegram_bot_settings'))
        {

            $this->refreshTokenForm($form);

            /* Сохранить настройки */
            $TelegramSettings = $TelegramSettingsHandler->handle($TelegramBotSettingsDTO);

            if($TelegramSettings instanceof TelegramBotSettings)
            {

                /* Сохранить настройки Telegram боту */
                $telegramSetWebhook
                    ->token($TelegramBotSettingsDTO->getToken())
                    ->secret($TelegramBotSettingsDTO->getSecret())
                    ->connections($TelegramBotSettingsDTO->getConnect())
                    ->url($this->generateUrl('telegram-bot:telegram.endpoint', ['profile' => $TelegramSettings->getEvent()], UrlGeneratorInterface::ABSOLUTE_URL));

                $telegramSetWebhook->send();


                $this->addFlash('success', 'success.edit', 'telegram.bot');

                return $this->redirectToReferer();
            }

            $this->addFlash('admin.page.settings', 'admin.danger.settings', 'telegram.bot', $TelegramSettings);

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $form->createView()]);
    }
}