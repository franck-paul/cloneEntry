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
$this->registerModule(
    'Clone Entry',
    'Make a clone of entry',
    'Franck Paul',
    '7.0',
    [
        'date'        => '2003-08-13T13:42:00+0100',
        'requires'    => [['core', '2.33']],
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
