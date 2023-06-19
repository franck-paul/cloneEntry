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

use ArrayObject;
use dcBlog;
use dcCore;
use dcPage;
use dcPostMedia;
use dcPostsActions;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\pages\BackendActions as PagesBackendActions;

class BackendBehaviors
{
    public static function cloneEntry($post)
    {
        if ($post != null) {
            // Display clone button
            echo (new Para('clone-entry', 'div'))->class('clear')->items([
                (new Form('clone-form'))
                    ->action(dcCore::app()->adminurl->get('admin.plugin.cloneEntry'))
                    ->method('post')
                    ->fields([
                        (new Para())->items([
                            (new Submit(['clone'], __('Clone this entry')))->class('clone'),
                            dcCore::app()->formNonce(false),
                            (new Hidden('clone_id', $post->post_id)),
                            (new Hidden('clone_type', $post->post_type)),
                        ]),
                        (new Para())->class('form-note')->items([
                            (new Text(
                                null,
                                __('The status of the new entry will be set <strong>to Pending</strong>.') . '<br />' .
                                __('It\'s date and time will bet set to now and it\'s URL would reflect this.') . '<br />' .
                                __('The category, tags, attachments and other properties will be preserved.')
                            )),
                        ]),
                    ]),
            ])
            ->render();
        }
    }

    public static function clonePost($post)
    {
        $settings = dcCore::app()->blog->settings->get(My::id());
        if ($settings->active_post) {
            BackendBehaviors::cloneEntry($post);
        }
    }

    public static function clonePage($post)
    {
        $settings = dcCore::app()->blog->settings->get(My::id());
        if ($settings->active_page) {
            BackendBehaviors::cloneEntry($post);
        }
    }

    public static function clonePosts(dcPostsActions $ap)
    {
        $settings = dcCore::app()->blog->settings->get(My::id());
        if ($settings->active_post) {
            // Add menuitem in actions dropdown list
            if (My::checkContext(My::BACKEND)) {
                $ap->addAction(
                    [__('Clone') => [__('Clone selected posts') => 'clone']],
                    [self::class, 'doClonePosts']
                );
            }
        }
    }

    public static function clonePages(PagesBackendActions $ap)
    {
        $settings = dcCore::app()->blog->settings->get(My::id());
        if ($settings->active_page) {
            // Add menuitem in actions dropdown list
            if (My::checkContext(My::BACKEND)) {
                $ap->addAction(
                    [__('Clone') => [__('Clone selected pages') => 'clone']],
                    [self::class, 'doClonePages']
                );
            }
        }
    }

    public static function doClonePosts(dcPostsActions $ap, ArrayObject $post)
    {
        self::doCloneEntries($ap, $post, 'post');
    }

    public static function doClonePages(PagesBackendActions $ap, ArrayObject $post)
    {
        self::doCloneEntries($ap, $post, 'page');
    }

    public static function doCloneEntries($ap, ArrayObject $post, $type = 'post')
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
                        dcCore::app()->blog->settings->system->post_url_format = '{t}';
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

                    $cur->post_status = dcBlog::POST_PENDING; // forced to pending
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
                    $postmedia = new dcPostMedia();
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
                            Html::escapeHTML(dcCore::app()->blog->name) => '',
                            __('Pages')                                 => dcCore::app()->adminurl->get('admin.plugin.pages'),
                            __('Clone selected pages')                  => '',
                        ]
                    )
                );
            } else {
                $ap->beginPage(
                    dcPage::breadcrumb(
                        [
                            Html::escapeHTML(dcCore::app()->blog->name) => '',
                            __('Entries')                               => dcCore::app()->adminurl->get('admin.posts'),
                            __('Clone selected posts')                  => '',
                        ]
                    )
                );
            }

            echo (new Form('clone-form'))
                ->action($ap->getURI())
                ->method('post')
                ->fields([
                    (new Text(null, $ap->getCheckboxes())),
                    (new Para())->items([
                        (new Submit(['clone'], __('Clone'))),
                    ]),
                    (new Para())->class('form-note')->items([
                        (new Text(
                            null,
                            __('The status of the new entry will be set <strong>to Pending</strong>.') . '<br />' .
                            __('It\'s date and time will bet set to now and it\'s URL would reflect this.') . '<br />' .
                            __('The category, tags, attachments and other properties will be preserved.')
                        )),
                    ]),
                    (new Para())->items([
                        dcCore::app()->formNonce(false),
                        (new Text(null, $ap->getHiddenFields())),
                        (new Hidden('full_content', 'true')),
                        (new Hidden('action', 'clone')),
                    ]),
                ])
            ->render();

            echo
            $ap->endPage();
        }
    }
}
