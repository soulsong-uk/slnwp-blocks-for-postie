<?php

namespace HtmlMdBlocksForPostie;

defined('ABSPATH') || exit;

/**
 * Single editable source for this plugin's user-facing PHP-rendered
 * strings - one place to find and change copy instead of hunting across
 * Settings.php and the bootstrap file. This is additive, not a replacement
 * for WordPress i18n. No JS in this plugin currently renders user-facing
 * text (it's a server-rendered admin settings page), so there is no
 * companion strings.js.
 *
 * Each string is wrapped in its own literal __() call right here, rather
 * than routed through one shared __($variable, ...) call in get() - WP.org
 * Plugin Check's WordPress.WP.I18n.NonSingularStringLiteralText sniff (and
 * WordPress's own .pot-generation tooling) require __()'s first argument to
 * be a literal string at the call site, not a variable/array lookup, since
 * both work by statically scanning source rather than executing it.
 */
class Strings
{
    public static function get(string $key): string
    {
        return self::all()[$key] ?? $key;
    }

    /**
     * @return array<string,string>
     */
    private static function all(): array
    {
        return [
            // Bootstrap (slnwp-blocks-for-postie.php) - missing-dependency admin notice
            'requires_postie_notice' => __('SLNWP Html and MD Blocks for Postie requires the Postie plugin to be installed and active - it has no effect on its own.', 'slnwp-blocks-for-postie'),

            // Settings page (src/Settings.php) - menu + page heading
            'menu_title'   => __('HTML & Markdown Blocks', 'slnwp-blocks-for-postie'),
            'page_heading' => __('SLNWP Html and MD Blocks for Postie', 'slnwp-blocks-for-postie'),

            // Settings page - warning notice, shown only when Postie's own
            // "Preferred Text Type" account setting is Plain
            'plain_text_warning' => __('Postie\'s "Preferred Text Type" is set to Plain. Headings and text alignment can only be detected in real HTML email content, so that formatting will be lost for anything outside <md> tags. For best results, set Postie\'s "Preferred Text Type" to HTML.', 'slnwp-blocks-for-postie'),

            // Settings page - Content Conversion subsection
            'content_conversion_heading'    => __('Content Conversion', 'slnwp-blocks-for-postie'),
            'markdown_toggle_label'         => __('Convert Markdown to Gutenberg Blocks', 'slnwp-blocks-for-postie'),
            'markdown_toggle_desc'          => __('Wrap any part of your email in <md> and </md> tags to turn it into nicely styled content — headings, bold, italic, links, images, and lists all get formatted automatically.', 'slnwp-blocks-for-postie'),
            'markdown_toggle_desc_outside'  => __('Anything outside the <md> tags stays as plain text, exactly as written.', 'slnwp-blocks-for-postie'),
            'markdown_toggle_example_label' => __('Example:', 'slnwp-blocks-for-postie'),
            /* translators: this is a literal <md> Markdown syntax example shown verbatim in a <pre> block, not prose - kept translatable in case a locale wants to show a translated sample. */
            'markdown_toggle_example'       => __("<md>\n# Welcome!\nThis is **bold** and this is *italic*.\n- Item one\n- Item two\n</md>", 'slnwp-blocks-for-postie'),
            'html_toggle_label' => __('Convert content into native Gutenberg blocks', 'slnwp-blocks-for-postie'),
            'html_toggle_desc'  => __('Wraps the finished content - including any Markdown conversion above - in real Gutenberg block markup (paragraphs, headings, images, lists, quotes), so the post opens as separate, individually editable blocks instead of one Classic/HTML block.', 'slnwp-blocks-for-postie'),
            'strip_empty_paragraphs_toggle_label' => __('Remove empty paragraph blocks', 'slnwp-blocks-for-postie'),
            'strip_empty_paragraphs_toggle_desc'  => __('Some email apps insert a blank paragraph for every empty line you leave in your message. Turn this on to leave those out of the published post, instead of keeping them as empty-looking blocks.', 'slnwp-blocks-for-postie'),
        ];
    }
}
