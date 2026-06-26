<?php

/**
 * Bulk Save Result View - Shows result after saving bulk translated words
 *
 * Variables expected:
 * - $tid: int - Text ID
 * - $cleanUp: bool - Whether to clean up right frames
 * - $tooltipMode: int - Tooltip display mode (1 = show)
 * - $newWords: array - Array of newly created words with keys:
 *     - id: int - Word ID
 *     - text_lc: string - Lowercase word text
 *     - status: int - Word status
 *     - translation: string - Word translation
 *     - hex: string - Hex class name for CSS
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

namespace Lukaisu\Views\Word;

use Lukaisu\Shared\Infrastructure\Database\Escaping;
use Lukaisu\Shared\UI\Helpers\IconHelper;

// Type assertions for variables passed from controller
assert(is_int($tid));
assert(is_bool($cleanUp));
assert(is_int($tooltipMode));
assert(is_array($newWords));
assert(is_string($todoContent));

?>
<?php
$loadingAlt = __('vocabulary.common.loading');
?>
<p id="displ_message">
    <?php echo IconHelper::render('loader-2', ['class' => 'icon-spin', 'alt' => $loadingAlt]); ?>
    <?= __('vocabulary.result.updating_texts') ?>
</p>

<script type="application/json" data-lukaisu-bulk-save-result-config>
<?php echo json_encode([
    'words' => $newWords,
    'useTooltip' => ($tooltipMode == 1),
    'cleanUp' => $cleanUp,
    'todoContent' => $todoContent
], JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
