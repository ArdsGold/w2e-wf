<?php
if (!defined('ABSPATH')) exit;

/**
 * Elementor template helpers.
 *
 * Marker contract
 * ---------------
 * Elementor's Custom Attributes field is written as:
 *
 *     data-customID|h2|repeat
 *
 * The first token is the content type. Remaining tokens are behavior flags.
 *
 * Examples:
 *     data-customID|h1              -> H1 slot
 *     data-customID|h2              -> H2 slot
 *     data-customID|h3              -> H3 slot
 *     data-customID|p               -> paragraph slot
 *     data-customID|h2|repeat      -> repeatable H2 slot (canonical)
 *     data-customID|h2|repeatable  -> legacy spelling accepted for compatibility
 *
 * Elements without a customID marker are never populated by the generator.
 * Legacy marker names remain readable so existing templates continue to work.
 */
class WFEBPG_Template {
    private const LEGACY_TYPES = [
        'h1nonrepeat'       => 'h1',
        'sectiontitlenonrepeat' => 'h2',
        'hnonrepeat'        => 'h3',
        'pnonrepeat'        => 'p',
        'repeatableitem'    => 'repeatable',
        'stepnumber'        => 'step',
    ];

    public static function decode($json) {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid Elementor JSON: ' . json_last_error_msg());
        }

        if (isset($data['content']) && is_array($data['content'])) return $data['content'];
        if (isset($data['elements']) && is_array($data['elements'])) return $data['elements'];
        if (is_array($data) && isset($data[0])) return $data;

        throw new Exception('Unsupported Elementor JSON structure. Expected exported template, content, elements, or raw element array.');
    }

    public static function encode($elements) {
        return wp_json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Return page-level settings from an Elementor JSON export. */
    public static function page_settings($json) {
        $data = json_decode($json, true);
        if (!is_array($data)) return [];
        if (isset($data['page_settings']) && is_array($data['page_settings'])) return $data['page_settings'];
        if (isset($data['settings']) && is_array($data['settings']) && isset($data['elements'])) return $data['settings'];
        return [];
    }

    /**
     * Return the normalized marker definition for an Elementor element.
     *
     * @return array{type:string,flags:array,raw:string}
     */
    public static function marker($el) {
        $raw = self::raw_custom_id($el);
        if ($raw === '') return ['type' => '', 'flags' => [], 'raw' => ''];

        // Be forgiving when a value is copied as "data-customID|h2|repeatable"
        // instead of only the value portion "h2|repeatable".
        if (stripos($raw, 'data-customid|') === 0) {
            $raw = substr($raw, strlen('data-customID|'));
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', $raw)), static function ($part) {
            return $part !== '';
        }));

        if (!$parts) return ['type' => '', 'flags' => [], 'raw' => $raw];

        $type_key = strtolower($parts[0]);
        $type = self::LEGACY_TYPES[$type_key] ?? $type_key;

        $flags = [];
        foreach (array_slice($parts, 1) as $flag) {
            $flag = strtolower(trim($flag));
            if ($flag !== '') $flags[] = $flag;
        }

        // Legacy repeatableItem and the shorter modern |repeat flag both
        // normalize to the canonical repeatable behavior flag.
        if ($type === 'repeatable' || in_array('repeat', $flags, true)) {
            $flags[] = 'repeatable';
            $flags = array_values(array_filter($flags, static function ($flag) {
                return $flag !== 'repeat';
            }));
        }
        $flags = array_values(array_unique($flags));

        return [
            'type' => $type,
            'flags' => $flags,
            'raw' => $raw,
        ];
    }

    /** Backward-compatible helper returning only the normalized marker type. */
    public static function custom_id($el) {
        return self::marker($el)['type'];
    }

    public static function has_flag($el, $flag) {
        $flag = strtolower(trim((string) $flag));
        if ($flag === '') return false;
        return in_array($flag, self::marker($el)['flags'], true);
    }

    public static function is_repeatable($el) {
        return self::has_flag($el, 'repeatable');
    }

    /**
     * Return the ordinal of the nearest parent-heading marker before the first
     * repeatable marker. For H3 repeatables this lets the generator bind the
     * cards to the corresponding H2 section in the DOCX.
     */
    public static function repeatable_scope_parent_ordinal($elements, $parent_level) {
        $ordinal = 0;
        $result = 0;
        self::walk($elements, function ($el) use (&$ordinal, &$result, $parent_level) {
            if ($result) return;
            $marker = self::marker($el);
            if (self::has_flag($el, 'repeatable')) {
                $result = max(1, $ordinal);
                return;
            }
            if ($marker['type'] === 'h' . (int) $parent_level) {
                $ordinal++;
            }
        });
        return $result;
    }

    /** Return all heading levels explicitly marked repeatable in a template. */
    public static function repeatable_heading_levels($elements) {
        $levels = [];
        self::walk($elements, function ($el) use (&$levels) {
            $marker = self::marker($el);
            if (!self::has_flag($el, 'repeatable')) return;
            if (preg_match('/^h([1-9][0-9]*)$/', $marker['type'], $m)) {
                $levels[] = (int) $m[1];
            }
        });
        return array_values(array_unique($levels));
    }

    /** Count normalized marker types in a template. */
    public static function marker_counts($elements) {
        $counts = [];
        self::walk($elements, function ($el) use (&$counts) {
            $type = self::custom_id($el);
            if ($type === '') return;
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        });
        return $counts;
    }

    private static function raw_custom_id($el) {
        $settings = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : [];
        $candidates = [];

        foreach (['customID', 'custom_id', 'data-customID', 'data_customID'] as $key) {
            if (isset($el[$key])) $candidates[] = $el[$key];
            if (isset($settings[$key])) $candidates[] = $settings[$key];
        }

        foreach (['_attributes', 'custom_attributes', 'attributes'] as $key) {
            if (!isset($settings[$key])) continue;

            if (is_string($settings[$key])) {
                $candidates[] = $settings[$key];
            } elseif (is_array($settings[$key])) {
                foreach ($settings[$key] as $attribute => $value) {
                    // Elementor commonly stores attributes as key => value.
                    // Preserve the key when it is the customID attribute.
                    if (strtolower((string) $attribute) === 'data-customid') {
                        $candidates[] = 'data-customID|' . (string) $value;
                    } elseif (is_string($value)) {
                        $candidates[] = $attribute . '|' . $value;
                    } else {
                        $candidates[] = $attribute;
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') continue;

            if (stripos($candidate, 'data-customid|') === 0) {
                return trim(substr($candidate, strlen('data-customID|')));
            }

            if (stripos($candidate, 'data-customid=') === 0) {
                return trim(trim(substr($candidate, strlen('data-customID='))), " \t\"'");
            }

            // If this came from Elementor's key/value representation, the
            // first pipe separates the attribute name from its value.
            if (strpos($candidate, '|') !== false) {
                $parts = explode('|', $candidate, 2);
                if (strtolower(trim($parts[0])) === 'data-customid') return trim($parts[1]);
            }

            return $candidate;
        }

        return '';
    }

    private static function walk($elements, $callback) {
        foreach ((array) $elements as $el) {
            $callback($el);
            if (isset($el['elements']) && is_array($el['elements'])) {
                self::walk($el['elements'], $callback);
            }
        }
    }
}
