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

if (!defined('DC_RC_PATH')) {return;}

$this->registerModule(
    "Clone Entry",           // Name
    "Make a clone of entry", // Description
    "Franck Paul",           // Author
    '0.4',                   // Version
    [
        'requires'    => [['core', '2.13']],   // Dependencies
        'permissions' => 'usage,contentadmin', // Permissions
        'type'        => 'plugin',             // Type
        'priority'    => 2000                 // Priority
    ]
);
