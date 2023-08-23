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

use dcCore;
use Dotclear\Module\MyPlugin;
use initPages;

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
            self::BACKEND => defined('DC_CONTEXT_ADMIN')
                    // Check specific permission
                    && dcCore::app()->blog && dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                        dcCore::app()->auth::PERMISSION_USAGE,
                        dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
                        initPages::PERMISSION_PAGES,
                    ]), dcCore::app()->blog->id),

            default => null
        };
    }
}
