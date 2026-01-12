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

namespace BaksDev\Telegram\Bot\Controller;


use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Files\Resources\Twig\ImagePathExtension;
use BaksDev\Products\Product\Repository\ProductsDetailByUids\ProductsDetailByUidsInterface;
use BaksDev\Products\Product\Repository\ProductsDetailByUids\ProductsDetailByUidsResult;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Api\TelegramSendPhoto;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\TelegramBotSettingsInterface;
use BaksDev\Telegram\Bot\UseCase\TelegramProductInfo\TelegramProduct\TelegramProductDTO;
use BaksDev\Telegram\Bot\UseCase\TelegramProductInfo\TelegramProductsInfoDTO;
use BaksDev\Telegram\Bot\UseCase\TelegramProductInfo\TelegramProductsInfoForm;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART')]
final class SendMessageController extends AbstractController
{

    #[Route('/admin/send/message/telegram', name: 'admin.message.telegram', methods: ['GET', 'POST'])]
    public function index(
        #[Autowire(env: 'HOST')] string $host,
        #[Autowire(env: 'TELEGRAM_CHANNEL')] string $channel,
        Request $request,
        TelegramSendMessages $telegramSendMessages,
        TelegramSendPhoto $telegramSendPhoto,
        TelegramBotSettingsInterface $UsersTableTelegramSettingsRepository,
        ProductsDetailByUidsInterface $productsDetailByUids,
        ImagePathExtension $ImagePathExtension,
    ): Response
    {

        /** Из настроек telegram-bot получить Сообщения */
        $settings = $UsersTableTelegramSettingsRepository->settings();
        $messages = $settings->getMessages();


        $TelegramProductsInfoDTO = new TelegramProductsInfoDTO();

        $form = $this
            ->createForm(
                type: TelegramProductsInfoForm::class,
                data: $TelegramProductsInfoDTO,
                options: ['action' => $this->generateUrl('telegram-bot:admin.message.telegram')],
            )
            ->handleRequest($request);


        /** Получить массивы uuids по выбранным продуктам */
        $events = [];
        $offers = [];
        $variations = [];
        $modifications = [];

        /** @var TelegramProductsInfoDTO $TelegramProductInfoDTO */
        foreach($TelegramProductsInfoDTO->getCollection() as $key => $TelegramProductDTO)
        {

            /** @var TelegramProductDTO $TelegramProductDTO */
            $events[$key] = $TelegramProductDTO->getProduct();
            $offers[$key] = $TelegramProductDTO->getOffer();
            $variations[$key] = $TelegramProductDTO->getVariation();
            $modifications[$key] = $TelegramProductDTO->getModification();

        }

        /** Получаем информацию о добавленных продуктах */
        /* Пока будет передаваться массив с данными об одном продукте */
        $telegramDetails = $productsDetailByUids
            ->events($events)
            ->offers($offers)
            ->variations($variations)
            ->modifications($modifications)
            ->toArray();

        $messageTelegram = '';

        /** Вывести ошибку если в настройках не заданы сообщения */
        if(false === $messages)
        {
            $this->addFlash
            (
                'danger',
                'message.empty.danger',
                'telegram.bot',
            );

            return $this->redirectToRoute('manufacture-part-application:admin.index');
        }

        /** Если в настройках задано одно сообщение */
        if(count($messages) === 1)
        {
            $messageTelegram = reset($messages);
        }

        /* Когда пользователь отправляет форму формируем сообщение из выбранных */
        if($form->isSubmitted() && $form->isValid() && $form->has('telegram_product_info'))
        {
            /* Получить выбранные значения */
            $messages = $TelegramProductsInfoDTO->getMessages();
            $messageTelegram = trim(implode(PHP_EOL, $messages));
        }


        /** Если сообщение заполнено */
        if(false === empty($messageTelegram))
        {

            /** Получить и отправить изображение товара */

            /** @var ProductsDetailByUidsResult $telegramDetail */
            foreach($telegramDetails as $telegramDetail)
            {
                $productImage = $telegramDetail->getProductImage();
                $productImageExt = $telegramDetail->getProductImageExt();
                $productImageCdn = $telegramDetail->isProductImageCdn();

                $imagePath = $ImagePathExtension->imagePath($productImage, $productImageExt, $productImageCdn);

                if(false === $productImageCdn)
                {
                    $imagePath = $host.$imagePath;
                }


                $caption = sprintf("Товар с артикулом %s", $telegramDetail->getProductArticle());

                $resultPhoto = $telegramSendPhoto
                    ->chanel($channel)
                    ->photo($imagePath)
                    ->caption($caption)
                    ->send();

                /* Отобразить Toast если отправка изображения не удалась */
                if(false === $resultPhoto)
                {
                    /* Отправить Toast */
                    $this->addFlash
                    (
                        'danger',
                        'message.photo.danger',
                        'telegram.bot',
                    );

                }

            }

            /** Получить и отправить сообщение */
            $result = $telegramSendMessages
                ->chanel($channel)
                ->message($messageTelegram)
                ->send();

            /* Отправить Toast */
            $this->addFlash
            (
                'message.new',
                $result !== false ? 'message.success' : 'message.danger',
                'telegram.bot',
            );

            return $this->redirectToRoute('manufacture-part-application:admin.index');

        }


        return $this->render([
            'form' => $form->createView(),
            'cards' => $telegramDetails,
        ]);

    }
}
