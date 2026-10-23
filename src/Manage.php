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

use Dotclear\App;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

class Manage
{
    use TraitProcess;

    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        // Larger scope than manage only as this class cope with cloning action on entry edit page
        // See BackendBehaviors::cloneEntry()
        return self::status(My::checkContext(My::BACKEND));
    }

    /**
     * Processes the request(s).
     */
    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (!empty($_POST['clone'])) {
            try {
                $post_id   = isset($_POST['clone_id'])   && is_numeric($post_id = $_POST['clone_id']) ? (int) $post_id : 0;
                $post_type = isset($_POST['clone_type']) && is_string($post_type = $_POST['clone_type']) ? $post_type : '';
                if ($post_id === 0 || $post_type === '') {
                    throw new Exception(__('Unable to clone the entry.'));
                }

                // Duplicate entry

                // Set date-time to now()
                // Set status to pending (-2)
                $params = [
                    'post_type' => $post_type,
                    'post_id'   => $post_id,
                ];
                $post = App::blog()->getPosts($params);

                if ($post->isEmpty()) {
                    App::error()->add(__('This entry does not exist.'));
                    Http::redirect(App::postTypes()->get($post_type)->adminUrl($post_id));
                }

                $cur = App::db()->con()->openCursor(App::db()->con()->prefix() . 'post');

                if ($post_type === 'page') {
                    # Magic tweak :)
                    App::blog()->settings()->get('system')->set('post_url_format', '{t}');
                }

                // Duplicate entry contents and options
                $cur->setStrField('post_type', $post_type);
                $cur->setStrField('post_title', $post->strField('post_title', true));
                $cur->setIntField('cat_id', $post->intField('cat_id', true));
                $cur->setStrField('post_format', $post->strField('post_format', true));
                $cur->setStrField('post_password', $post->strField('post_password', true));
                $cur->setStrField('post_lang', $post->strField('post_lang', true));
                $cur->setStrField('post_excerpt', $post->strField('post_excerpt', true));
                $cur->setStrField('post_excerpt_xhtml', $post->strField('post_excerpt_xhtml', true));
                $cur->setStrField('post_content', $post->strField('post_content', true));
                $cur->setStrField('post_content_xhtml', $post->strField('post_content_xhtml', true));
                $cur->setStrField('post_notes', $post->strField('post_notes', true));
                $cur->setIntField('post_position', $post->intField('post_position', true));
                $cur->setBoolField('post_open_comment', $post->boolField('post_open_comment', true));
                $cur->setBoolField('post_open_tb', $post->boolField('post_open_tb', true));
                $cur->setBoolField('post_selected', $post->boolField('post_selected', true));

                $cur->setIntField('post_status', App::status()->post()::PENDING); // forced to pending
                $cur->setStrField('user_id', App::auth()->userID());

                if ($post_type === 'post') {
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
                    if ($media_id !== 0) {
                        $postmedia->addPostMedia($return_id, $media_id);
                    }
                }

                App::backend()->notices()->addSuccessNotice(__('Entry has been successfully cloned.'));

                // Go to entry edit page
                Http::redirect(App::postTypes()->get($post_type)->adminUrl($return_id, false));
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        // Real management scope
        if (My::checkContext(My::MANAGE) && !empty($_POST['saveconfig'])) {
            try {
                $active_post = !empty($_POST['active_post']);
                $active_page = !empty($_POST['active_page']);

                $settings = My::settings();
                $settings->put('active_post', $active_post, App::blogWorkspace()::NS_BOOL);
                $settings->put('active_page', $active_page, App::blogWorkspace()::NS_BOOL);

                App::blog()->triggerBlog();

                App::backend()->notices()->addSuccessNotice(__('Configuration successfully updated.'));
                My::redirect();
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        if (!My::checkContext(My::MANAGE)) {
            // Not in real management scope (see above)
            return;
        }

        // Getting current parameters
        $settings = My::settings();

        $active_post = $settings->getBool('active_post', false);
        $active_page = $settings->getBool('active_page', false);

        App::backend()->page()->openModule(My::name());

        echo App::backend()->page()->breadcrumb(
            [
                Html::escapeHTML(App::blog()->name()) => '',
                __('Clone Entry')                     => '',
            ]
        );
        echo App::backend()->notices()->getNotices();

        // Form
        echo (new Form('options'))
            ->action(App::backend()->getPageURL())
            ->method('post')
            ->fields([
                (new Para())->items([
                    (new Checkbox('active_post', $active_post))
                        ->value(1)
                        ->label((new Label(__('Enable Cloning of post for this blog'), Label::INSIDE_TEXT_AFTER))),
                ]),
                (new Para())->items([
                    (new Checkbox('active_page', $active_page))
                        ->value(1)
                        ->label((new Label(__('Enable Cloning of page for this blog'), Label::INSIDE_TEXT_AFTER))),
                ]),
                (new Para())->items([
                    (new Submit(['saveconfig'], __('Save configuration')))
                        ->accesskey('s'),
                    ... My::hiddenFields(),
                ]),
            ])
            ->render();

        App::backend()->page()->closeModule();
    }
}
