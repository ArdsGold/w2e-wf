<?php
if (!defined('ABSPATH')) exit;

/**
 * Safely converts supported US phone-number text into tel: links.
 *
 * Important: only text nodes are processed. HTML tags/attributes are left
 * untouched, and existing anchors are never processed again. This prevents
 * generated href attributes from being turned into nested/corrupted links.
 */
class WFEBPG_Phone_Linker {
    private const PHONE_PATTERN = '/(?:\+1\s*\(\d{3}\)\s*\d{3}[-\s]\d{4}|\+1[-\s]\d{3}[-\s]\d{3}[-\s]\d{4}|\(\d{3}\)\s*\d{3}[-\s]\d{4}|\b\d{3}[-\s]\d{3}[-\s]\d{4}\b|\+1\s*\d{3}\s*\d{3}\s*\d{4}|\+1\d{10}\b|\b\d{10}\b)/';

    /** Native Elementor links wrap their text when rendered; do not nest anchors. */
    public static function preserve_widget($element) {
        $settings = $element['settings'] ?? [];
        if (!is_array($settings)) return false;
        $destinations = [];
        foreach ($settings as $key => $value) {
            $url = is_array($value) ? ($value['url'] ?? '')
                : (preg_match('/(?:link|url|href)$/i', $key) ? $value : '');
            if (is_string($url) && preg_match('/^tel:/i', trim($url))) $destinations[] = trim($url);
        }
        if (!$destinations) return false;
        if (preg_match('/(?:^|[-_])button$/i', $element['widgetType'] ?? '')) return true;

        $numbers = [];
        foreach ($settings as $key => $value) {
            if (!is_string($value) || preg_match('/^_|^wfebpg_|link|url|href|attributes|customid/i', $key)) continue;
            if (preg_match('/^(?:https?:|tel:|mailto:)/i', trim($value))) continue;
            $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = str_replace("\xc2\xa0", ' ', $text);
            preg_match_all(self::PHONE_PATTERN, $text, $matches);
            foreach ($matches[0] as $match) $numbers[] = self::normalize_number($match);
        }
        foreach ($destinations as $url) {
            $target = preg_split('/[;?]/', rawurldecode(html_entity_decode(substr($url, 4), ENT_QUOTES | ENT_HTML5, 'UTF-8')))[0];
            $phone = self::normalize_number($target);
            if ($phone !== '' && in_array($phone, $numbers, true)) return true;
        }
        return false;
    }

    private static function normalize_number($text) {
        $digits = preg_replace('/\D+/', '', $text);
        if (strlen($digits) === 11 && $digits[0] === '1') return '+' . $digits;
        if (strlen($digits) === 10) return '+1' . $digits;
        return '';
    }

    public static function html($html) {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        // Split HTML into tags and text. We only modify text portions.
        $parts = preg_split('/(<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        $in_anchor = false;
        $in_raw_text = false;

        foreach ($parts as &$part) {
            if ($part === '') {
                continue;
            }

            // HTML tag: never alter attributes or tag contents.
            if ($part[0] === '<') {
                if (preg_match('/^<\s*a\b/i', $part)) {
                    $in_anchor = true;
                } elseif (preg_match('/^<\s*\/\s*a\s*>/i', $part)) {
                    $in_anchor = false;
                }

                if (preg_match('/^<\s*(script|style)\b/i', $part)) {
                    $in_raw_text = true;
                } elseif (preg_match('/^<\s*\/\s*(script|style)\s*>/i', $part)) {
                    $in_raw_text = false;
                }

                continue;
            }

            // Do not process text inside an existing link, script, or style.
            if ($in_anchor || $in_raw_text) {
                continue;
            }

            $part = self::link_text($part);
        }
        unset($part);

        return implode('', $parts);
    }

    private static function link_text($text) {
        // Supported formats:
        // +1 (XXX) XXX-XXXX
        // +1-XXX-XXX-XXXX
        // (XXX) XXX-XXXX
        // XXX-XXX-XXXX
        // XXXXXXXXXX
        // +1XXXXXXXXXX
        // +1 XXX XXX XXXX
        // Use ONE callback pass so the <a> markup we create can never be
        // matched again by another phone-number pattern.
        $text = preg_replace_callback(self::PHONE_PATTERN, function ($m) {
            $display = $m[0];
            $phone = self::normalize_number($display);
            if ($phone === '') return $display;

            return '<a href="tel:' . esc_attr($phone) . '" style="color: inherit !important; text-decoration: underline !important;">' . $display . '</a>';
        }, $text);

        return $text;
    }
}
