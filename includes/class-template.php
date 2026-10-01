<?php
/**
 * Elementor JSON parsing and marker helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WFEBPG_Template {
    public static function decode($json) {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid Elementor JSON: ' . json_last_error_msg());
        }

        if (isset($data['content']) && is_array($data['content'])) {
            return $data['content'];
        }
        if (isset($data['elements']) && is_array($data['elements'])) {
            return $data['elements'];
        }
        if (is_array($data) && isset($data[0])) {
            return $data;
        }

        throw new Exception('Unsupported Elementor JSON structure. Expected exported template, content, elements, or raw element array.');
    }

    public static function encode($elements) {
        return wp_json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Return page-level settings from an Elementor JSON export. */
    public static function page_settings($json) {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['page_settings']) && is_array($data['page_settings'])) {
            return $data['page_settings'];
        }
        if (isset($data['settings']) && is_array($data['settings']) && isset($data['elements'])) {
            return $data['settings'];
        }
        return [];
    }

    /**
     * Normalize legacy marker names to the current human-readable vocabulary.
     * Existing Elementor templates continue to work without being rewritten.
     */
    /**
     * Legacy Elementor marker aliases retained for existing templates.
     * New templates should use the short canonical marker names.
     */
    private const LEGACY_MARKER_ALIASES = [
        'h1NonRepeat' => 'h1',
        'sectionTitleNonRepeat' => 'h2',
        'hNonRepeat' => 'h3',
        'pNonRepeat' => 'p',
        'repeatableItem' => 'repeat',
        'stepNumber' => 'step',
    ];

    private static function normalize_marker($marker) {
        return isset(self::LEGACY_MARKER_ALIASES[$marker])
            ? self::LEGACY_MARKER_ALIASES[$marker]
            : $marker;
    }

    /**
     * Elementor stores the custom HTML attribute in settings as:
     * data-customID|h1
     *
     * We also accept common normalized forms so the plugin remains flexible.
     */
    public static function custom_id($el) {
        $settings = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : [];
        $candidates = [];

        foreach (['customID', 'custom_id', 'data-customID', 'data_customID'] as $key) {
            if (isset($el[$key])) {
                $candidates[] = $el[$key];
            }
            if (isset($settings[$key])) {
                $candidates[] = $settings[$key];
            }
        }

        if (isset($settings['_attributes']) && is_string($settings['_attributes'])) {
            $candidates[] = $settings['_attributes'];
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            if (strpos($candidate, '|') !== false) {
                $parts = explode('|', $candidate, 2);
                return self::normalize_marker(trim($parts[1]));
            }
            if (stripos($candidate, 'data-customID=') === 0) {
                return self::normalize_marker(trim(trim(substr($candidate, 14)), " \t\"'"));
            }
            return self::normalize_marker($candidate);
        }

        return '';
    }
}
