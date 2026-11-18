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
 * Elementor Free alternatives: Advanced > Wolf Forge Marker (h3|link),
 * or CSS Classes (wf-h3 wf-link). All formats share the same marker parser.
 * Elements without a recognized marker are never populated by the generator.
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
        } elseif (stripos($raw, 'data-customid=') === 0) {
            $raw = trim(substr($raw, strlen('data-customID=')), " \t\"'");
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

        // Legacy repeatableItem, the shorter |repeat flag, and the r2.2.0
        // structural marker data-customID|repeat all normalize to the same
        // repeatable behavior flag. Keep the structural type as `repeat` so
        // the generator can distinguish a whole-element repeat boundary from
        // an h3|repeat content marker.
        if ($type === 'repeatable' || $type === 'repeat' || in_array('repeat', $flags, true)) {
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

    /** Field first, legacy attributes second, prefixed CSS classes last. */
    private static function raw_custom_id($el) {
        $settings = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : [];
        $field = $settings['wfebpg_marker'] ?? '';
        if (is_string($field) && trim($field) !== '') return trim($field);

        foreach (['customID', 'custom_id', 'data-customID', 'data_customID'] as $key) {
            foreach ([$el[$key] ?? '', $settings[$key] ?? ''] as $value) {
                if (is_string($value) && trim($value) !== '') return trim($value);
            }
        }
        foreach (['_attributes', 'custom_attributes', 'attributes'] as $key) {
            $value = $settings[$key] ?? '';
            if (is_array($value)) {
                $lines = [];
                foreach ($value as $attribute => $content) {
                    if (!is_string($content)) continue;
                    $lines[] = strtolower((string) $attribute) === 'data-customid'
                        ? 'data-customID|' . $content : $content;
                }
                $value = implode("\n", $lines);
            }
            if (!is_string($value)) continue;
            foreach (preg_split('/\R/', $value) as $line) {
                if (preg_match('/^\s*data-customid[|=](.*)$/i', $line, $match)) {
                    $raw = trim($match[1], " \t\"'");
                    if ($raw !== '') return $raw;
                }
            }
            // Older JSON may store the value without its attribute name.
            $first = strtolower(trim(explode('|', trim($value))[0]));
            if (self::marker_token($first)) return trim($value);
        }

        $tokens = [];
        foreach (['_css_classes', 'css_classes'] as $key) {
            if (!isset($settings[$key]) || !is_string($settings[$key])) continue;
            foreach (preg_split('/\s+/', trim($settings[$key])) as $class) {
                if (self::css_marker_class($class)) $tokens[] = strtolower(substr($class, 3));
            }
        }
        $tokens = array_values(array_unique($tokens));
        if (!$tokens) return '';
        // Heading/content tokens win over modifiers, regardless of class order.
        $primary = null;
        foreach ($tokens as $token) {
            $type = self::LEGACY_TYPES[$token] ?? $token;
            if (preg_match('/^h[1-9][0-9]*$/', $type) || $type === 'step') { $primary = $token; break; }
        }
        foreach (['p', 'repeat', 'repeatable', 'link'] as $type) {
            if ($primary !== null) break;
            foreach ($tokens as $token) {
                if ((self::LEGACY_TYPES[$token] ?? $token) === $type) { $primary = $token; break; }
            }
        }
        return implode('|', array_merge([$primary], array_values(array_diff($tokens, [$primary]))));
    }

    private static function marker_token($token) {
        return preg_match('/^h[1-9][0-9]*$/', $token)
            || in_array($token, ['p', 'step', 'repeat', 'repeatable', 'link'], true)
            || isset(self::LEGACY_TYPES[$token]);
    }

    private static function css_marker_class($class) {
        return stripos($class, 'wf-') === 0 && self::marker_token(strtolower(substr($class, 3)));
    }

    /** Clear every marker source after consuming an inline repeat; retain styling. */
    public static function clear_marker(&$element) {
        if (!isset($element['settings']) || !is_array($element['settings'])) return;
        unset($element['settings']['wfebpg_marker']);
        foreach (['customID', 'custom_id', 'data-customID', 'data_customID'] as $key) {
            unset($element[$key], $element['settings'][$key]);
        }
        foreach (['_css_classes', 'css_classes'] as $key) {
            if (!isset($element['settings'][$key]) || !is_string($element['settings'][$key])) continue;
            $classes = preg_split('/\s+/', trim($element['settings'][$key]));
            $element['settings'][$key] = implode(' ', array_filter($classes, static function ($class) {
                return !self::css_marker_class($class);
            }));
        }
        foreach (['_attributes', 'custom_attributes', 'attributes'] as $key) {
            if (!isset($element['settings'][$key])) continue;
            $value = $element['settings'][$key];
            if (is_string($value)) {
                $lines = array_filter(preg_split('/\R/', $value), static function ($line) {
                    $first = strtolower(trim(explode('|', trim($line))[0]));
                    return !preg_match('/^\s*data-customid[|=]/i', $line) && !self::marker_token($first);
                });
                if ($lines) $element['settings'][$key] = implode("\n", $lines);
                else unset($element['settings'][$key]);
            } elseif (is_array($value)) {
                foreach ($value as $attribute => $content) {
                    if (strtolower((string) $attribute) === 'data-customid'
                        || (is_string($content) && preg_match('/^\s*data-customid[|=]/i', $content))) {
                        unset($element['settings'][$key][$attribute]);
                    }
                }
            }
        }
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
