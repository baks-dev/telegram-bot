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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Telegram\Bot\Type\Settings\Event\TelegramBotSettingsEventType;
use BaksDev\Telegram\Bot\Type\Settings\Event\TelegramBotSettingsEventUid;
use BaksDev\Telegram\Bot\Type\Settings\Id\UsersTableTelegramSettingsIdentificator;
use BaksDev\Telegram\Bot\Type\Settings\Id\UsersTableTelegramSettingsIdentificatorType;
use BaksDev\Users\UsersTable\Type\Table\Event\UsersTableEventUid;
use BaksDev\Users\UsersTable\Type\Table\Id\UsersTableType;
use Symfony\Config\DoctrineConfig;

return static function(ContainerConfigurator $container, DoctrineConfig $doctrine)
{
    $doctrine->dbal()->type(UsersTableTelegramSettingsIdentificator::TYPE)->class(UsersTableTelegramSettingsIdentificatorType::class);
    $doctrine->dbal()->type(TelegramBotSettingsEventUid::TYPE)->class(TelegramBotSettingsEventType::class);


    $doctrine->dbal()->type(UsersTableEventUid::TYPE)->class(UsersTableType::class);

	$emDefault = $doctrine->orm()->entityManager('default');
	
	$emDefault->autoMapping(true);
    
	$emDefault->mapping('TelegramBot')
		->type('attribute')
		->dir(__DIR__.'/../../Entity')
		->isBundle(false)
		->prefix('BaksDev\Telegram\Bot')
		->alias('TelegramBot')
	;
};