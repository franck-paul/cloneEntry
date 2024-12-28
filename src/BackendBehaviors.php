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
use Dotclear\App;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Core\Backend\Page;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\pages\BackendActions as PagesBackendActions;

class BackendBehaviors
{
    private static function cloneEntryButtons(?MetaRecord $post): void
    {
        if (!is_null($post)) {
            // Display clone button
            echo (new Para('clone-entry-buttons', 'div'))->items([
                (new Para())->items([
                    (new Submit(['clone'], __('Clone this entry')))->class('clone')->form('clone-form'),
                ]),
                (new Para())->class('form-note')->items([
                    (new Text(
                        null,
                        __('The status of the new entry will be set <strong>to Pending</strong>.') . '<br>' .
                        __('It\'s date and time will bet set to now and it\'s URL would reflect this.') . '<br>' .
                        __('The category, tags, attachments and other properties will be preserved.')
                    )),
                ]),
            ])
            ->render();
        }
    }

    private static function cloneEntry(?MetaRecord $post): void
    {
        if (!is_null($post)) {
            // Display clone button
            echo (new Para('clone-entry', 'div'))->class('clear')->items([
                (new Form('clone-form'))
                    ->action(App::backend()->url()->get('admin.plugin.cloneEntry'))
                    ->method('post')
                    ->fields([
                        ...My::hiddenFields([
                            'clone_id'   => $post->post_id,
                            'clone_type' => $post->post_type,
                        ]),
                    ]),
            ])
            ->render();
        }
    }

    public static function clonePostButtons(?MetaRecord $post): string
    {
        $settings = My::settings();
        if ($settings->active_post) {
            self::cloneEntryButtons($post);
        }

        return '';
    }

    public static function clonePost(?MetaRecord $post): string
    {
        $settings = My::settings();
        if ($settings->active_post) {
            self::cloneEntry($post);
        }

        return '';
    }

    public static function clonePageButtons(?MetaRecord $post): string
    {
        $settings = My::settings();
        if ($settings->active_page) {
            self::cloneEntryButtons($post);
        }

        return '';
    }

    public static function clonePage(?MetaRecord $post): string
    {
        $settings = My::settings();
        if ($settings->active_page) {
            self::cloneEntry($post);
        }

        return '';
    }

    public static function clonePosts(ActionsPosts $ap): string
    {
        $settings = My::settings();
        // Add menuitem in actions dropdown list
        if ($settings->active_post && My::checkContext(My::BACKEND)) {
            $ap->addAction(
                [__('Clone') => [__('Clone selected posts') => 'clone']],
                self::doClonePosts(...)
            );
        }

        return '';
    }

    public static function clonePages(PagesBackendActions $ap): string
    {
        $settings = My::settings();
        // Add menuitem in actions dropdown list
        if ($settings->active_page && My::checkContext(My::BACKEND)) {
            $ap->addAction(
                [__('Clone') => [__('Clone selected pages') => 'clone']],
                self::doClonePages(...)
            );
        }

        return '';
    }

    /**
     * @param      ActionsPosts                 $ap     Actions
     * @param      ArrayObject<string, mixed>   $post   The post
     */
    public static function doClonePosts(ActionsPosts $ap, ArrayObject $post): void
    {
        self::doCloneEntries($ap, $post, 'post');
    }

    /**
     * @param      PagesBackendActions          $ap     Actions
     * @param      ArrayObject<string, mixed>   $post   The post
     */
    public static function doClonePages(PagesBackendActions $ap, ArrayObject $post): void
    {
        self::doCloneEntries($ap, $post, 'page');
    }

    /**
     * @param      ActionsPosts|PagesBackendActions     $ap     Actions
     * @param      ArrayObject<string, mixed>           $post   The post
     * @param      string                               $type   The type
     */
    private static function doCloneEntries(ActionsPosts|PagesBackendActions $ap, ArrayObject $post, string $type = 'post'): void
    {
        if (!empty($post['full_content'])) {
            $posts = $ap->getRS();
            if ($posts->rows()) {
                while ($posts->fetch()) {
                    $post_id = $posts->post_id;

                    // Prepare new entry
                    $cur = App::con()->openCursor(App::con()->prefix() . 'post');

                    if ($type === 'page') {
                        # Magic tweak :)
                        App::blog()->settings()->system->post_url_format = '{t}';
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

                    $cur->post_status = App::blog()::POST_PENDING; // forced to pending
                    $cur->user_id     = App::auth()->userID();

                    if ($type === 'post') {
                        # --BEHAVIOR-- adminBeforePostCreate
                        App::behavior()->callBehavior('adminBeforePostCreate', $cur);

                        $return_id = App::blog()->addPost($cur);

                        # --BEHAVIOR-- adminAfterPostCreate
                        App::behavior()->callBehavior('adminAfterPostCreate', $cur, $return_id);
                    } else {
                        # --BEHAVIOR-- adminBeforePageCreate
                        App::behavior()->callBehavior('adminBeforePageCreate', $cur);

                        $return_id = App::blog()->addPost($cur);

                        # --BEHAVIOR-- adminAfterPageCreate
                        App::behavior()->callBehavior('adminAfterPageCreate', $cur, $return_id);
                    }

                    // If old entry has meta data, duplicate them too
                    $meta = App::meta()->getMetadata(['post_id' => $post_id]);
                    while ($meta->fetch()) {
                        App::meta()->setPostMeta($return_id, $meta->meta_type, $meta->meta_id);
                    }

                    // If old entry has attached media, duplicate them too
                    $postmedia = App::postMedia();
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
            if ($type === 'page') {
                $ap->beginPage(
                    Page::breadcrumb(
                        [
                            Html::escapeHTML(App::blog()->name()) => '',
                            __('Pages')                           => App::backend()->url()->get('admin.plugin.pages'),
                            __('Clone selected pages')            => '',
                        ]
                    )
                );
            } else {
                $ap->beginPage(
                    Page::breadcrumb(
                        [
                            Html::escapeHTML(App::blog()->name()) => '',
                            __('Entries')                         => App::backend()->url()->get('admin.posts'),
                            __('Clone selected posts')            => '',
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
                        __('The status of the new entry will be set <strong>to Pending</strong>.') . '<br>' .
                        __('It\'s date and time will bet set to now and it\'s URL would reflect this.') . '<br>' .
                        __('The category, tags, attachments and other properties will be preserved.')
                    )),
                ]),
                (new Para())->items([
                    (new Text(null, $ap->getHiddenFields())),
                    (new Hidden(['full_content'], 'true')),
                    (new Hidden(['action'], 'clone')),
                    (new Hidden(['process'], ($type === 'post' ? 'Posts' : 'Plugin'))),
                    App::nonce()->formNonce(),
                ]),
            ])
            ->render();

            $ap->endPage();
        }
    }
}
