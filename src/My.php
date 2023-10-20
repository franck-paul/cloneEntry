<?php
/**
 * @brief cloneEntry, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Jean-Christian Denis, Franck Paul and contributors
 *
 * @copyright Jean-Christian Denis, Franck Paul
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\cloneEntry;

use Dotclear\App;
use Dotclear\Module\MyPlugin;
use Dotclear\Plugin\pages\Pages;

/**
 * Plugin definitions
 */
class My extends MyPlugin
{
    /**
     * Check permission depending on given context
     *
     * @param      int   $context  The context
     *
     * @return     bool  true if allowed, else false
     */
    public static function checkCustomContext(int $context): ?bool
    {
        return match ($context) {
            self::BACKEND => App::task()->checkContext('BACKEND')
                    // Check specific permission
                    && App::blog()->isDefined() && App::auth()->check(App::auth()->makePermissions([
                        App::auth()::PERMISSION_USAGE,
                        App::auth()::PERMISSION_CONTENT_ADMIN,
                        Pages::PERMISSION_PAGES,
                    ]), App::blog()->id()),

            default => null
        };
    }
}
