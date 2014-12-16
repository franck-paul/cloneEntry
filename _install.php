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

$new_version = $core->plugins->moduleInfo('cloneEntry','version');
$old_version = $core->getVersion('cloneEntry');

if (version_compare($old_version,$new_version,'>=')) return;

try
{
	if (version_compare(DC_VERSION,'2.7','<'))
	{
		throw new Exception('Clone Entry requires Dotclear 2.7');
	}

	$core->blog->settings->addNamespace('cloneentry');

	// Default state is active
	$core->blog->settings->wordcount->put('ce_active_post',true,'boolean','Active for posts',false,true);
	$core->blog->settings->wordcount->put('ce_active_page',true,'boolean','Active for pages',false,true);

	$core->setVersion('cloneEntry',$new_version);

	return true;
}
catch (Exception $e)
{
	$core->error->add($e->getMessage());
}
return false;
