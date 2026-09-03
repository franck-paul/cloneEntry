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
    private static function cloneEntryButtons(?MetaRecord $metaRecord): void
    {
        if (!is_null($metaRecord)) {
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

    private static function cloneEntry(?MetaRecord $metaRecord): void
    {
        if (!is_null($metaRecord)) {
            $post_id   = $metaRecord->intField('post_id');
            $post_type = $metaRecord->strField('post_type');
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

    public static function clonePostButtons(?MetaRecord $metaRecord): string
    {
        $settings = My::settings();
        if ($settings->getBool('active_post')) {
            self::cloneEntryButtons($metaRecord);
        }

        return '';
    }

    public static function clonePost(?MetaRecord $metaRecord): string
    {
        $settings = My::settings();
        if ($settings->getBool('active_post')) {
            self::cloneEntry($metaRecord);
        }

        return '';
    }

    public static function clonePageButtons(?MetaRecord $metaRecord): string
    {
        $settings = My::settings();
        if ($settings->getBool('active_page')) {
            self::cloneEntryButtons($metaRecord);
        }

        return '';
    }

    public static function clonePage(?MetaRecord $metaRecord): string
    {
        $settings = My::settings();
        if ($settings->getBool('active_page')) {
            self::cloneEntry($metaRecord);
        }

        return '';
    }

    public static function clonePosts(ActionsPosts $actionsPosts): string
    {
        $settings = My::settings();
        // Add menuitem in actions dropdown list
        if ($settings->getBool('active_post') && My::checkContext(My::BACKEND)) {
            $actionsPosts->addAction(
                [__('Clone') => [__('Clone selected posts') => 'clone']],
                self::doClonePosts(...)
            );
        }

        return '';
    }

    public static function clonePages(PagesBackendActions $pagesBackendActions): string
    {
        $settings = My::settings();
        // Add menuitem in actions dropdown list
        if ($settings->getBool('active_page') && My::checkContext(My::BACKEND)) {
            $pagesBackendActions->addAction(
                [__('Clone') => [__('Clone selected pages') => 'clone']],
                self::doClonePages(...)
            );
        }

        return '';
    }

    /**
     * @param      ActionsPosts                 $actionsPosts     Actions
     * @param      ArrayObject<string, mixed>   $arrayObject   The post
     */
    public static function doClonePosts(ActionsPosts $actionsPosts, ArrayObject $arrayObject): void
    {
        self::doCloneEntries($actionsPosts, $arrayObject, 'post');
    }

    /**
     * @param      PagesBackendActions          $pagesBackendActions     Actions
     * @param      ArrayObject<string, mixed>   $arrayObject   The post
     */
    public static function doClonePages(PagesBackendActions $pagesBackendActions, ArrayObject $arrayObject): void
    {
        self::doCloneEntries($pagesBackendActions, $arrayObject, 'page');
    }

    /**
     * @param      ActionsPosts|PagesBackendActions     $actionsPostsPages     Actions
     * @param      ArrayObject<string, mixed>           $arrayObject   The post
     * @param      string                               $type   The type
     */
    private static function doCloneEntries(ActionsPosts|PagesBackendActions $actionsPostsPages, ArrayObject $arrayObject, string $type = 'post'): void
    {
        if (!empty($arrayObject['full_content'])) {
            $posts = $actionsPostsPages->getRS();
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
                        $cur->setStrField('post_type', $type);
                        $cur->setStrField('post_title', $posts->strField('post_title', true));
                        $cur->setIntField('cat_id', $posts->intField('cat_id', true));
                        $cur->setStrField('post_format', $posts->strField('post_format', true));
                        $cur->setStrField('post_password', $posts->strField('post_password', true));
                        $cur->setStrField('post_lang', $posts->strField('post_lang', true));
                        $cur->setStrField('post_title', $posts->strField('post_title', true));
                        $cur->setStrField('post_excerpt', $posts->strField('post_excerpt', true));
                        $cur->setStrField('post_excerpt_xhtml', $posts->strField('post_excerpt_xhtml', true));
                        $cur->setStrField('post_content', $posts->strField('post_content', true));
                        $cur->setStrField('post_content_xhtml', $posts->strField('post_content_xhtml', true));
                        $cur->setStrField('post_notes', $posts->strField('post_notes', true));
                        $cur->setIntField('post_position', $posts->intField('post_position', true));
                        $cur->setBoolField('post_open_comment', $posts->boolField('post_open_comment', true));
                        $cur->setBoolField('post_open_tb', $posts->boolField('post_open_tb', true));
                        $cur->setBoolField('post_selected', $posts->boolField('post_selected'));

                        $cur->setIntField('post_status', App::status()->post()::PENDING); // forced to pending
                        $cur->setStrField('user_id', App::auth()->userID());

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

                $actionsPostsPages->redirect(true, ['upd' => 1]);
            } else {
                $actionsPostsPages->redirect();
            }
        } else {
            // Ask confirmation before cloning
            if ($type === 'page') {
                $actionsPostsPages->beginPage(
                    App::backend()->page()->breadcrumb(
                        [
                            Html::escapeHTML(App::blog()->name()) => '',
                            __('Pages')                           => App::backend()->url()->get('admin.plugin.pages'),
                            __('Clone selected pages')            => '',
                        ]
                    )
                );
            } else {
                $actionsPostsPages->beginPage(
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
            ->action($actionsPostsPages->getURI())
            ->method('post')
            ->fields([
                (new Text(null, $actionsPostsPages->getCheckboxes())),
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
                        (new Text(null, $actionsPostsPages->getHiddenFields())),
                        (new Hidden(['full_content'], 'true')),
                        (new Hidden(['action'], 'clone')),
                        (new Hidden(['process'], ($type === 'post' ? 'Posts' : 'Plugin'))),
                        App::nonce()->formNonce(),
                    ]),
            ])
            ->render();

            $actionsPostsPages->endPage();
        }
    }
}
