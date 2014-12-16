<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
# This file is part of cloneEntry, a plugin for Dotclear 2.
#
# Copyright (c) Franck Paul and contributors
# carnet.franck.paul@gmail.com
#
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_CONTEXT_ADMIN')) { return; }

// dead but useful code, in order to have translations
__('Clone Entry').__('Clone the edited entry');

// Add menu item in blog menu
$_menu['Blog']->addItem(__('Clone Entry'),'plugin.php?p=cloneEntry','index.php?pf=cloneEntry/icon.png',
		preg_match('/plugin.php\?p=cloneEntry(&.*)?$/',$_SERVER['REQUEST_URI']),
		$core->auth->check('page,contentadmin',$core->blog->id));

// Add behaviour callback for post
$core->addBehavior('adminPostAfterForm',array('adminCloneEntry','clonePost'));
// Add behaviour callback for page
$core->addBehavior('adminPageAfterForm',array('adminCloneEntry','clonePage'));

class adminCloneEntry
{
	static function cloneEntry($post)
	{
		global $core;

		if ($post != null) {
			// Display clone button
			$res =
				'<form action="'.$core->adminurl->get('admin.plugin.cloneEntry').'" method="post" id="clone-form">'."\n".
				'<input type="submit" value="'.__('Clone this entry').'" name="clone" />'."\n".
				form::hidden('id',$post->post_id)."\n".
				form::hidden('type',$post->post_type)."\n".
				$core->formNonce()."\n".
				'</form>'."\n";
			echo $res;
		}
	}

	public static function clonePost($post)
	{
		global $core;

		$core->blog->settings->addNamespace('cloneentry');
		if ($core->blog->settings->cloneentry->ce_active_post) {
			adminCloneEntry::cloneEntry($post);
		}
	}

	public static function clonePage($post)
	{
		global $core;

		$core->blog->settings->addNamespace('cloneentry');
		if ($core->blog->settings->cloneentry->ce_active_page) {
			adminCloneEntry::cloneEntry($post);
		}
	}
}
