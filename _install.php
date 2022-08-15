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
if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

$new_version = dcCore::app()->plugins->moduleInfo('cloneEntry', 'version');
$old_version = dcCore::app()->getVersion('cloneEntry');

if (version_compare($old_version, $new_version, '>=')) {
    return;
}

try {
    dcCore::app()->blog->settings->addNamespace('cloneentry');

    // Default state is active
    dcCore::app()->blog->settings->cloneentry->put('ce_active_post', true, 'boolean', 'Active for posts', false, true);
    dcCore::app()->blog->settings->cloneentry->put('ce_active_page', true, 'boolean', 'Active for pages', false, true);

    dcCore::app()->setVersion('cloneEntry', $new_version);

    return true;
} catch (Exception $e) {
    dcCore::app()->error->add($e->getMessage());
}

return false;
