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
use dcNamespace;
use dcNsProcess;
use Exception;

class Install extends dcNsProcess
{
    protected static $init = false; /** @deprecated since 2.27 */
    public static function init(): bool
    {
        static::$init = My::checkContext(My::INSTALL);

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        try {
            // Update
            $old_version = dcCore::app()->getVersion(My::id());
            if (version_compare((string) $old_version, '3.0', '<')) {
                // Rename settings namespace
                if (dcCore::app()->blog->settings->exists('cloneentry')) {
                    dcCore::app()->blog->settings->delNamespace(My::id());
                    dcCore::app()->blog->settings->renNamespace('cloneentry', My::id());
                }

                // Change settings names (remove ce_ prefix in them)
                $rename = function (string $name, dcNamespace $settings): void {
                    if ($settings->settingExists('ce_' . $name, true)) {
                        $settings->rename('ce_' . $name, $name);
                    }
                };

                $settings = dcCore::app()->blog->settings->get(My::id());

                foreach ([
                    'active_post',
                    'active_page',
                ] as $value) {
                    $rename($value, $settings);
                }
            }

            // Init
            $settings = dcCore::app()->blog->settings->get(My::id());

            // Default state is active
            $settings->put('active_post', true, dcNamespace::NS_BOOL, 'Active for posts', false, true);
            $settings->put('active_page', true, dcNamespace::NS_BOOL, 'Active for pages', false, true);
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return true;
    }
}
