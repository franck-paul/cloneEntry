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

use dcBlog;
use dcCore;
use dcNamespace;
use dcNsProcess;
use dcPage;
use dcPostMedia;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;

class Manage extends dcNsProcess
{
    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        // Larger scope than manage only as this class cope with cloning action on entry edit page
        // See BackendBehaviors::cloneEntry()
        static::$init = My::checkContext(My::BACKEND);

        return static::$init;
    }

    /**
     * Processes the request(s).
     */
    public static function process(): bool
    {
        if (!static::$init) {
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

                $post = dcCore::app()->blog->getPosts($params);

                if ($post->isEmpty()) {
                    dcCore::app()->error->add(__('This entry does not exist.'));
                    Http::redirect(dcCore::app()->getPostAdminURL($post_type, $post_id));
                }

                $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . 'post');

                if ($post_type == 'page') {
                    # Magic tweak :)
                    dcCore::app()->blog->settings->system->post_url_format = '{t}';
                }

                // Duplicate entry contents and options
                $cur->post_type          = $post_type;
                $cur->post_title         = $post->post_title;
                $cur->cat_id             = $post->cat_id;
                $cur->post_format        = $post->post_format;
                $cur->post_password      = $post->post_password;
                $cur->post_lang          = $post->post_lang;
                $cur->post_title         = $post->post_title;
                $cur->post_excerpt       = $post->post_excerpt;
                $cur->post_excerpt_xhtml = $post->post_excerpt_xhtml;
                $cur->post_content       = $post->post_content;
                $cur->post_content_xhtml = $post->post_content_xhtml;
                $cur->post_notes         = $post->post_notes;
                $cur->post_position      = $post->post_position;
                $cur->post_open_comment  = (int) $post->post_open_comment;
                $cur->post_open_tb       = (int) $post->post_open_tb;
                $cur->post_selected      = (int) $post->post_selected;

                $cur->post_status = dcBlog::POST_PENDING; // forced to pending
                $cur->user_id     = dcCore::app()->auth->userID();

                if ($post_type == 'post') {
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

                dcPage::addSuccessNotice(__('Entry has been successfully cloned.'));

                // Go to entry edit page
                Http::redirect(dcCore::app()->getPostAdminURL($post_type, $return_id, false));
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }
        }

        if (My::checkContext(My::MANAGE)) {
            // Real management scope
            if (!empty($_POST['saveconfig'])) {
                try {
                    $active_post = (empty($_POST['active_post'])) ? false : true;
                    $active_page = (empty($_POST['active_page'])) ? false : true;

                    $settings = dcCore::app()->blog->settings->get(My::id());
                    $settings->put('active_post', $active_post, dcNamespace::NS_BOOL);
                    $settings->put('active_page', $active_page, dcNamespace::NS_BOOL);

                    dcCore::app()->blog->triggerBlog();

                    dcPage::addSuccessNotice(__('Configuration successfully updated.'));
                    Http::redirect(dcCore::app()->admin->getPageURL());
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
            }
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!static::$init) {
            return;
        }

        if (!My::checkContext(My::MANAGE)) {
            // Not in real management scope (see above)
            return;
        }

        // Getting current parameters
        $settings = dcCore::app()->blog->settings->get(My::id());

        $active_post = (bool) $settings->active_post;
        $active_page = (bool) $settings->active_page;

        dcPage::openModule(__('Clone Entry'));

        echo dcPage::breadcrumb(
            [
                Html::escapeHTML(dcCore::app()->blog->name) => '',
                __('Clone Entry')                           => '',
            ]
        );
        echo dcPage::notices();

        // Form
        echo (new Form('options'))
            ->action(dcCore::app()->admin->getPageURL())
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
                    dcCore::app()->formNonce(false),
                ]),
            ])
            ->render();

        dcPage::closeModule();
    }
}
