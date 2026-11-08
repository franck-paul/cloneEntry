<?php

/**
 * @brief cloneEntry, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul contact@open-time.net
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

if (isset($this) && is_object($this) && method_exists($this, 'registerModule') && isset($this->id) && is_string($this->id)) {
    $this->registerModule(
        'Clone Entry',
        'Make a clone of entry',
        'Franck Paul',
        '10.0',
        [
            'date'        => '2026-11-08T10:11:03+0100',
            'requires'    => [['core', '2.40']],
            'permissions' => 'My',
            'type'        => 'plugin',
            'priority'    => 2000,
            'settings'    => [
            ],

            'details'    => 'https://open-time.net/?q=cloneEntry',
            'support'    => 'https://github.com/franck-paul/cloneEntry',
            'repository' => 'https://raw.githubusercontent.com/franck-paul/cloneEntry/main/dcstore.xml',
            'license'    => 'gpl2',
        ]
    );
}
