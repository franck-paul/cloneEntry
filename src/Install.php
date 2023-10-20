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

use Dotclear\App;
use Dotclear\Core\Process;
use Dotclear\Interface\Core\BlogWorkspaceInterface;
use Exception;

class Install extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            // Update
            $old_version = App::version()->getVersion(My::id());
            if (version_compare((string) $old_version, '3.0', '<')) {
                // Rename settings namespace
                if (App::blog()->settings()->exists('cloneentry')) {
                    App::blog()->settings()->delWorkspace(My::id());
                    App::blog()->settings()->renWorkspace('cloneentry', My::id());
                }

                // Change settings names (remove ce_ prefix in them)
                $rename = function (string $name, BlogWorkspaceInterface $settings): void {
                    if ($settings->settingExists('ce_' . $name, true)) {
                        $settings->rename('ce_' . $name, $name);
                    }
                };

                $settings = My::settings();

                foreach ([
                    'active_post',
                    'active_page',
                ] as $value) {
                    $rename($value, $settings);
                }
            }

            // Init
            $settings = My::settings();

            // Default state is active
            $settings->put('active_post', true, App::blogWorkspace()::NS_BOOL, 'Active for posts', false, true);
            $settings->put('active_page', true, App::blogWorkspace()::NS_BOOL, 'Active for pages', false, true);
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }
}
