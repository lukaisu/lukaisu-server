<?php

/**
 * Feed Edit Text Form Footer View
 *
 * Renders the submit button and JavaScript for the edit text form.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Views
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Views\Feed;

?>
   <input id="markaction" type="submit" value="<?php echo __e('feed.edit_text_footer_save'); ?>" />
   <input type="button" value="<?php echo __e('feed.edit_text_footer_cancel'); ?>"
          data-action="navigate" data-url="/feeds" />
   <input type="hidden" name="checked_feeds_save" value="1" />
   </form>
