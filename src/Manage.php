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

use Dotclear\App;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;

class Manage extends Process
{
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
                $post_id   = $_POST['clone_id'];
                $post_type = $_POST['clone_type'];

                // Duplicate entry

                // Set date-time to now()
                // Set status to pending (-2)
                $params['post_type'] = $post_type;
                $params['post_id']   = $post_id;

                $post = App::blog()->getPosts($params);

                if ($post->isEmpty()) {
                    App::error()->add(__('This entry does not exist.'));
                    Http::redirect(App::postTypes()->get($post_type)->adminUrl($post_id));
                }

                $cur = App::con()->openCursor(App::con()->prefix() . 'post');

                if ($post_type == 'page') {
                    # Magic tweak :)
                    App::blog()->settings()->system->post_url_format = '{t}';
                }

                // Duplicate entry contents and options
                $cur->post_type          = $post_type;
                $cur->post_title         = $post->post_title;
                $cur->cat_id             = $post->cat_id;
                $cur->post_format        = $post->post_format;
                $cur->post_password      = $post->post_password;
                $cur->post_lang          = $post->post_lang;
                $cur->post_excerpt       = $post->post_excerpt;
                $cur->post_excerpt_xhtml = $post->post_excerpt_xhtml;
                $cur->post_content       = $post->post_content;
                $cur->post_content_xhtml = $post->post_content_xhtml;
                $cur->post_notes         = $post->post_notes;
                $cur->post_position      = $post->post_position;
                $cur->post_open_comment  = (int) $post->post_open_comment;
                $cur->post_open_tb       = (int) $post->post_open_tb;
                $cur->post_selected      = (int) $post->post_selected;

                $cur->post_status = App::status()->post()::PENDING; // forced to pending
                $cur->user_id     = App::auth()->userID();

                if ($post_type == 'post') {
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
                    $postmedia->addPostMedia($return_id, (int) $media->media_id);
                }

                Notices::addSuccessNotice(__('Entry has been successfully cloned.'));

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

                Notices::addSuccessNotice(__('Configuration successfully updated.'));
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

        $active_post = (bool) $settings->active_post;
        $active_page = (bool) $settings->active_page;

        Page::openModule(My::name());

        echo Page::breadcrumb(
            [
                Html::escapeHTML(App::blog()->name()) => '',
                __('Clone Entry')                     => '',
            ]
        );
        echo Notices::getNotices();

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

        Page::closeModule();
    }
}
