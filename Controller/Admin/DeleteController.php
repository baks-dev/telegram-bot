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
use BaksDev\Telegram\Bot\Entity\Event\TelegramBotSettingsEvent;
use BaksDev\Telegram\Bot\Entity\TelegramBotSettings;
use BaksDev\Telegram\Bot\UseCase\Settings\Delete\TelegramBotSettingsDeleteDTO;
use BaksDev\Telegram\Bot\UseCase\Settings\Delete\TelegramBotSettingsDeleteForm;
use BaksDev\Telegram\Bot\UseCase\Settings\Delete\TelegramBotSettingsDeleteHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_TELEGRAM_SETTINGS_DELETE')]
class DeleteController extends AbstractController
{
    #[Route('/admin/telegram/settings/delete/{id}', name: 'admin.delete', methods: ['GET', 'POST'])]
    public function delete(
        Request $request,
        #[MapEntity] TelegramBotSettingsEvent $Profile,
        TelegramBotSettingsDeleteHandler $TelegramBotSettingsDeleteHandler,
    ): Response
    {
        $TelegramBotSettingsDeleteDTO = new TelegramBotSettingsDeleteDTO();
        $Profile->getDto($TelegramBotSettingsDeleteDTO);

        $form = $this->createForm(TelegramBotSettingsDeleteForm::class, $TelegramBotSettingsDeleteDTO, [
            'action' => $this->generateUrl('telegram-bot:admin.delete', ['id' => $TelegramBotSettingsDeleteDTO->getEvent()]),
        ]);

        $form->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('telegram_bot_settings_delete'))
        {
            $this->refreshTokenForm($form);

            $handle = $TelegramBotSettingsDeleteHandler->handle($TelegramBotSettingsDeleteDTO);

            $this->addFlash(
                'page.delete',
                $handle instanceof TelegramBotSettings ? 'success.delete' : 'danger.delete',
                'telegram.bot',
                $handle
            );

            return $this->redirectToRoute('telegram-bot:admin.index');
        }

        return $this->render(
            [
                'form' => $form->createView(),
                'name' => "Настройка Telegram",
            ]
        );
    }
}