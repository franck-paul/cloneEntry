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

// Getting current parameters
dcCore::app()->blog->settings->addNamespace('cloneentry');
$ce_active_post = (bool) dcCore::app()->blog->settings->cloneentry->ce_active_post;
$ce_active_page = (bool) dcCore::app()->blog->settings->cloneentry->ce_active_page;

// Cloning entry
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
            http::redirect(dcCore::app()->getPostAdminURL($post_type, $post_id));
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

        $cur->post_status = -2; // forced to pending
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
        $postmedia = new dcPostMedia(dcCore::app());
        $media     = $postmedia->getPostMedia(['post_id' => $post_id]);
        while ($media->fetch()) {
            $postmedia->addPostMedia($return_id, $media->media_id);
        }

        dcPage::addSuccessNotice(__('Entry has been successfully cloned.'));

        // Go to entry edit page
        http::redirect(dcCore::app()->getPostAdminURL($post_type, $return_id, false));
    } catch (Exception $e) {
        dcCore::app()->error->add($e->getMessage());
        http::redirect(dcCore::app()->getPostAdminURL($post_type, $post_id, false));
    }
}

// Next is for admin only
dcPage::check('pages,contentadmin');

// Saving new configuration
if (!empty($_POST['saveconfig'])) {
    try {
        dcCore::app()->blog->settings->addNamespace('cloneentry');

        $ce_active_post = (empty($_POST['active_post'])) ? false : true;
        $ce_active_page = (empty($_POST['active_page'])) ? false : true;
        dcCore::app()->blog->settings->cloneentry->put('ce_active_post', $ce_active_post, 'boolean');
        dcCore::app()->blog->settings->cloneentry->put('ce_active_page', $ce_active_page, 'boolean');
        dcCore::app()->blog->triggerBlog();
        $msg = __('Configuration successfully updated.');
    } catch (Exception $e) {
        dcCore::app()->error->add($e->getMessage());
    }
}
?>
<html>
<head>
  <title><?php echo __('Clone Entry'); ?></title>
</head>

<body>
<?php
echo dcPage::breadcrumb(
    [
        html::escapeHTML(dcCore::app()->blog->name) => '',
        __('Clone Entry')                           => '',
    ]
);
?>

<?php if (!empty($msg)) {
    dcPage::success($msg);
}
?>

<div id="wc_options">
  <form method="post" action="plugin.php">
  <p>
    <?php echo form::checkbox('active_post', 1, $ce_active_post); ?>
    <label class="classic" for="active_post"><?php echo __('Enable Cloning of post for this blog'); ?></label>
  </p>
  <p>
    <?php echo form::checkbox('active_page', 1, $ce_active_page); ?>
    <label class="classic" for="active_page"><?php echo __('Enable Cloning of page for this blog'); ?></label>
  </p>

  <p><input type="hidden" name="p" value="cloneEntry" />
  <?php echo dcCore::app()->formNonce(); ?>
  <input type="submit" name="saveconfig" value="<?php echo __('Save configuration'); ?>" />
  </p>
  </form>
</div>

</body>
</html>
