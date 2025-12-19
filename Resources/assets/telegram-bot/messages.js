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

/** Добавить сообщение */
$addButtonMessage = document.getElementById("messageAddCollection");

if($addButtonMessage)
{
    /* Блок для новой коллекции */
    let $blockCollectionCall = document.getElementById("collection-message");

    if($blockCollectionCall)
    {
        $addButtonMessage.addEventListener("click", function()
        {

            let $addButtonMessage = this;
            /* получаем прототип коллекции  */
            let newForm = $addButtonMessage.dataset.prototype;
            let index = $addButtonMessage.dataset.index * 1;

            /* Замена '__messages__' в HTML-коде прототипа
             вместо этого будет число, основанное на том, сколько коллекций */
            newForm = newForm.replace(/__messages__/g, index);

            /* Вставляем новую коллекцию */
            let div = document.createElement("div");
            div.id = "users_table_telegram_settings_form_message_" + index;
            div.classList.add("mb-3");
            div.classList.add("item-message");

            div.innerHTML = newForm;
            $blockCollectionCall.append(div);


            /* Удалить */
            (div.querySelector(".del-item-message"))?.addEventListener("click", deleteMessage);

            const delButton = div.querySelector(".del-item-message");
            delButton.dataset.delete = "users_table_telegram_settings_form_message_" + (index).toString();

            /* Увеличиваем data-index на 1 после вставки новой коллекции */
            $addButtonMessage.dataset.index = (index + 1).toString();

            /* Плавная прокрутка к элементу */
            div.scrollIntoView({block : "center", inline : "center", behavior : "smooth"});

        });
    }
}

/** Удаление сообщения */
document.querySelectorAll(".del-item-message").forEach(function(item)
{
    item.addEventListener("click", deleteMessage);
});

function deleteMessage()
{
    let messages = document.querySelectorAll(".item-message").length;

    if(messages > 1)
    {
        document.getElementById(this.dataset.delete).remove();
    }
}

