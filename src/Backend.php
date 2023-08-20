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

use dcCore;
use Dotclear\Core\Backend\Menus;
use Dotclear\Core\Process;

class Backend extends Process
{
    public static function init(): bool
    {
        // dead but useful code, in order to have translations
        __('Clone Entry') . __('Make a clone of entry');

        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Add menu item in blog menu
        dcCore::app()->admin->menus[Menus::MENU_BLOG]->addItem(
            __('Clone Entry'),
            My::manageUrl(),
            My::icons(),
            preg_match(My::urlScheme(), $_SERVER['REQUEST_URI']),
            My::checkContext(My::MENU)
        );

        dcCore::app()->addBehaviors([
            // Add behaviour callback for post
            'adminPostAfterForm' => BackendBehaviors::clonePost(...),
            // Add behaviour callback for page
            'adminPageAfterForm' => BackendBehaviors::clonePage(...),

            /* Add behavior callbacks for posts actions */
            'adminPostsActions' => BackendBehaviors::clonePosts(...),
            'adminPagesActions' => BackendBehaviors::clonePages(...),
        ]);

        return true;
    }
}
