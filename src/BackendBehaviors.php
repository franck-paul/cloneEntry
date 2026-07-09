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

namespace Dotclear\Plugin\cloneEntry;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Note;
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
            echo (new Div('clone-entry-buttons'))
                ->items([
                    (new Para())
                        ->items([
                            (new Submit(['clone'], __('Clone this entry')))->class('clone')->form('clone-form'),
                        ]),
                    (new Note())
                        ->class('form-note')
                        ->text(
                            __('The status of the new entry will be set <strong>to Pending</strong>.') . '<br>' .
                            __('It\'s date and time will bet set to now and it\'s URL would reflect this.') . '<br>' .
                            __('The category, tags, attachments and other properties will be preserved.')
                        ),
                ])
            ->render();
        }
    }

    private static function cloneEntry(?MetaRecord $post): void
    {
        if (!is_null($post)) {
            $post_id   = $post->intField('post_id');
            $post_type = $post->strField('post_type');
            if ($post_id !== 0 && $post_type !== '') {
                // Display clone button
                echo (new Div('clone-entry'))
                    ->class('clear')
                    ->items([
                        (new Form('clone-form'))
                            ->action(App::backend()->url()->get('admin.plugin.cloneEntry'))
                            ->method('post')
                            ->fields([
                                ...My::hiddenFields([
                                    'clone_id'   => $post_id,
                                    'clone_type' => $post_type,
                                ]),
                            ]),
                    ])
                ->render();
            }
        }
    }

    public static function clonePostButtons(?MetaRecord $post): string
    {
        $settings = My::settings();
        if ($settings->getBool('active_post')) {
            self::cloneEntryButtons($post);
        }

        return '';
    }

    public static function clonePost(?MetaRecord $post): string
    {
        $settings = My::settings();
        if ($settings->getBool('active_post')) {
            self::cloneEntry($post);
        }

        return '';
    }

    public static function clonePageButtons(?MetaRecord $post): string
    {
        $settings = My::settings();
        if ($settings->getBool('active_page')) {
            self::cloneEntryButtons($post);
        }

        return '';
    }

    public static function clonePage(?MetaRecord $post): string
    {
        $settings = My::settings();
        if ($settings->getBool('active_page')) {
            self::cloneEntry($post);
        }

        return '';
    }

    public static function clonePosts(ActionsPosts $ap): string
    {
        $settings = My::settings();
        // Add menuitem in actions dropdown list
        if ($settings->getBool('active_post') && My::checkContext(My::BACKEND)) {
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
        if ($settings->getBool('active_page') && My::checkContext(My::BACKEND)) {
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
            if ($posts->rows() !== []) {
                while ($posts->fetch()) {
                    $post_id = $posts->intField('post_id');
                    if ($post_id > 0) {
                        // Prepare new entry
                        $cur = App::db()->con()->openCursor(App::db()->con()->prefix() . 'post');

                        if ($type === 'page') {
                            # Magic tweak :)
                            App::blog()->settings()->get('system')->set('post_url_format', '{t}');
                        }

                        // Duplicate entry contents and options
                        $cur->post_type          = $type;
                        $cur->post_title         = $posts->strField('post_title', true);
                        $cur->cat_id             = $posts->intField('cat_id', true);
                        $cur->post_format        = $posts->strField('post_format', true);
                        $cur->post_password      = $posts->strField('post_password', true);
                        $cur->post_lang          = $posts->strField('post_lang', true);
                        $cur->post_title         = $posts->strField('post_title', true);
                        $cur->post_excerpt       = $posts->strField('post_excerpt', true);
                        $cur->post_excerpt_xhtml = $posts->strField('post_excerpt_xhtml', true);
                        $cur->post_content       = $posts->strField('post_content', true);
                        $cur->post_content_xhtml = $posts->strField('post_content_xhtml', true);
                        $cur->post_notes         = $posts->strField('post_notes', true);
                        $cur->post_position      = $posts->intField('post_position', true);
                        $cur->post_open_comment  = $posts->boolField('post_open_comment', true);
                        $cur->post_open_tb       = $posts->boolField('post_open_tb', true);
                        $cur->post_selected      = $posts->boolField('post_selected');

                        $cur->post_status = App::status()->post()::PENDING; // forced to pending
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
                            if ($meta->strField('meta_type') !== '' && $meta->strField('meta_id') !== '') {
                                App::meta()->setPostMeta($return_id, $meta->strField('meta_type'), $meta->strField('meta_id'));
                            }
                        }

                        // If old entry has attached media, duplicate them too
                        $postmedia = App::postMedia();
                        $media     = $postmedia->getPostMedia(['post_id' => $post_id]);
                        while ($media->fetch()) {
                            $media_id = $media->intField('media_id');
                            if ($media_id > 0) {
                                $postmedia->addPostMedia($return_id, $media_id);
                            }
                        }
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
                    App::backend()->page()->breadcrumb(
                        [
                            Html::escapeHTML(App::blog()->name()) => '',
                            __('Pages')                           => App::backend()->url()->get('admin.plugin.pages'),
                            __('Clone selected pages')            => '',
                        ]
                    )
                );
            } else {
                $ap->beginPage(
                    App::backend()->page()->breadcrumb(
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
                (new Para())
                    ->items([
                        (new Submit(['clone'], __('Clone'))),
                    ]),
                (new Note())
                    ->class('form-note')
                    ->text(
                        __('The status of the new entry will be set <strong>to Pending</strong>.') . '<br>' .
                        __('It\'s date and time will bet set to now and it\'s URL would reflect this.') . '<br>' .
                        __('The category, tags, attachments and other properties will be preserved.')
                    ),
                (new Para())
                    ->items([
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
