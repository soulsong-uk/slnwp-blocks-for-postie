<?php

namespace HtmlMdBlocksForPostie;

defined('ABSPATH') || exit;

/**
 * Two toggles - Markdown parsing and HTML-to-blocks normalization can each
 * be switched off independently. Everything else (which tags map to which
 * blocks, the core/html fallback) is hardcoded rather than configurable, to
 * keep scope tight. All user-facing copy rendered here is centralized in
 * Strings.php, not written literally in this file - edit copy there.
 */
class Settings
{
    private const OPTION = 'html_md_blocks_for_postie_settings';

    /**
     * The plugin's most recent prior option name, from when it was named
     * "Postie Blocks Addon" (slug postie-blocks-addon). migrateOldOption()
     * carries a site's already-saved toggle state forward under the new
     * name so the rename doesn't silently reset it to defaults.
     */
    private const LEGACY_OPTION = 'postie_blocks_settings';

    /**
     * The plugin's original option name, one rename further back, from
     * when it was named "Postie Markdown Blocks" (slug postie-md-plugin).
     * Only consulted if LEGACY_OPTION itself is also missing.
     */
    private const LEGACY_OPTION_OLDEST = 'postie_md_settings';

    public static function isMarkdownEnabled(): bool
    {
        return self::flag('markdown_enabled');
    }

    /**
     * Master switch for HtmlToBlocks - the pass that turns final HTML
     * (Markdown-converted or original) into real Gutenberg block markup.
     * Off means Postie's content is left exactly as Postie itself produced
     * it (or as Markdown-converted semantic HTML, if that ran) - no
     * <!-- wp:x --> block comments get added at all.
     */
    public static function isHtmlEnabled(): bool
    {
        return self::flag('html_enabled');
    }

    /**
     * Whether HtmlToBlocks should drop a paragraph chunk that is nothing but
     * a non-breaking-space placeholder - see
     * HtmlToBlocks::isWhitespacePlaceholder(). Defaults off, unlike the
     * other two flags: a lone "&nbsp;" is ordinarily a genuine spacer
     * paragraph a sender left on purpose, so stripping it is opt-in per
     * account, not the default for everyone.
     */
    public static function isStripEmptyParagraphsEnabled(): bool
    {
        return self::flag('strip_empty_paragraphs', false);
    }

    private static function flag(string $key, bool $default = true): bool
    {
        $settings = get_option(self::OPTION, null);
        if ($settings === null) {
            $settings = self::migrateOldOption();
        }
        if (!is_array($settings) || !array_key_exists($key, $settings)) {
            return $default;
        }
        return (bool) $settings[$key];
    }

    /**
     * Runs in any context (admin, cron, the "?postie=get-mail" front-end
     * trigger) - not just admin_init - since Settings::isMarkdownEnabled()/
     * isHtmlEnabled() are called from Hooks::onPostBefore() during normal
     * email processing, which happens outside is_admin() entirely. Safe to
     * call repeatedly: only ever writes once, when the new option doesn't
     * exist yet but the legacy one does; every call after that finds the
     * new option already set and returns immediately via the caller's own
     * get_option() check, without reaching this method again.
     *
     * @return array<string,mixed>|null
     */
    private static function migrateOldOption(): ?array
    {
        $old = get_option(self::LEGACY_OPTION, null);
        if (!is_array($old)) {
            $old = get_option(self::LEGACY_OPTION_OLDEST, null);
        }
        if (!is_array($old)) {
            return null;
        }
        update_option(self::OPTION, $old);
        return $old;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSetting']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenuPage(): void
    {
        // Submenu under Postie's own top-level menu (registered by Postie
        // itself with slug "postie-settings") rather than a new top-level
        // entry, so this addon reads as part of Postie's settings surface.
        add_submenu_page(
            'postie-settings',
            Strings::get('menu_title'),
            Strings::get('menu_title'),
            'manage_options',
            'html-md-blocks-for-postie',
            [$this, 'renderPage']
        );
    }

    public function registerSetting(): void
    {
        register_setting('html_md_blocks_for_postie_settings_group', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => ['markdown_enabled' => true, 'html_enabled' => true, 'strip_empty_paragraphs' => false],
        ]);
    }

    /**
     * @param mixed $input
     * @return array{markdown_enabled: bool, html_enabled: bool, strip_empty_paragraphs: bool}
     */
    public function sanitize($input): array
    {
        return [
            'markdown_enabled'       => !empty($input['markdown_enabled']),
            'html_enabled'           => !empty($input['html_enabled']),
            'strip_empty_paragraphs' => !empty($input['strip_empty_paragraphs']),
        ];
    }

    public function enqueueAssets(): void
    {
        // Checked via $_GET['page'] rather than the admin_enqueue_scripts
        // $hook_suffix argument - that suffix is derived from the parent
        // menu's slug ("postie-settings_page_html-md-blocks-for-postie")
        // which is easy to get subtly wrong; the page slug itself is a
        // stable target.
        if (!isset($_GET['page']) || $_GET['page'] !== 'html-md-blocks-for-postie') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        wp_enqueue_style(
            'html-md-blocks-for-postie-admin',
            HTML_MD_BLOCKS_FOR_POSTIE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            HTML_MD_BLOCKS_FOR_POSTIE_VERSION
        );
    }

    /**
     * True when Postie's own "Preferred Text Type" account setting is set
     * to Plain - read directly from Postie's own option rather than
     * anything this plugin stores, since it's Postie's setting, not ours.
     * When plain text is preferred, Postie hands this plugin genuine plain
     * text with no HTML at all outside an explicit <md> block, so
     * HtmlToBlocks's alignment/heading detection (which only ever reads
     * real HTML elements/attributes - see detectAlign()) has nothing to
     * detect: real headings and alignment from the sender's own rich-text
     * formatting are silently unavailable, not a bug in this plugin's own
     * conversion. Markdown headings (the "#" syntax inside <md>) are
     * unaffected either way, since MarkdownConverter parses the sender's
     * original decoded text directly - only genuine HTML-sourced formatting
     * is at risk.
     */
    private function postiePrefersPlainText(): bool
    {
        $postieSettings = get_option('postie-settings', []);
        if (!is_array($postieSettings)) {
            return false;
        }
        return strtolower((string) ($postieSettings['prefer_text_type'] ?? '')) === 'plain';
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $markdownEnabled       = self::isMarkdownEnabled();
        $htmlEnabled           = self::isHtmlEnabled();
        $stripEmptyParagraphs  = self::isStripEmptyParagraphsEnabled();
        ?>
        <div class="wrap html-md-blocks-for-postie-settings-wrap">
            <h1><?php echo esc_html(Strings::get('page_heading')); ?></h1>
            <?php if ($this->postiePrefersPlainText()) : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html(Strings::get('plain_text_warning')); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('html_md_blocks_for_postie_settings_group'); ?>
                <div class="html-md-blocks-for-postie-card">
                    <div class="html-md-blocks-for-postie-subsection">
                        <p class="html-md-blocks-for-postie-field-heading"><?php echo esc_html(Strings::get('content_conversion_heading')); ?></p>
                        <label>
                            <input type="checkbox" name="html_md_blocks_for_postie_settings[markdown_enabled]" value="1" <?php checked($markdownEnabled); ?> />
                            <?php echo esc_html(Strings::get('markdown_toggle_label')); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html(Strings::get('markdown_toggle_desc')); ?>
                        </p>
                        <p class="description">
                            <?php echo esc_html(Strings::get('markdown_toggle_desc_outside')); ?>
                        </p>
                        <p class="description">
                            <strong><?php echo esc_html(Strings::get('markdown_toggle_example_label')); ?></strong>
                        </p>
                        <pre class="html-md-blocks-for-postie-example"><code><?php echo esc_html(Strings::get('markdown_toggle_example')); ?></code></pre>
                        <label>
                            <input type="checkbox" name="html_md_blocks_for_postie_settings[html_enabled]" value="1" <?php checked($htmlEnabled); ?> />
                            <?php echo esc_html(Strings::get('html_toggle_label')); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html(Strings::get('html_toggle_desc')); ?>
                        </p>
                        <label>
                            <input type="checkbox" name="html_md_blocks_for_postie_settings[strip_empty_paragraphs]" value="1" <?php checked($stripEmptyParagraphs); ?> />
                            <?php echo esc_html(Strings::get('strip_empty_paragraphs_toggle_label')); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html(Strings::get('strip_empty_paragraphs_toggle_desc')); ?>
                        </p>
                    </div>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
