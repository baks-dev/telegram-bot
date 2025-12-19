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

namespace BaksDev\Telegram\Bot\UseCase\TelegramProductInfo;


use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\TelegramBotSettingsInterface;
use BaksDev\Telegram\Bot\UseCase\TelegramProductInfo\TelegramProduct\TelegramProductForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TelegramProductsInfoForm extends AbstractType
{

    public function __construct(
        private readonly TelegramBotSettingsInterface $UsersTableTelegramSettingsRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        /* Сообщения */
        $settings = $this->UsersTableTelegramSettingsRepository->settings();
        $messages = $settings->getMessages();

        if(false !== $messages)
        {

            $builder->add('messages', ChoiceType::class, [
                'choices' => $messages,
                'choice_value' => function(string $message) {
                    return $message;
                },
                'choice_label' => function(string $message) {
                    return $message;
                },
                'multiple' => true, /* Разрешить множественный выбор */
                'expanded' => true, /* Выводить как чекбоксы */
                'required' => true,
            ]);
        }

        $builder->add('collection', CollectionType::class, [
            /** Указать вложенные формы */
            'entry_type' => TelegramProductForm::class,
            'entry_options' => [
                'attr' => ['class' => 'products-data-box'],
            ],
            'allow_add' => true,
        ]);

        /* Сохранить ******************************************************/
        $builder->add(
            'telegram_product_info',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TelegramProductsInfoDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}