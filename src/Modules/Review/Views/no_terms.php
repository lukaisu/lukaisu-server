<?php

/**
 * No Terms View - Shows message when no terms available
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

namespace Lukaisu\Views\Review;

?>
<p class="has-text-centered">
    &nbsp;<br /><?php echo \htmlspecialchars(__('review.no_terms'), ENT_QUOTES, 'UTF-8'); ?>
</p>
