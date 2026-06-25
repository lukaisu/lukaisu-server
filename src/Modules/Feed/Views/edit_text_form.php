<?php

/**
 * Feed Edit Text Form View
 *
 * Renders the form for editing feed items before creating texts.
 *
 * Variables expected:
 * - $texts: array of text data (title, text, source_uri, audio_uri)
 * - $row: array feed link and feed data (language_id, id)
 * - $count: int starting form counter (passed by reference)
 * - $tagName: string tag name for the text
 * - $nfId: int feed ID
 * - $maxTexts: int maximum texts setting
 * - $languages: array of language records
 * - $scrdir: string script direction HTML attribute
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Views
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Views\Feed;

use Lukaisu\Shared\UI\Helpers\IconHelper;

// View variables - assert types for Psalm
assert(is_array($texts));
assert(is_array($row) && isset($row['language_id']) && isset($row['id']));
assert(is_int($count));
assert(is_string($tagName));
assert(is_int($nfId));
assert(is_int($maxTexts));
assert(is_array($languages));
assert(is_string($scrdir));

/**
 * @var array<int, array{title: string, text: string, source_uri: string, audio_uri: string}> $texts
 * @var array{language_id: int, id: int} $row
 * @var int $count
 * @var string $tagName
 * @var int $nfId
 * @var int $maxTexts
 * @var array<int, array{LgID: int, LgName: string}> $languages
 * @var string $scrdir
 */

foreach ($texts as $text) :
    /** @var array{title: string, text: string, source_uri: string, audio_uri: string} $text */
    ?>
<div class="box mb-4" x-data="{ isSelected: true }">
    <!-- Header with checkbox and title -->
    <div class="field is-horizontal">
        <div class="field-label is-normal">
            <label class="checkbox">
                <input class="markcheck"
                       type="checkbox"
                       name="Nf_count[<?php echo $count; ?>]"
                       value="<?php echo $count; ?>"
                       checked
                       x-model="isSelected" />
            </label>
        </div>
        <div class="field-body">
            <div class="field has-addons">
                <div class="control is-expanded">
                    <input type="text"
                           class="input notempty"
                           name="feed[<?php echo $count; ?>][title]"
                           value="<?php echo htmlspecialchars($text['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           maxlength="200"
                           placeholder="<?php echo __e('feed.edit_text_form_title_placeholder'); ?>"
                           :disabled="!isSelected"
                           required />
                </div>
                <div class="control">
                    <span class="icon has-text-danger" title="<?php echo __e('feed.edit_text_form_field_required'); ?>">
                        <?php echo IconHelper::render('asterisk', ['alt' => __('feed.edit_text_form_required_alt')]); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div x-show="isSelected" x-transition x-cloak>
        <!-- Language -->
        <div class="field is-horizontal">
            <div class="field-label is-normal">
                <label class="label"><?php echo __e('feed.edit_text_form_language'); ?></label>
            </div>
            <div class="field-body">
                <div class="field">
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="feed[<?php echo $count; ?>][language_id]" class="notempty setfocus">
                                <?php
                                foreach ($languages as $rowLang) :
                                    /** @var array{LgID: int, LgName: string} $rowLang */
                                    ?>
                                <option value="<?php echo $rowLang['LgID']; ?>"<?php
                                if ($row['language_id'] === $rowLang['LgID']) {
                                    echo ' selected';
                                }
                                ?>><?php echo htmlspecialchars($rowLang['LgName'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Text Content -->
        <div class="field is-horizontal">
            <div class="field-label is-normal">
                <label class="label"><?php echo __e('feed.edit_text_form_text'); ?></label>
            </div>
            <div class="field-body">
                <div class="field has-addons">
                    <div class="control is-expanded">
                        <textarea <?php echo $scrdir; ?>
                                  name="feed[<?php echo $count; ?>][text]"
                                  class="textarea notempty checkbytes"
                                  rows="12"
                                  required><?php
                                      echo htmlspecialchars($text['text'] ?? '', ENT_QUOTES, 'UTF-8');
                                    ?></textarea>
                    </div>
                    <div class="control">
                        <span class="icon has-text-danger" title="Field must not be empty">
                            <?php echo IconHelper::render('asterisk', ['alt' => 'Required']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Source URI -->
        <div class="field is-horizontal">
            <div class="field-label is-normal">
                <label class="label"><?php echo __e('feed.edit_text_form_source_uri'); ?></label>
            </div>
            <div class="field-body">
                <div class="field">
                    <div class="control">
                        <input type="url"
                               class="input checkurl"
                               name="feed[<?php echo $count; ?>][source_uri]"
                               value="<?php echo htmlspecialchars($text['source_uri'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               maxlength="1000"
                               placeholder="https://..." />
                    </div>
                </div>
            </div>
        </div>

        <!-- Tags -->
        <div class="field is-horizontal">
            <div class="field-label is-normal">
                <label class="label"><?php echo __e('feed.edit_text_form_tags'); ?></label>
            </div>
            <div class="field-body">
                <div class="field">
                    <div class="control">
                        <div class="tags">
                            <span class="tag is-info is-light"><?php
                                echo htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8');
                            ?></span>
                        </div>
                        <input type="hidden"
                               name="feed[<?php echo $count; ?>][Nf_ID]"
                               value="<?php echo $nfId; ?>" />
                        <input type="hidden"
                               name="feed[<?php echo $count; ?>][Nf_Max_Texts]"
                               value="<?php echo $maxTexts; ?>" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Audio URI -->
        <div class="field is-horizontal">
            <div class="field-label is-normal">
                <label class="label"><?php echo __e('feed.edit_text_form_audio_uri'); ?></label>
            </div>
            <div class="field-body">
                <div class="field">
                    <div class="control">
                        <input type="text"
                               class="input"
                               name="feed[<?php echo $count; ?>][audio_uri]"
                               value="<?php echo htmlspecialchars($text['audio_uri'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               maxlength="200"
                               placeholder="<?php echo __e('feed.edit_text_form_audio_placeholder'); ?>" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Collapsed state indicator -->
    <div x-show="!isSelected" x-transition class="has-text-grey-light is-italic">
        <span class="icon is-small">
            <?php echo IconHelper::render('eye-off', ['alt' => __('feed.edit_text_form_hidden_alt')]); ?>
        </span>
        <?php echo __e('feed.edit_text_form_deselected'); ?>
    </div>
</div>
    <?php
    $count++;
endforeach;
