<?php
/**
 * @brief cloneEntry, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\cloneEntry;

use dcAdmin;
use dcCore;
use dcNsProcess;

class Backend extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init = My::checkContext(My::BACKEND);

        // dead but useful code, in order to have translations
        __('Clone Entry') . __('Make a clone of entry');

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        // Add menu item in blog menu
        dcCore::app()->menu[dcAdmin::MENU_BLOG]->addItem(
            __('Clone Entry'),
            My::makeUrl(),
            My::icons(),
            preg_match(My::urlScheme(), $_SERVER['REQUEST_URI']),
            My::checkContext(My::MENU)
        );

        dcCore::app()->addBehaviors([
            // Add behaviour callback for post
            'adminPostAfterForm' => [BackendBehaviors::class, 'clonePost'],
            // Add behaviour callback for page
            'adminPageAfterForm' => [BackendBehaviors::class, 'clonePage'],

            /* Add behavior callbacks for posts actions */
            'adminPostsActions' => [BackendBehaviors::class, 'clonePosts'],
            'adminPagesActions' => [BackendBehaviors::class, 'clonePages'],
        ]);

        return true;
    }
}
