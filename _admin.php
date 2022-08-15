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

// dead but useful code, in order to have translations
__('Clone Entry') . __('Make a clone of entry');

// Add menu item in blog menu
$_menu['Blog']->addItem(
    __('Clone Entry'),
    'plugin.php?p=cloneEntry',
    [urldecode(dcPage::getPF('cloneEntry/icon.svg')), urldecode(dcPage::getPF('cloneEntry/icon-dark.svg'))],
    preg_match('/plugin.php\?p=cloneEntry(&.*)?$/', $_SERVER['REQUEST_URI']),
    dcCore::app()->auth->check('page,contentadmin', dcCore::app()->blog->id)
);

// Add behaviour callback for post
dcCore::app()->addBehavior('adminPostAfterForm', ['adminCloneEntry', 'clonePost']);
// Add behaviour callback for page
dcCore::app()->addBehavior('adminPageAfterForm', ['adminCloneEntry', 'clonePage']);

/* Add behavior callbacks for posts actions */
dcCore::app()->addBehavior('adminPostsActionsPage', ['adminCloneEntry', 'clonePosts']);
dcCore::app()->addBehavior('adminPagesActionsPage', ['adminCloneEntry', 'clonePages']);

class adminCloneEntry
{
    public static function cloneEntry($post)
    {
        if ($post != null) {
            // Display clone button
            $res = '<div id="clone-entry" class="clear">' . "\n" .
            '<form action="' . dcCore::app()->adminurl->get('admin.plugin.cloneEntry') . '" method="post" id="clone-form">' . "\n" .
            '<p>' . "\n" .
            '<input type="submit" value="' . __('Clone this entry') . '" name="clone" class="clone" />' . "\n" .
            form::hidden('clone_id', $post->post_id) . "\n" .
            form::hidden('clone_type', $post->post_type) . "\n" .
            dcCore::app()->formNonce() . "\n" .
            '</p>' . "\n" .
            '<p class="form-note">' . __('The status of the new entry will be set <strong>to Pending</strong>.') . '<br />' . "\n" .
            __('It\'s date and time will bet set to now and it\'s URL would reflect this.') . '<br />' . "\n" .
            __('The category, tags, attachments and other properties will be preserved.') . '</p>' . "\n" .
                '</form>' . "\n" .
                '</div>' . "\n";
            echo $res;
        }
    }

    public static function clonePost($post)
    {
        dcCore::app()->blog->settings->addNamespace('cloneentry');
        if (dcCore::app()->blog->settings->cloneentry->ce_active_post) {
            adminCloneEntry::cloneEntry($post);
        }
    }

    public static function clonePage($post)
    {
        dcCore::app()->blog->settings->addNamespace('cloneentry');
        if (dcCore::app()->blog->settings->cloneentry->ce_active_page) {
            adminCloneEntry::cloneEntry($post);
        }
    }

    public static function clonePosts($core, $ap)
    {
        dcCore::app()->blog->settings->addNamespace('cloneentry');
        if (dcCore::app()->blog->settings->cloneentry->ce_active_post) {
            // Add menuitem in actions dropdown list
            if (dcCore::app()->auth->check('contentadmin', dcCore::app()->blog->id)) {
                $ap->addAction(
                    [__('Clone') => [__('Clone selected posts') => 'clone']],
                    ['adminCloneEntry', 'doClonePosts']
                );
            }
        }
    }

    public static function clonePages($core, $ap)
    {
        dcCore::app()->blog->settings->addNamespace('cloneentry');
        if (dcCore::app()->blog->settings->cloneentry->ce_active_page) {
            // Add menuitem in actions dropdown list
            if (dcCore::app()->auth->check('contentadmin', dcCore::app()->blog->id)) {
                $ap->addAction(
                    [__('Clone') => [__('Clone selected pages') => 'clone']],
                    ['adminCloneEntry', 'doClonePages']
                );
            }
        }
    }

    public static function doClonePosts($core, dcPostsActionsPage $ap, $post)
    {
        self::doCloneEntries(dcCore::app(), $ap, $post, 'post');
    }

    public static function doClonePages($core, dcPostsActionsPage $ap, $post)
    {
        self::doCloneEntries(dcCore::app(), $ap, $post, 'page');
    }

    public static function doCloneEntries($core, dcPostsActionsPage $ap, $post, $type = 'post')
    {
        if (!empty($post['full_content'])) {
            $posts = $ap->getRS();
            if ($posts->rows()) {
                while ($posts->fetch()) {
                    $post_id = $posts->post_id;

                    // Prepare new entry
                    $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . 'post');

                    if ($type == 'page') {
                        # Magic tweak :)
                        dcCore::app()->blog->settings->system->post_url_format = $GLOBALS['page_url_format'];
                    }

                    // Duplicate entry contents and options
                    $cur->post_type          = $type;
                    $cur->post_title         = $posts->post_title;
                    $cur->cat_id             = $posts->cat_id;
                    $cur->post_format        = $posts->post_format;
                    $cur->post_password      = $posts->post_password;
                    $cur->post_lang          = $posts->post_lang;
                    $cur->post_title         = $posts->post_title;
                    $cur->post_excerpt       = $posts->post_excerpt;
                    $cur->post_excerpt_xhtml = $posts->post_excerpt_xhtml;
                    $cur->post_content       = $posts->post_content;
                    $cur->post_content_xhtml = $posts->post_content_xhtml;
                    $cur->post_notes         = $posts->post_notes;
                    $cur->post_position      = $posts->post_position;
                    $cur->post_open_comment  = (int) $posts->post_open_comment;
                    $cur->post_open_tb       = (int) $posts->post_open_tb;
                    $cur->post_selected      = (int) $posts->post_selected;

                    $cur->post_status = -2; // forced to pending
                    $cur->user_id     = dcCore::app()->auth->userID();

                    if ($type == 'post') {

                        # --BEHAVIOR-- adminBeforePostCreate
                        dcCore::app()->callBehavior('adminBeforePostCreate', $cur);

                        $return_id = dcCore::app()->blog->addPost($cur);

                        # --BEHAVIOR-- adminAfterPostCreate
                        dcCore::app()->callBehavior('adminAfterPostCreate', $cur, $return_id);
                    } else {

                        # --BEHAVIOR-- adminBeforePageCreate
                        dcCore::app()->callBehavior('adminBeforePageCreate', $cur);

                        $return_id = dcCore::app()->blog->addPost($cur);

                        # --BEHAVIOR-- adminAfterPageCreate
                        dcCore::app()->callBehavior('adminAfterPageCreate', $cur, $return_id);
                    }

                    // If old entry has meta data, duplicate them too
                    $meta = dcCore::app()->meta->getMetadata(['post_id' => $post_id]);
                    while ($meta->fetch()) {
                        dcCore::app()->meta->setPostMeta($return_id, $meta->meta_type, $meta->meta_id);
                    }

                    // If old entry has attached media, duplicate them too
                    $postmedia = new dcPostMedia(dcCore::app());
                    $media     = $postmedia->getPostMedia(['post_id' => $post_id]);
                    while ($media->fetch()) {
                        $postmedia->addPostMedia($return_id, $media->media_id);
                    }
                }
                $ap->redirect(true, ['upd' => 1]);
            } else {
                $ap->redirect();
            }
        } else {
            // Ask confirmation before cloning
            if ($type == 'page') {
                $ap->beginPage(
                    dcPage::breadcrumb(
                        [
                            html::escapeHTML(dcCore::app()->blog->name) => '',
                            __('Pages')                                 => 'plugin.php?p=pages',
                            __('Clone selected pages')                  => '',
                        ]
                    )
                );
            } else {
                $ap->beginPage(
                    dcPage::breadcrumb(
                        [
                            html::escapeHTML(dcCore::app()->blog->name) => '',
                            __('Entries')                               => 'posts.php',
                            __('Clone selected posts')                  => '',
                        ]
                    )
                );
            }

            echo
            '<form action="' . $ap->getURI() . '" method="post">' .
            $ap->getCheckboxes() .
            '<p><input type="submit" value="' . __('Clone') . '" /></p>' . "\n" .

            '<p class="form-note">' . __('The status of the new entry will be set <strong>to Pending</strong>.') . '<br />' . "\n" .
            __('It\'s date and time will bet set to now and it\'s URL would reflect this.') . '<br />' . "\n" .
            __('The category, tags, attachments and other properties will be preserved.') . '</p>' . "\n" .

            dcCore::app()->formNonce() . $ap->getHiddenFields() .
            form::hidden(['full_content'], 'true') .
            form::hidden(['action'], 'clone') .
                '</form>';
            $ap->endPage();
        }
    }
}
