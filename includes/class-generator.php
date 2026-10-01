<?php
if (!defined('ABSPATH')) exit;

class WFEBPG_Generator {
    // Public marker names. Legacy names are normalized by WFEBPG_Template.
    const H1_ID = 'h1';
    const SECTION_TITLE_ID = 'h2';
    const H_ID = 'h3';
    const P_ID = 'p';
    const REPEAT_ID = 'repeatable';
    const INTERNAL_REPEAT_WIDGETS = ['toggle'];
    const STEP_ID = 'step';
    const QUEUE_OPTION = 'wfebpg_queue';
    const QUEUE_LOCK_OPTION = 'wfebpg_queue_worker_lock';
    const QUEUE_LOCK_SECONDS = 120;
    const QUEUE_MAX_JOBS = 3;
    const QUEUE_MAX_SECONDS = 25;

    public static function clean_filename($name) {
        // Page titles should preserve special characters from the DOCX filename.
        // Only remove the file extension and normalize whitespace; do not strip
        // punctuation such as apostrophes, &, #, parentheses, hyphens, etc.
        $name = pathinfo($name, PATHINFO_FILENAME);
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    public static function slug($title) { return sanitize_title($title); }

    public static function enqueue($args) {
        $q = get_option(self::QUEUE_OPTION, []);
        $q[] = $args;
        update_option(self::QUEUE_OPTION, $q, false);

        // Schedule a near-immediate single event as well as the recurring
        // worker. This helps the queue start promptly instead of waiting for
        // the next minute tick. WordPress still requires WP-Cron to be
        // triggered by traffic or a real cron request.
        if (!wp_next_scheduled('wfebpg_process_queue')) {
            wp_schedule_single_event(time() + 5, 'wfebpg_process_queue');
        }
    }

    public static function process_queue() {
        // Prevent WP-Cron and the manual "Process Queue Now" action from
        // processing the same job at the same time. add_option() is atomic
        // enough for this single-worker lock on normal WordPress storage.
        $lock_key = self::QUEUE_LOCK_OPTION;
        $locked_at = get_option($lock_key, 0);
        if ($locked_at && (time() - (int) $locked_at) < self::QUEUE_LOCK_SECONDS) return;
        if ($locked_at) delete_option($lock_key);
        if (!add_option($lock_key, time(), '', false)) return;

        try {
            $q = get_option(self::QUEUE_OPTION, []);
            if (!$q) return;

            // Process a small batch per cron request. This is much faster than
        // forcing large queues to wait one full minute per page, while the
        // time guard keeps heavy Elementor jobs from monopolizing the request.
        $started = microtime(true);
        $processed = 0;
        $max_jobs = self::QUEUE_MAX_JOBS;
        $max_seconds = self::QUEUE_MAX_SECONDS;

            while ($q && $processed < $max_jobs && (microtime(true) - $started) < $max_seconds) {
                $job = array_shift($q);
                update_option(self::QUEUE_OPTION, $q, false);

                try {
                    self::generate($job);
                } catch (Throwable $e) {
                    WFEBPG_Logger::log($e->getMessage(), 'error');
                }

                $processed++;
            }
        } finally {
            delete_option($lock_key);
        }
    }

    /**
     * Populate all non-repeat widgets using the custom IDs in the template.
     * Mapping is based on occurrence order in the DOCX:
     * h1 -> Heading 1
     * h2 -> Heading 2
     * h3 -> Heading 3
     * p  -> paragraph/content blocks
     */
    /**
     * Populate non-repeat content according to the semantic order of the DOCX.
     *
     * H1/H2/H3 markers consume their own heading levels. p markers
     * widgets then consume the appropriate block(s), preventing paragraphs
     * from being shifted or duplicated merely because the DOCX contains many
     * headings between paragraph blocks.
     */
    /**
     * Populate non-repeat markers using hierarchical document scope.
     *
     * A heading marker is scoped by the nearest preceding marked heading one
     * level above it: H2 belongs to the preceding H1, H3 belongs to the
     * preceding H2, H4 belongs to the preceding H3, and so on. This prevents
     * an H3 from one document section from being consumed by an H3 slot in a
     * different section.
     */
    private static function populate_nonrepeat(&$elements, $doc, $repeatable_items = []) {
        $blocks = self::build_structured_doc_blocks($doc);
        if (!$blocks) $blocks = self::legacy_nonrepeat_blocks($doc);

        $excluded_indices = [];
        foreach ((array) $repeatable_items as $repeatable_item) {
            if (isset($repeatable_item['source_index'])) {
                $excluded_indices[(int) $repeatable_item['source_index']] = true;
            }
        }

        $heading_cursors = [];
        $template_ordinals = [];
        $active_doc_paths = [];
        $active_blocks = [];

        self::walk_nonrepeat_hierarchy($elements, function (&$el) use (
            &$blocks,
            &$excluded_indices,
            &$heading_cursors,
            &$template_ordinals,
            &$active_doc_paths,
            &$active_blocks
        ) {
            $marker = WFEBPG_Template::marker($el);
            if ($marker['type'] === '' || WFEBPG_Template::is_repeatable($el)) return;

            $settings = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : [];
            $type = $marker['type'];

            // A Toggle is a single Elementor widget that contains many tab
            // items. When its marker requests h3|p, populate the tab repeater
            // from the complete H3/P stream in the current H2 scope instead
            // of treating the Toggle like a single title field.
            if ($type === 'h3'
                && strtolower((string) ($el['widgetType'] ?? '')) === 'toggle'
                && in_array('p', (array) ($marker['flags'] ?? []), true)) {
                $parent_level = 2;
                $parent_ordinal = (int) ($active_doc_paths[2] ?? 0);
                if ($parent_ordinal > 0) {
                    $items = self::build_repeatable_items_for_scope($doc, 3, $parent_level, $parent_ordinal);
                    if ($items) {
                        $tabs = [];
                        foreach ($items as $item) {
                            $tabs[] = [
                                'tab_title' => (string) ($item['heading'] ?? ''),
                                'tab_content' => wpautop(implode("\n\n", (array) ($item['content'] ?? []))),
                                '_id' => self::new_element_id(),
                            ];
                        }
                        $settings['tabs'] = $tabs;
                    }
                }
                $el['settings'] = $settings;
                return;
            }

            if (preg_match('/^h([1-9][0-9]*)$/', $type, $hm)) {
                $level = (int) $hm[1];
                $parent_path = '';
                if ($level > 1 && isset($active_doc_paths[$level - 1])) {
                    $parent_path = (string) $active_doc_paths[$level - 1];
                }

                // A template heading occurrence advances only within its own
                // parent scope. Deeper template heading ordinals are reset when
                // a new parent heading is encountered.
                for ($i = $level + 1; $i <= 9; $i++) {
                    unset($template_ordinals[$i], $active_doc_paths[$i], $active_blocks[$i]);
                }
                $template_ordinals[$level] = (int) ($template_ordinals[$level] ?? 0) + 1;

                $cursor_key = $level . '|' . $parent_path;
                $block_index = null;
                $block = self::next_structured_block_in_scope(
                    $blocks,
                    $heading_cursors,
                    $level,
                    $parent_path,
                    $excluded_indices,
                    $block_index
                );

                if ($block !== null) {
                    self::set_widget_title($settings, (string) $block['heading']);

                    // A combined marker such as data-customID|h3|p means this
                    // single widget owns both the heading and the paragraphs
                    // associated with that DOCX heading. Previously the non-
                    // repeat path handled only the h3 portion, leaving fields
                    // such as an Icon Box description_text at their template
                    // default (for example, "Test"). Reuse the generic text
                    // setter so Icon Box, Text Editor, and other supported
                    // Elementor text fields receive the same associated content.
                    if (in_array('p', $marker['flags'], true) || in_array($type, ['h1', 'h2', 'h3'], true)) {
                        self::set_widget_marker_content(
                            $settings,
                            $marker,
                            (string) $block['heading'],
                            implode("\n\n", (array) $block['content'])
                        );
                    }

                    $active_doc_paths[$level] = (string) ($block['path'] ?? '');
                    $active_blocks[$level] = $block;
                    $active_blocks['latest'] = $block;
                } else {
                    // Keep the scope marker even when the DOCX has no matching
                    // heading. Crucially, clear the active block too: a P marker
                    // after an unmatched heading must never inherit content from
                    // the previous section.
                    $active_doc_paths[$level] = $parent_path !== ''
                        ? $parent_path . '.' . $template_ordinals[$level]
                        : (string) $template_ordinals[$level];
                    $active_blocks[$level] = null;
                    $active_blocks['latest'] = null;
                }

                $el['settings'] = $settings;
                return;
            }

            if ($type === self::P_ID) {
                $block = $active_blocks['latest'] ?? null;
                if ($block !== null && !empty($block['content'])) {
                    self::set_widget_text($settings, implode("\n\n", (array) $block['content']));
                }
                $el['settings'] = $settings;
            }
        });
    }

    /**
     * Walk template elements in visual/document order while retaining the
     * same global preceding-heading context across nested Elementor nodes.
     */
    private static function walk_nonrepeat_hierarchy(&$elements, $callback) {
        foreach ($elements as &$el) {
            $callback($el);
            if (isset($el['elements']) && is_array($el['elements'])) {
                self::walk_nonrepeat_hierarchy($el['elements'], $callback);
            }
        }
        unset($el);
    }

    /** Build heading-aware DOCX blocks with a hierarchical path such as 1.2.3. */
    private static function build_structured_doc_blocks($doc) {
        $blocks = [];
        $ordinals = array_fill(1, 9, 0);
        $current = null;

        foreach (($doc['items'] ?? []) as $source_index => $item) {
            if (!empty($item['repeatable'])) {
                if ($current !== null) {
                    $blocks[] = $current;
                    $current = null;
                }
                continue;
            }

            if (!empty($item['heading'])) {
                if ($current !== null) $blocks[] = $current;

                $level = max(1, (int) ($item['heading_level'] ?? 0));
                for ($i = $level + 1; $i <= 9; $i++) $ordinals[$i] = 0;
                $ordinals[$level]++;

                $path_parts = [];
                for ($i = 1; $i <= $level; $i++) {
                    if ($ordinals[$i] > 0) $path_parts[] = (string) $ordinals[$i];
                }

                $current = [
                    'heading' => (string) $item['text'],
                    'heading_level' => $level,
                    'content' => [],
                    'source_index' => (int) $source_index,
                    'path' => implode('.', $path_parts),
                    'parent_path' => $level > 1 ? implode('.', array_slice($path_parts, 0, -1)) : '',
                ];
            } elseif ($current !== null) {
                $current['content'][] = (string) $item['text'];
            }
        }

        if ($current !== null) $blocks[] = $current;
        return $blocks;
    }

    /** Find the next heading in the requested level/parent scope. */
    private static function next_structured_block_in_scope(&$blocks, &$cursors, $level, $parent_path, $excluded_indices, &$found_index = null) {
        $key = (int) $level . '|' . (string) $parent_path;
        $cursor = (int) ($cursors[$key] ?? 0);

        foreach ($blocks as $i => $block) {
            if ($i < $cursor) continue;
            if ((int) ($block['heading_level'] ?? 0) !== (int) $level) continue;
            if ((string) ($block['parent_path'] ?? '') !== (string) $parent_path) continue;
            if (isset($excluded_indices[(int) ($block['source_index'] ?? -1)])) continue;

            $cursors[$key] = $i + 1;
            $found_index = $i;
            return $block;
        }

        $cursors[$key] = count($blocks);
        return null;
    }

    private static function legacy_nonrepeat_blocks($doc) {
        $blocks = [];
        $current = null;
        foreach (($doc['items'] ?? []) as $doc_index => $item) {
            if (!empty($item['repeatable'])) continue;
            if (!empty($item['heading'])) {
                if ($current !== null) $blocks[] = $current;
                $current = ['heading' => $item['text'], 'content' => []];
            } elseif ($current !== null) {
                $current['content'][] = $item['text'];
            }
        }
        if ($current !== null) $blocks[] = $current;
        return $blocks;
    }

    private static function next_block(&$blocks, &$cursor) {
        if ($cursor >= count($blocks)) return null;
        $block = $blocks[$cursor];
        $cursor++;
        return $block;
    }

    private static function next_block_by_heading_level(&$blocks, &$cursor, $level, &$found_index = null, $excluded_indices = []) {
        $count = count($blocks);
        for ($i = $cursor; $i < $count; $i++) {
            if ((int) ($blocks[$i]['heading_level'] ?? 0) !== (int) $level) continue;
            if (!empty($excluded_indices[(string) ($blocks[$i]['heading'] ?? '')])) continue;
            $cursor = $i + 1;
            $found_index = $i;
            return $blocks[$i];
        }
        return null;
    }

    private static function next_block_with_content(&$blocks, &$cursor) {
        while ($cursor < count($blocks)) {
            $block = $blocks[$cursor++];
            if (!empty($block['content'])) return $block;
        }
        return null;
    }

    private static function set_widget_title(&$settings, $value) {
        foreach (['title', 'title_text', 'heading', 'text', 'ekit_icon_box_title_text', 'ekit_icon_box_badge_title'] as $key) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = $value;
                return;
            }
        }

        // ElementsKit Icon List stores each heading-like label inside the
        // icon_list repeater rather than in a top-level title field. A marker
        // on the widget still represents the heading slot, so populate the
        // first item when it is used as a normal H3 marker.
        if (isset($settings['icon_list']) && is_array($settings['icon_list']) && isset($settings['icon_list'][0])) {
            $settings['icon_list'][0]['text'] = $value;
        }
    }

    private static function set_widget_text(&$settings, $value) {
        $html = wpautop($value);
        if (array_key_exists('editor', $settings)) {
            $settings['editor'] = $html;
        } elseif (array_key_exists('description_text', $settings)) {
            $settings['description_text'] = wp_strip_all_tags($value);
        } elseif (array_key_exists('ekit_icon_box_description_text', $settings)) {
            $settings['ekit_icon_box_description_text'] = wp_strip_all_tags($value);
        } elseif (array_key_exists('text', $settings)) {
            $settings['text'] = $value;
        } elseif (array_key_exists('content', $settings)) {
            $settings['content'] = $html;
        } elseif (array_key_exists('html', $settings)) {
            $settings['html'] = $html;
        }
    }

    /**
     * Populate a marked widget with the content types requested by its marker.
     *
     * Widgets such as Icon Box and Image Box expose separate title/description
     * fields, so h3|p maps naturally to those fields. A Text Editor only has
     * one content field, so the same marker must render both pieces of content
     * into that field instead of silently dropping the heading.
     */
    private static function set_widget_marker_content(&$settings, $marker, $heading, $content) {
        $heading = (string) $heading;
        $content = (string) $content;
        $has_heading = in_array($marker['type'], ['h1', 'h2', 'h3'], true);
        $has_paragraph = in_array('p', (array) ($marker['flags'] ?? []), true);

        if (array_key_exists('editor', $settings)) {
            $parts = [];
            if ($has_heading && $heading !== '') {
                $level = (int) substr($marker['type'], 1);
                $parts[] = '<h' . $level . '>' . esc_html($heading) . '</h' . $level . '>';
            }
            if ($has_paragraph && $content !== '') {
                $parts[] = wpautop($content);
            } elseif (!$has_heading && $content !== '') {
                $parts[] = wpautop($content);
            }

            if ($parts) {
                $settings['editor'] = implode("\n", $parts);
            }
            return;
        }

        if ($has_heading) {
            self::set_widget_title($settings, $heading);
        }

        if ($has_paragraph && $content !== '') {
            self::set_widget_text($settings, $content);
        }
    }

    /**
     * Modern repeatable markers are the actual repeatable content contract.
     * Each yellow heading starts one item; all following non-heading paragraphs
     * belong to that item until the next yellow heading.
     *
     * A marker such as data-customID|h2|repeatable identifies both the content
     * type and repeatable behavior. Multiple repeatable markers in one card
     * are cloned together by their containing Column.
     */
    /**
     * Build repeatable records from the document according to the template.
     *
     * New templates declare the source heading explicitly, for example:
     * data-customID|h2|repeatable
     *
     * That makes ordinary DOCX structure sufficient; no font color or hidden
     * document convention is required. Legacy yellow headings remain supported
     * when a template has only the old repeatableItem marker.
     */
    /** Flatten scoped collections for exclusion/debug consumers. */
    private static function flatten_repeatable_collections($collections) {
        $items = [];
        foreach ((array) $collections as $collection) {
            foreach ((array) ($collection['items'] ?? []) as $item) {
                $items[] = $item;
            }
        }
        return $items;
    }

    private static function build_repeatable_items($doc, $elements) {
        $collections = self::build_repeatable_collections($doc, $elements);

        if ($collections) {
            // Preserve the original single-array return value for older
            // validation/debug code. Generation itself uses the full
            // collection map so separate repeatable sections cannot consume
            // one another's DOCX records.
            $first = reset($collections);
            return is_array($first) ? (array) ($first['items'] ?? []) : [];
        }

        // Legacy compatibility: yellow heading boundaries are only used when
        // the template does not declare a modern heading|repeat marker.
        return array_values((array) ($doc['repeatables'] ?? []));
    }

    /**
     * Build one DOCX collection for every repeatable Elementor section.
     *
     * A repeatable H3 is scoped to the current H2 marker, an H4 to the
     * current H3/H2 hierarchy, and so on. Each repeatable section therefore
     * receives an independent collection and its own repeat cursor.
     *
     * The map is keyed by the Elementor section/container ID because element
     * indexes can change while repeatable sections are cloned.
     */
    private static function build_repeatable_collections($doc, $elements) {
        $collections = [];
        $ordinals = [];

        $walk = function ($nodes, $root_id = null) use (&$walk, &$collections, &$ordinals, $doc) {
            foreach ((array) $nodes as $node) {
                if (!is_array($node)) continue;

                $marker = WFEBPG_Template::marker($node);

                // Keep the outermost Elementor Section/Container as the
                // visual owner for widget-level repeatables. Elementor exports
                // often contain several nested Containers, but a marker such
                // as data-customID|h3|p|repeat should belong to the top-level
                // section that will be cloned/expanded. Previously every nested
                // container replaced the owner ID, so the collection could be
                // keyed by an inner container while expansion looked it up by
                // the outer section ID. That produced the misleading
                // "no matching DOCX heading scope" error.
                $is_root = self::is_repeatable_root($node);
                if ($is_root && ($root_id === null || $root_id === '')) {
                    $next_root_id = (string) ($node['id'] ?? '');
                } else {
                    $next_root_id = $root_id;
                }

                // Heading ordinals describe the template's document scopes.
                // Deeper heading counters reset whenever a higher heading is
                // encountered, exactly like the DOCX hierarchy.
                if (preg_match('/^h([1-9][0-9]*)$/', $marker['type'], $hm)) {
                    $level = (int) $hm[1];
                    $ordinals[$level] = (int) ($ordinals[$level] ?? 0) + 1;
                    foreach (array_keys($ordinals) as $known_level) {
                        if ((int) $known_level > $level) {
                            $ordinals[$known_level] = 0;
                        }
                    }
                }

                if (WFEBPG_Template::is_repeatable($node) && !self::is_internal_repeatable_widget($node)) {
                    $target_level = 0;
                    if (preg_match('/^h([1-9][0-9]*)$/', $marker['type'], $rm)) {
                        $target_level = (int) $rm[1];
                    }

                    if ($target_level > 0) {
                        $parent_level = $target_level > 1 ? $target_level - 1 : 0;
                        $scope_parts = [];
                        for ($level = 1; $level <= $parent_level; $level++) {
                            $scope_parts[] = 'h' . $level . ':' . (int) ($ordinals[$level] ?? 0);
                        }

                        $scope_key = $target_level . '|' . implode('|', $scope_parts);
                        $owner_id = $is_root && WFEBPG_Template::is_repeatable($node)
                            ? (string) ($node['id'] ?? '')
                            : (string) $next_root_id;

                        // Widget-level repeatables normally live inside an
                        // ordinary Section/Container. That parent is the
                        // visual repeat region even though it is not itself
                        // marked repeatable. This is especially important for
                        // a Text Editor carrying h3|p|repeat directly beneath
                        // an H2 marker.
                        if ($owner_id === '') {
                            $owner_id = 'rootless:' . $scope_key;
                        }

                        if (!isset($collections[$owner_id])) {
                            $collections[$owner_id] = [
                                'scope_key' => $scope_key,
                                'target_level' => $target_level,
                                'parent_level' => $parent_level,
                                'items' => self::build_repeatable_items_for_template_scope(
                                    $doc,
                                    $target_level,
                                    $ordinals
                                ),
                            ];
                        }
                    }
                }

                if (isset($node['elements']) && is_array($node['elements'])) {
                    $walk($node['elements'], $next_root_id);
                }
            }
        };

        $walk($elements);
        return $collections;
    }

    /** Build a DOCX collection from the template's current heading hierarchy. */
    private static function build_repeatable_items_for_template_scope($doc, $target_level, $template_ordinals) {
        $parent_level = $target_level > 1 ? $target_level - 1 : 0;
        if ($parent_level === 0) {
            return self::build_repeatable_items_for_scope($doc, $target_level, 0, 0);
        }

        $scope_ordinal = (int) ($template_ordinals[$parent_level] ?? 0);
        if ($scope_ordinal < 1) return [];

        return self::build_repeatable_items_for_scope(
            $doc,
            $target_level,
            $parent_level,
            $scope_ordinal
        );
    }

    /**
     * Build one repeatable collection from a heading level and optional parent
     * heading scope. The collection contains the boundary heading plus every
     * immediately following paragraph until the next heading. Deeper headings
     * are retained as nested heading data for backwards-compatible marked
     * descendants, but do not start a new item.
     */
    /** Build collections for internal repeater widgets using their nearest preceding parent-heading scope. */
    private static function build_internal_repeatable_items($doc, $elements) {
        $collections = [];
        $ordinals = [];
        $walk = function ($nodes) use (&$walk, &$collections, &$ordinals, $doc) {
            foreach ((array) $nodes as $node) {
                if (!is_array($node)) continue;
                $marker = WFEBPG_Template::marker($node);
                if (preg_match('/^h([1-9][0-9]*)$/', $marker['type'], $hm)) {
                    $level = (int) $hm[1];
                    $ordinals[$level] = (int) ($ordinals[$level] ?? 0) + 1;
                    foreach (array_keys($ordinals) as $known_level) {
                        if ((int) $known_level > $level) $ordinals[$known_level] = 0;
                    }
                }
                if (self::is_internal_repeatable_widget($node)) {
                    $target_level = (int) substr($marker['type'], 1);
                    $parent_level = $target_level > 1 ? $target_level - 1 : 0;
                    $parent_ordinal = $parent_level ? (int) $ordinals[$parent_level] : 0;
                    $collections[] = [
                        'widget_type' => (string) ($node['widgetType'] ?? ''),
                        'items' => self::build_repeatable_items_for_scope($doc, $target_level, $parent_level, $parent_ordinal),
                    ];
                }
                if (isset($node['elements']) && is_array($node['elements'])) $walk($node['elements']);
            }
        };
        $walk($elements);
        return $collections;
    }

    private static function is_internal_repeatable_widget($element) {
        if (!is_array($element) || !WFEBPG_Template::is_repeatable($element)) return false;
        $type = strtolower((string) ($element['widgetType'] ?? ''));
        $marker = WFEBPG_Template::marker($element);
        return in_array($type, self::INTERNAL_REPEAT_WIDGETS, true)
            && preg_match('/^h[1-9][0-9]*$/', $marker['type']);
    }

    /** Populate internal repeater widgets without cloning the widget itself. */
    private static function populate_internal_repeaters(&$elements, $doc) {
        $ordinals = [];
        $walk = function (&$nodes) use (&$walk, &$ordinals, $doc) {
            foreach ($nodes as &$node) {
                if (!is_array($node)) continue;
                $marker = WFEBPG_Template::marker($node);
                if (preg_match('/^h([1-9][0-9]*)$/', $marker['type'], $hm)) {
                    $level = (int) $hm[1];
                    $ordinals[$level] = (int) ($ordinals[$level] ?? 0) + 1;
                    foreach (array_keys($ordinals) as $known_level) {
                        if ((int) $known_level > $level) $ordinals[$known_level] = 0;
                    }
                }
                if (self::is_internal_repeatable_widget($node)) {
                    $target_level = (int) substr($marker['type'], 1);
                    $parent_level = $target_level > 1 ? $target_level - 1 : 0;
                    $parent_ordinal = $parent_level ? (int) $ordinals[$parent_level] : 0;
                    $items = self::build_repeatable_items_for_scope($doc, $target_level, $parent_level, $parent_ordinal);
                    self::populate_internal_repeatable_widget($node, $items);
                }
                if (isset($node['elements']) && is_array($node['elements'])) $walk($node['elements']);
            }
            unset($node);
        };
        $walk($elements);
    }

    private static function populate_internal_repeatable_widget(&$widget, $items) {
        if (strtolower((string) ($widget['widgetType'] ?? '')) !== 'toggle') return;
        $settings = isset($widget['settings']) && is_array($widget['settings']) ? $widget['settings'] : [];
        $tabs = [];
        foreach ((array) $items as $item) {
            $tabs[] = [
                'tab_title' => (string) ($item['heading'] ?? ''),
                'tab_content' => wpautop(implode("\n\n", (array) ($item['content'] ?? []))),
                '_id' => self::new_element_id(),
            ];
        }
        $settings['tabs'] = $tabs;
        $widget['settings'] = $settings;
    }

    private static function build_repeatable_items_for_scope($doc, $target_level, $scope_parent_level = 0, $scope_parent_ordinal = 0) {
        $items = [];
        $current = null;
        $parent_ordinal = 0;
        $in_scope = $scope_parent_level === 0;

        foreach (($doc['items'] ?? []) as $doc_index => $item) {
            $is_heading = !empty($item['heading']);
            $level = (int) ($item['heading_level'] ?? 0);

            if ($scope_parent_level && $is_heading && $level === $scope_parent_level) {
                $parent_ordinal++;
                $in_scope = ($parent_ordinal === $scope_parent_ordinal);
                if (!$in_scope && $current !== null) {
                    $items[] = $current;
                    $current = null;
                }
                continue;
            }

            if (!$in_scope) continue;

            if ($is_heading && $level === $target_level) {
                if ($current !== null) $items[] = $current;
                $current = [
                    'heading' => (string) $item['text'],
                    'heading_level' => $target_level,
                    'source_index' => $doc_index,
                    'headings' => [
                        $target_level => [(string) $item['text']],
                    ],
                    'content' => [],
                    'repeatable' => true,
                ];
                continue;
            }

            if ($current === null) continue;

            // A heading above the repeatable level closes the current record.
            if ($is_heading && $level < $target_level) {
                $items[] = $current;
                $current = null;
                // If this is the parent scope heading, the next target-level
                // heading belongs to the next parent section and is therefore
                // outside this collection.
                if ($scope_parent_level && $level <= $scope_parent_level) {
                    $in_scope = false;
                }
                continue;
            }

            if ($is_heading) {
                $current['headings'][$level][] = (string) $item['text'];
                continue;
            }

            // Every immediate paragraph after the boundary heading belongs to
            // that record, including multiple paragraphs before the next heading.
            $current['content'][] = (string) $item['text'];
        }

        if ($current !== null) $items[] = $current;
        return $items;
    }

    /**
     * Populate repeatable Text Editor streams without cloning their Elementor
     * section/container.
     *
     * Marker contract:
     *     data-customID|h3|p|repeat
     *
     * The nearest preceding marked H2 establishes the DOCX scope. Every H3/P
     * pair until the next higher-level heading is rendered into the same
     * Text Editor as real HTML headings and paragraphs. This is intentionally
     * different from a repeatable card: one editor is the destination for the
     * entire stream.
     */
    private static function populate_inline_repeat_text_editors(&$elements, $doc) {
        $ordinals = [];

        $walk = function (&$nodes) use (&$walk, &$ordinals, $doc) {
            foreach ($nodes as &$node) {
                if (!is_array($node)) continue;

                $marker = WFEBPG_Template::marker($node);

                if (preg_match('/^h([1-9][0-9]*)$/', $marker['type'], $hm)) {
                    $level = (int) $hm[1];
                    $ordinals[$level] = (int) ($ordinals[$level] ?? 0) + 1;
                    foreach (array_keys($ordinals) as $known_level) {
                        if ((int) $known_level > $level) {
                            $ordinals[$known_level] = 0;
                        }
                    }
                }

                $widget_type = strtolower((string) ($node['widgetType'] ?? ''));
                $is_inline_stream = $widget_type === 'text-editor'
                    && WFEBPG_Template::is_repeatable($node)
                    && preg_match('/^h([1-9][0-9]*)$/', $marker['type'], $rm)
                    && in_array('p', (array) ($marker['flags'] ?? []), true);

                if ($is_inline_stream) {
                    $target_level = (int) $rm[1];
                    $parent_level = $target_level > 1 ? $target_level - 1 : 0;
                    $parent_ordinal = $parent_level > 0
                        ? (int) ($ordinals[$parent_level] ?? 0)
                        : 0;

                    $items = self::build_repeatable_items_for_scope(
                        $doc,
                        $target_level,
                        $parent_level,
                        $parent_ordinal
                    );

                    $html = [];
                    foreach ($items as $item) {
                        $heading = trim((string) ($item['heading'] ?? ''));
                        $content = trim(implode("\n\n", (array) ($item['content'] ?? [])));

                        if ($heading !== '') {
                            $html[] = '<h' . $target_level . '>' . esc_html($heading) . '</h' . $target_level . '>';
                        }
                        if ($content !== '') {
                            $html[] = wpautop($content);
                        }
                    }

                    if (!isset($node['settings']) || !is_array($node['settings'])) {
                        $node['settings'] = [];
                    }
                    if ($html) {
                        $node['settings']['editor'] = implode("\n", $html);
                    }

                    // The stream has already been consumed. Removing the marker
                    // prevents the later repeatable-section pass from cloning
                    // the containing Section/Container.
                    self::remove_custom_marker($node);
                }

                if (isset($node['elements']) && is_array($node['elements'])) {
                    $walk($node['elements']);
                }
            }
            unset($node);
        };

        $walk($elements);
    }

    /** Remove the data-customID marker from a generated element. */
    private static function remove_custom_marker(&$element) {
        if (!isset($element['settings']) || !is_array($element['settings'])) return;

        foreach (['customID', 'custom_id', 'data-customID', 'data_customID'] as $key) {
            unset($element[$key], $element['settings'][$key]);
        }

        foreach (['_attributes', 'custom_attributes', 'attributes'] as $key) {
            if (!array_key_exists($key, $element['settings'])) continue;

            $value = $element['settings'][$key];
            if (is_string($value) && stripos(trim($value), 'data-customid|') === 0) {
                unset($element['settings'][$key]);
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $attribute => $attribute_value) {
                    if (strtolower((string) $attribute) === 'data-customid') {
                        unset($element['settings'][$key][$attribute]);
                    }
                }
                if (!$element['settings'][$key]) unset($element['settings'][$key]);
            }
        }
    }

    /** Populate every marked element belonging to one repeatable item. */
    private static function populate_repeatable_element(&$element, $item, &$state) {
        if (!WFEBPG_Template::is_repeatable($element)) return;

        $marker = WFEBPG_Template::marker($element);
        $settings = isset($element['settings']) && is_array($element['settings'])
            ? $element['settings']
            : [];

        switch ($marker['type']) {
            case self::H1_ID:
            case self::SECTION_TITLE_ID:
            case self::H_ID:
                $level = (int) substr($marker['type'], 1);
                $headings = (array) ($item['headings'][$level] ?? []);
                $index = (int) ($state['heading'][$level] ?? 0);
                $value = $headings[$index] ?? ($item['heading'] ?? '');
                $state['heading'][$level] = $index + 1;
                // Generic widget-level fallback: if this marked element is a
                // legacy widget rather than a repeatable parent container, map
                // its available title/text fields without naming a widget type.
                $content = implode("\n\n", (array) ($item['content'] ?? []));
                self::set_widget_marker_content($settings, $marker, (string) $value, $content);
                break;

            case self::P_ID:
                $paragraphs = array_values((array) ($item['content'] ?? []));
                if (count($state['paragraph_markers']) === 1) {
                    $value = implode("\n\n", $paragraphs);
                } else {
                    $index = (int) ($state['paragraph'] ?? 0);
                    $value = (string) ($paragraphs[$index] ?? '');
                    $state['paragraph'] = $index + 1;
                }
                self::set_widget_text($settings, $value);
                break;

            case self::REPEAT_ID:
                // Legacy repeatableItem did not specify a content type. Keep its
                // existing widget-specific behavior for old templates.
                self::populate_repeatable_widget($element, $item);
                return;
        }

        $element['settings'] = $settings;
    }

    /**
     * Populate one repeatable root and its descendants. A section/container
     * marked with h3|repeat (or h3|p|repeat) is a complete content unit. The
     * first Heading widget inside it receives the heading, and the first Text
     * Editor receives all paragraphs belonging to that heading. Child markers
     * remain supported for backwards compatibility, but they are not required.
     */
    private static function populate_repeatable_card(&$elements, $item) {
        $state = [
            'heading' => [],
            'paragraph' => 0,
            'paragraph_markers' => [],
        ];

        if (WFEBPG_Template::is_repeatable($elements) && self::is_repeatable_root($elements)) {
            self::populate_repeatable_root($elements, $item);
        }

        $collect = function (&$node) use (&$collect, &$state) {
            if (!is_array($node)) return;
            if (WFEBPG_Template::is_repeatable($node) && WFEBPG_Template::custom_id($node) === self::P_ID) {
                $state['paragraph_markers'][] = true;
            }
            if (isset($node['elements']) && is_array($node['elements'])) {
                foreach ($node['elements'] as &$child) {
                    $collect($child);
                }
                unset($child);
            }
        };
        $collect($elements);

        $populate = function (&$node, $is_root = false) use (&$populate, $item, &$state) {
            if (!is_array($node)) return;

            // The root container has already been populated from its first
            // Heading/Text Editor pair. Do not reinterpret its marker as a
            // widget-level title slot.
            if (!$is_root) {
                self::populate_repeatable_element($node, $item, $state);
            }

            if (isset($node['elements']) && is_array($node['elements'])) {
                foreach ($node['elements'] as &$child) {
                    $populate($child, false);
                }
                unset($child);
            }
        };
        $populate($elements, self::is_repeatable_root($elements));
    }

    /**
     * Populate a repeatable section/container using the first Heading widget
     * and first Text Editor widget found inside it. This is deliberately
     * widget-agnostic: the parent marker defines the content contract and the
     * child widget types define the two destinations.
     */
    private static function populate_repeatable_root(&$root, $item) {
        $heading_value = (string) ($item['heading'] ?? '');
        $paragraph_value = implode("\n\n", (array) ($item['content'] ?? []));
        $found_heading = false;
        $found_text = false;

        $walk = function (&$node) use (&$walk, &$found_heading, &$found_text, $heading_value, $paragraph_value) {
            if (!is_array($node)) return;

            $widget_type = (string) ($node['widgetType'] ?? '');
            if (!$found_heading && $widget_type === 'heading') {
                $settings = isset($node['settings']) && is_array($node['settings']) ? $node['settings'] : [];
                self::set_widget_title($settings, $heading_value);
                $node['settings'] = $settings;
                $found_heading = true;
            } elseif (!$found_text && $widget_type === 'text-editor') {
                $settings = isset($node['settings']) && is_array($node['settings']) ? $node['settings'] : [];
                self::set_widget_text($settings, $paragraph_value);
                $node['settings'] = $settings;
                $found_text = true;
            }

            if (isset($node['elements']) && is_array($node['elements'])) {
                foreach ($node['elements'] as &$child) {
                    if ($found_heading && $found_text) break;
                    $walk($child);
                }
                unset($child);
            }
        };
        $walk($root);

        // Backwards-compatible fallback for legacy repeatable widgets such as
        // Icon Box. The generic child-widget contract is preferred whenever a
        // Heading/Text Editor pair exists; this fallback does not depend on a
        // specific widget type.
        if (!$found_heading && !$found_text && isset($root['settings'])) {
            self::populate_repeatable_widget($root, $item);
        }
    }

    /**
     * Populate repeatable sections using an independent DOCX collection for
     * each Elementor repeatable region. Legacy templates may still pass one
     * flat repeatable array; modern templates pass a section-ID keyed map.
     */
    private static function populate_repeatables(&$elements, $repeatables, $widgets_per_section = 0, $image_pool = [], $doc = null) {
        if (!$repeatables) return;
        if (is_array($doc)) self::populate_internal_repeaters($elements, $doc);

        $is_collection_map = self::is_repeatable_collection_map($repeatables);

        if ($widgets_per_section > 0 && self::expand_repeatable_sections(
            $elements,
            $repeatables,
            $widgets_per_section,
            $image_pool,
            $is_collection_map
        )) {
            return;
        }

        // Element-level fallback. Modern templates still get one collection
        // per repeatable region, while legacy templates retain the original
        // flat collection behavior.
        $locations = [];
        self::find_repeatable_locations($elements, $locations);
        $groups = self::group_repeatable_locations($locations);

        if (!$is_collection_map) {
            $existing = count($groups);
            $wanted = count($repeatables);

            if ($existing === 0 && $wanted > 0) {
                if (is_array($doc)) return;
                throw new Exception('The Elementor template contains repeatable content but no repeatable element could be located.');
            }

            if ($wanted < $existing) {
                self::remove_repeatable_groups_from_end($elements, $existing - $wanted);
            } elseif ($wanted > $existing) {
                self::clone_repeatable_groups($elements, $groups, $wanted - $existing);
            }

            $locations = [];
            self::find_repeatable_locations($elements, $locations);
            $groups = self::group_repeatable_locations($locations);
            foreach ($groups as $i => $group) {
                if (!isset($repeatables[$i])) continue;
                foreach ($group['nodes'] as $node_info) {
                    $node =& self::get_node_reference($elements, $node_info['node_path']);
                    if ($node !== null) self::populate_repeatable_card($node, $repeatables[$i]);
                    unset($node);
                }
            }
            return;
        }

        // Modern element-level fallback. A repeatable group is associated with
        // the collection belonging to its containing section ID. This keeps a
        // process Image Box from consuming the Services collection, for example.
        $collection_cursors = [];
        foreach ($groups as $group) {
            $owner_id = self::repeatable_group_owner_id($group);
            if ($owner_id === null || !isset($repeatables[$owner_id])) continue;

            $items = (array) ($repeatables[$owner_id]['items'] ?? []);
            $cursor = (int) ($collection_cursors[$owner_id] ?? 0);
            if ($cursor >= count($items)) continue;

            foreach ($group['nodes'] as $node_info) {
                if (!isset($items[$cursor])) break;
                $node =& self::get_node_reference($elements, $node_info['node_path']);
                if ($node !== null) self::populate_repeatable_card($node, $items[$cursor]);
                unset($node);
            }
            $collection_cursors[$owner_id] = $cursor + 1;
        }
    }

    private static function is_repeatable_collection_map($value) {
        if (!is_array($value) || !$value) return false;
        $first = reset($value);
        return is_array($first)
            && array_key_exists('items', $first)
            && array_key_exists('target_level', $first);
    }

    /** Return the Elementor section/container ID owning a repeatable group. */
    private static function repeatable_group_owner_id($group) {
        foreach ((array) ($group['nodes'] ?? []) as $node_info) {
            $node = $node_info['node'] ?? [];
            if (self::is_repeatable_root($node)) {
                return (string) ($node['id'] ?? '');
            }
        }

        // For widget-level repeatables, the group itself is usually inside a
        // section. Recover that owner from the nearest available parent path
        // is not possible after grouping alone, so the caller's modern path
        // should normally be handled by section expansion first.
        return null;
    }

    private static function section_repeatable_collection($section, $repeatable_collections, $is_collection_map) {
        if (!$is_collection_map || !is_array($section)) return null;
        $section_id = (string) ($section['id'] ?? '');
        if ($section_id === '' || !isset($repeatable_collections[$section_id])) return null;
        return $repeatable_collections[$section_id];
    }

    private static function section_repeatable_locations($section) {
        $locations = [];
        if (!is_array($section) || !isset($section['elements']) || !is_array($section['elements'])) return $locations;
        self::find_repeatable_locations($section['elements'], $locations);
        return array_values(array_filter($locations, static function ($location) {
            return !self::is_repeatable_root($location['node'] ?? []);
        }));
    }

    private static function is_repeatable_root($element) {
        $type = (string) ($element['elType'] ?? '');
        return in_array($type, ['section', 'container'], true);
    }

    /**
     * Replace every section/container containing repeatable markers with one or more
     * cloned sections. Modern repeatable sections consume their own scoped DOCX
     * collection; legacy templates continue to use one flat collection.
     */
    private static function expand_repeatable_sections(&$elements, $repeatables, $widgets_per_section, $image_pool = [], $is_collection_map = false) {
        $image_pool = self::prepare_image_pool($image_pool);
        return self::expand_repeatable_sections_recursive(
            $elements,
            $repeatables,
            $widgets_per_section,
            $image_pool,
            $is_collection_map
        );
    }

    private static function expand_repeatable_sections_recursive(&$elements, $repeatables, $limit, $image_pool = [], $is_collection_map = false) {
        $found_section = false;
        $element_count = count($elements);

        for ($i = 0; $i < $element_count; $i++) {
            if (self::is_repeatable_root($elements[$i])) {
                $locations = self::section_repeatable_locations($elements[$i]);
                if ($locations) {
                    $collection = self::section_repeatable_collection(
                        $elements[$i],
                        $repeatables,
                        $is_collection_map
                    );
                    $items = $is_collection_map
                        ? (array) ($collection['items'] ?? [])
                        : (array) $repeatables;

                    // A modern repeatable marker that lives inside a section
                    // must have a corresponding DOCX scope. If no scoped
                    // collection was built, leave the prototype untouched and
                    // report the mapping problem instead of silently consuming
                    // another section's records.
                    if ($is_collection_map && $collection === null) {
                        throw new Exception(
                            'A repeatable Elementor section (ID ' .
                            (string) ($elements[$i]['id'] ?? 'unknown') .
                            ') has no matching DOCX heading scope.'
                        );
                    }

                    $found_section = true;
                    $item_count = count($items);
                    if ($item_count === 0) {
                        self::remove_all_repeatables($elements[$i]['elements']);
                        continue;
                    }

                    $prototype = $elements[$i];
                    $prototype_groups = self::group_repeatable_locations($locations);

                    $prototype_color_profile = self::repeatable_color_profile(
                        $prototype,
                        $prototype_groups
                    );

                    $prototype_count = count($prototype_groups);
                    if ($prototype_count === 0) continue;

                    $section_count = (int) ceil($item_count / $limit);
                    $replacement = [];

                    for ($section_index = 0; $section_index < $section_count; $section_index++) {
                        $section = self::deep_clone_element($prototype);
                        self::remove_all_repeatables($section['elements']);

                        $chunk_count = min($limit, $item_count - ($section_index * $limit));
                        $section_images = self::random_image_pool(
                            $image_pool,
                            $chunk_count
                        );

                        $source_offset = 0;
                        for ($j = 0; $j < $chunk_count; $j++) {
                            $item_index = ($section_index * $limit) + $j;
                            $source_index = $chunk_count < $limit
                                ? ($source_offset + $j) % $prototype_count
                                : ($section_index * $limit + $j) % $prototype_count;
                            $source = $prototype_groups[$source_index];

                            foreach ($source['nodes'] as $node_info) {
                                $copy = self::deep_clone_element($node_info['node']);
                                self::populate_repeatable_card($copy, $items[$item_index]);
                                self::append_to_path($section['elements'], $node_info['parent_path'], $copy);
                            }

                            $column_path = $source['column_path'];
                            if ($column_path !== null) {
                                self::apply_repeatable_card_visuals(
                                    $section['elements'],
                                    $column_path,
                                    $section_images[$j] ?? null,
                                    $prototype_color_profile,
                                    $j,
                                    $section_index
                                );
                            }
                        }

                        if ($chunk_count < $limit) {
                            self::center_partial_repeatable_columns(
                                $section,
                                $prototype_groups,
                                $chunk_count
                            );
                        }

                        $replacement[] = $section;
                    }

                    array_splice($elements, $i, 1, $replacement);
                    $element_count = count($elements);
                    $i += count($replacement) - 1;
                    continue;
                }
            }

            if (isset($elements[$i]['elements']) && is_array($elements[$i]['elements'])) {
                if (self::expand_repeatable_sections_recursive(
                    $elements[$i]['elements'],
                    $repeatables,
                    $limit,
                    $image_pool,
                    $is_collection_map
                )) {
                    $found_section = true;
                }
            }
        }

        return $found_section;
    }

    private static function center_partial_repeatable_columns(&$section, $prototype_groups, $used_count) {
        $total = count($prototype_groups);
        if ($total === 0 || $used_count >= $total) return;

        $cards = [];
        $used_paths = [];
        for ($i = 0; $i < $used_count; $i++) {
            $source = $prototype_groups[$i] ?? null;
            if (!$source) return;
            $path = $source['column_path'] ?? null;
            if (!is_array($path) || count($path) !== 1) return;
            $index = (int) $path[0];
            if (!isset($section['elements'][$index]) || !is_array($section['elements'][$index])) return;
            $used_paths[$index] = true;
            $cards[] = $section['elements'][$index];
        }

        if (count($cards) !== $used_count) return;

        $card_width = 100 / $total;
        foreach ($cards as &$card) {
            if (!isset($card['settings']) || !is_array($card['settings'])) $card['settings'] = [];
            $card['settings']['_column_size'] = $card_width;
            $card['settings']['_inline_size'] = null;
        }
        unset($card);

        // Remove unused visual columns. The CSS class below centers the
        // remaining 25%-wide cards as one group inside the section container.
        $remaining = [];
        foreach ($section['elements'] as $index => $column) {
            if (isset($used_paths[$index])) $remaining[] = $column;
        }
        if (count($remaining) !== $used_count) return;

        $section['elements'] = $remaining;
        $settings = isset($section['settings']) && is_array($section['settings'])
            ? $section['settings']
            : [];
        $classes = preg_split('/\s+/', trim((string) ($settings['css_classes'] ?? '')));
        $classes = array_values(array_filter($classes));
        if (!in_array('wfebpg-partial-centered', $classes, true)) $classes[] = 'wfebpg-partial-centered';
        $settings['css_classes'] = implode(' ', $classes);
        $section['settings'] = $settings;
    }

    /** Remove a node at an element-tree path. */
    private static function remove_at_path(&$root, $path) {
        if (!$path) return false;
        $parent_path = $path;
        $index = array_pop($parent_path);
        $parent =& $root;
        foreach ($parent_path as $step) {
            if (!isset($parent[$step]['elements']) || !is_array($parent[$step]['elements'])) {
                unset($parent);
                return false;
            }
            $parent =& $parent[$step]['elements'];
        }
        if (!isset($parent[$index])) {
            unset($parent);
            return false;
        }
        array_splice($parent, $index, 1);
        unset($parent);
        return true;
    }

    /**
     * Remove the visual prototype columns that were not used in the current
     * repeatable section. This is important when a column's background image
     * is its visual card: removing only the Icon Box leaves a blank image-only
     * card behind.
     */
    private static function remove_unused_repeatable_columns(&$section, $prototype_widgets, $used_count, $source_offset = 0) {
        $used_paths = [];
        $total = count($prototype_widgets);
        if ($total === 0) return;

        for ($j = 0; $j < $used_count; $j++) {
            $path = $prototype_widgets[($source_offset + $j) % $total]['column_path'] ?? null;
            if ($path !== null) {
                $used_paths[serialize($path)] = true;
            }
        }

        $unused = [];
        foreach ($prototype_widgets as $source) {
            $column_path = $source['column_path'] ?? null;
            if ($column_path === null) continue;

            $key = serialize($column_path);
            if (!isset($used_paths[$key])) {
                $unused[$key] = $column_path;
            }
        }

        usort($unused, function ($a, $b) {
            $a_count = count($a);
            $b_count = count($b);
            if ($a_count !== $b_count) return $b_count <=> $a_count;

            for ($i = 0; $i < $a_count; $i++) {
                if ($a[$i] !== $b[$i]) return $b[$i] <=> $a[$i];
            }
            return 0;
        });

        foreach ($unused as $path) {
            self::remove_at_path($section['elements'], $path);
        }
    }

    /**
     * Keep the prototype's original column grid for a partial final row and
     * clear unused card columns so they become invisible spacers.
     *
     * Example with four 25% columns:
     * 3 cards -> [blank] [card] [card] [card]
     * 2 cards -> [blank] [card] [card] [blank]
     * 1 card  -> [blank] [blank] [card] [blank]
     */
    private static function clear_unused_repeatable_columns(&$section, $prototype_widgets, $used_count, $source_offset = 0) {
        $total = count($prototype_widgets);
        if ($total === 0 || $used_count >= $total) return;

        $used = [];
        for ($j = 0; $j < $used_count; $j++) {
            $source = $prototype_widgets[($source_offset + $j) % $total] ?? null;
            $path = is_array($source) ? ($source['column_path'] ?? null) : null;
            if ($path !== null) $used[serialize($path)] = true;
        }

        foreach ($prototype_widgets as $source) {
            $path = $source['column_path'] ?? null;
            if ($path === null || isset($used[serialize($path)])) continue;

            $column =& self::get_node_reference($section['elements'], $path);
            if ($column === null) continue;

            if (!isset($column['settings']) || !is_array($column['settings'])) {
                $column['settings'] = [];
            }

            unset(
                $column['settings']['background_image'],
                $column['settings']['background_image_mobile'],
                $column['settings']['background_color'],
                $column['settings']['background_overlay_color'],
                $column['settings']['background_overlay_opacity']
            );

            if (isset($column['settings']['__globals__']) && is_array($column['settings']['__globals__'])) {
                unset(
                    $column['settings']['__globals__']['background_color'],
                    $column['settings']['__globals__']['background_overlay_color']
                );
            }

            unset(
                $column['settings']['background_background'],
                $column['settings']['background_overlay_background']
            );
        }
    }

    /** Return a writable reference to an Elementor node at a path. */
    private static function &get_node_reference(&$root, $path) {
        $null = null;
        $path = array_values((array) $path);
        if (!$path) return $null;

        $parent =& $root;
        $last = array_pop($path);
        foreach ($path as $index) {
            if (!isset($parent[$index]['elements']) || !is_array($parent[$index]['elements'])) {
                return $null;
            }
            $parent =& $parent[$index]['elements'];
        }

        if (!isset($parent[$last]) || !is_array($parent[$last])) return $null;
        return $parent[$last];
    }

    private static function remove_all_repeatables(&$elements) {
        if (!is_array($elements)) return;
        for ($i = count($elements) - 1; $i >= 0; $i--) {
            if (WFEBPG_Template::is_repeatable($elements[$i])) {
                array_splice($elements, $i, 1);
                continue;
            }
            if (isset($elements[$i]['elements']) && is_array($elements[$i]['elements'])) {
                self::remove_all_repeatables($elements[$i]['elements']);
            }
        }
    }


    /**
     * Capture the color treatment used by each repeatable card's containing
     * Elementor Column. The preferred treatment is the background overlay
     * color because Wolf Forge cards commonly place a translucent color over
     * a background image. Literal colors and Elementor global color tokens are
     * both supported. A plain background color is used as a fallback.
     */
    private static function repeatable_color_profile($section, $prototype_groups) {
        $profile = [];

        foreach ((array) $prototype_groups as $source) {
            $path = $source['column_path'] ?? null;
            $column = $path !== null
                ? self::get_node_at_path($section['elements'] ?? [], $path)
                : null;

            $settings = is_array($column) && isset($column['settings']) && is_array($column['settings'])
                ? $column['settings']
                : [];
            $globals = isset($settings['__globals__']) && is_array($settings['__globals__'])
                ? $settings['__globals__']
                : [];

            if (!empty($globals['background_overlay_color'])) {
                $profile[] = [
                    'type' => 'overlay',
                    'storage' => 'global',
                    'value' => (string) $globals['background_overlay_color'],
                ];
                continue;
            }

            if (!empty($settings['background_overlay_color'])) {
                $profile[] = [
                    'type' => 'overlay',
                    'storage' => 'setting',
                    'value' => (string) $settings['background_overlay_color'],
                ];
                continue;
            }

            if (!empty($globals['background_color'])) {
                $profile[] = [
                    'type' => 'background',
                    'storage' => 'global',
                    'value' => (string) $globals['background_color'],
                ];
                continue;
            }

            if (!empty($settings['background_color'])) {
                $profile[] = [
                    'type' => 'background',
                    'storage' => 'setting',
                    'value' => (string) $settings['background_color'],
                ];
                continue;
            }

            $profile[] = ['type' => 'none', 'storage' => 'none', 'value' => null];
        }

        return self::normalize_repeatable_color_profile($profile);
    }

    /**
     * Validate the prototype once. The hot generation loop then only performs
     * an O(1) slot lookup instead of re-checking the entire A/B/A/B profile
     * for every card.
     */
    private static function normalize_repeatable_color_profile($profile) {
        $profile = array_values((array) $profile);
        if (count($profile) < 2) {
            return ['enabled' => false, 'entries' => []];
        }

        $first = $profile[0]['value'] ?? null;
        $second = $profile[1]['value'] ?? null;
        if ($first === null || $second === null || $first === $second) {
            return ['enabled' => false, 'entries' => $profile];
        }

        foreach ($profile as $index => $entry) {
            $expected = ($index & 1) === 0 ? $first : $second;
            if (($entry['value'] ?? null) !== $expected) {
                return ['enabled' => false, 'entries' => $profile];
            }
        }

        return [
            'enabled' => true,
            'entries' => [$profile[0], $profile[1]],
        ];
    }

    /** Read an Elementor node from an element-tree path. */
    private static function get_node_at_path($root, $path) {
        $node = $root;
        $path = array_values((array) $path);
        $path_count = count($path);

        foreach ($path as $position => $index) {
            if (!is_array($node) || !isset($node[$index]) || !is_array($node[$index])) {
                return null;
            }

            $node = $node[$index];
            if ($position < $path_count - 1) {
                if (!isset($node['elements']) || !is_array($node['elements'])) {
                    return null;
                }
                $node = $node['elements'];
            }
        }

        return $node;
    }

    /**
     * Apply image and alternating color treatment to one repeatable card.
     * The target column is traversed only once, avoiding two independent path
     * walks for every generated card.
     */
    private static function apply_repeatable_card_visuals(
        &$root,
        $column_path,
        $image,
        $color_profile,
        $slot,
        $section_index
    ) {
        if (!$column_path) return false;

        $node =& $root;
        $path_count = count($column_path);
        $last_position = $path_count - 1;

        foreach ($column_path as $position => $index) {
            if (!isset($node[$index]) || !is_array($node[$index])) {
                unset($node);
                return false;
            }

            if ($position === $last_position) {
                $column =& $node[$index];
                if (!isset($column['settings']) || !is_array($column['settings'])) {
                    $column['settings'] = [];
                }

                if (is_array($image) && !empty($image['id']) && !empty($image['url'])) {
                    $column['settings']['background_image'] = [
                        'id' => (int) $image['id'],
                        'url' => $image['url'],
                    ];

                    if (array_key_exists('background_image_mobile', $column['settings'])) {
                        $column['settings']['background_image_mobile'] = [
                            'id' => (int) $image['id'],
                            'url' => $image['url'],
                        ];
                    }
                }

                if (!empty($color_profile['enabled'])) {
                    $target_index = (($slot & 1) ^ ($section_index & 1));
                    $entry = $color_profile['entries'][$target_index] ?? null;

                    if (is_array($entry) && !empty($entry['value'])) {
                        $storage = $entry['storage'] ?? 'global';

                        if ($entry['type'] === 'overlay') {
                            if ($storage === 'global') {
                                $column['settings']['background_overlay_color'] = '';
                                if (!isset($column['settings']['__globals__']) || !is_array($column['settings']['__globals__'])) {
                                    $column['settings']['__globals__'] = [];
                                }
                                $column['settings']['__globals__']['background_overlay_color'] = $entry['value'];
                            } else {
                                if (isset($column['settings']['__globals__']) && is_array($column['settings']['__globals__'])) {
                                    unset($column['settings']['__globals__']['background_overlay_color']);
                                }
                                $column['settings']['background_overlay_color'] = $entry['value'];
                            }

                            if (!isset($column['settings']['background_overlay_background'])) {
                                $column['settings']['background_overlay_background'] = 'classic';
                            }
                        } elseif ($entry['type'] === 'background') {
                            if ($storage === 'global') {
                                $column['settings']['background_color'] = '';
                                if (!isset($column['settings']['__globals__']) || !is_array($column['settings']['__globals__'])) {
                                    $column['settings']['__globals__'] = [];
                                }
                                $column['settings']['__globals__']['background_color'] = $entry['value'];
                            } else {
                                if (isset($column['settings']['__globals__']) && is_array($column['settings']['__globals__'])) {
                                    unset($column['settings']['__globals__']['background_color']);
                                }
                                $column['settings']['background_color'] = $entry['value'];
                            }
                        }
                    }
                }

                unset($column, $node);
                return true;
            }

            if (!isset($node[$index]['elements']) || !is_array($node[$index]['elements'])) {
                unset($node);
                return false;
            }

            $node =& $node[$index]['elements'];
        }

        unset($node);
        return false;
    }

    /**
     * Return a randomized, duplicate-free subset of the active Media Library
     * image pool for one repeatable section.
     */
    /**
     * Resolve and validate the active Media Library pool once per generation.
     * Attachment lookups are much more expensive than array shuffles, so
     * generated sections reuse this prepared pool.
     */
    private static function prepare_image_pool($ids) {
        static $cache = [];

        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
        if (!$ids) return [];

        $cache_key = implode(',', $ids);
        if (isset($cache[$cache_key])) return $cache[$cache_key];

        $valid = [];
        foreach ($ids as $id) {
            if (get_post_type($id) !== 'attachment') continue;

            $mime = get_post_mime_type($id);
            if (!$mime || strpos($mime, 'image/') !== 0) continue;

            $url = wp_get_attachment_image_url($id, 'full');
            if (!$url) continue;

            $valid[] = [
                'id' => $id,
                'url' => $url,
            ];
        }

        $cache[$cache_key] = $valid;
        return $valid;
    }

    /**
     * Return a randomized, duplicate-free subset from an already prepared
     * Media Library image pool for one repeatable section.
     */
    private static function random_image_pool($prepared_pool, $needed) {
        if (!$needed || !$prepared_pool) return [];

        $pool = array_values($prepared_pool);
        shuffle($pool);

        if (count($pool) < $needed) {
            WFEBPG_Logger::log(
                'Image pool contains only ' . count($pool) . ' usable image(s) for a ' . $needed . '-item repeatable section. Selected pool images will not be duplicated; remaining cards keep their template image.',
                'warning'
            );
        }

        return array_slice($pool, 0, $needed);
    }

    /** Populate the first Heading and first Text Editor inside a content-pair root. */
    private static function populate_content_pair_root(&$root, $block) {
        $heading_value = (string) ($block['heading'] ?? '');
        $paragraph_value = implode("\n\n", (array) ($block['content'] ?? []));
        $found_heading = false;
        $found_text = false;

        $walk = function (&$node) use (&$walk, &$found_heading, &$found_text, $heading_value, $paragraph_value) {
            if (!is_array($node)) return;

            $widget_type = (string) ($node['widgetType'] ?? '');
            if (!$found_heading && $widget_type === 'heading') {
                $settings = isset($node['settings']) && is_array($node['settings']) ? $node['settings'] : [];
                self::set_widget_title($settings, $heading_value);
                $node['settings'] = $settings;
                $found_heading = true;
            } elseif (!$found_text && $widget_type === 'text-editor') {
                $settings = isset($node['settings']) && is_array($node['settings']) ? $node['settings'] : [];
                self::set_widget_text($settings, $paragraph_value);
                $node['settings'] = $settings;
                $found_text = true;
            }

            if (isset($node['elements']) && is_array($node['elements'])) {
                foreach ($node['elements'] as &$child) {
                    if ($found_heading && $found_text) break;
                    $walk($child);
                }
                unset($child);
            }
        };
        $walk($root);

        // If a legacy template uses a widget directly as the marked element,
        // retain the generic settings-field fallback without naming a widget
        // type. This keeps old templates usable while the new container model
        // remains the preferred contract.
        if (!$found_heading && !$found_text) {
            if (!isset($root['settings']) || !is_array($root['settings'])) $root['settings'] = [];
            self::set_widget_title($root['settings'], $heading_value);
            if ($paragraph_value !== '') self::set_widget_text($root['settings'], $paragraph_value);
        }
    }

    private static function populate_repeatable_widget(&$widget, $item) {
        $settings = isset($widget['settings']) && is_array($widget['settings']) ? $widget['settings'] : [];
        $title = $item['heading'];
        $content = implode("\n\n", $item['content']);

        if (array_key_exists('title_text', $settings)) {
            $settings['title_text'] = $title;
        } elseif (array_key_exists('title', $settings)) {
            $settings['title'] = $title;
        }

        if (array_key_exists('description_text', $settings)) {
            $settings['description_text'] = $content;
        } elseif (array_key_exists('editor', $settings)) {
            $settings['editor'] = wpautop($content);
        } elseif (array_key_exists('text', $settings)) {
            $settings['text'] = $content;
        } elseif (array_key_exists('content', $settings)) {
            $settings['content'] = wpautop($content);
        } elseif (array_key_exists('html', $settings)) {
            $settings['html'] = wpautop($content);
        }

        $widget['settings'] = $settings;
    }

    /**
     * Group repeatable nodes. A repeatable section/container is itself the
     * repeatable unit and therefore forms its own group. Legacy widget-level
     * repeatable markers continue to group by their containing Column so older
     * templates keep their existing card behavior.
     */
    private static function group_repeatable_locations($locations) {
        $groups = [];
        foreach ((array) $locations as $location) {
            $node = $location['node'] ?? [];
            $is_root = is_array($node) && self::is_repeatable_root($node);
            $key = $is_root
                ? 'root:' . serialize($location['node_path'] ?? [])
                : (is_array($location['column_path'] ?? null)
                    ? 'column:' . serialize($location['column_path'])
                    : 'parent:' . serialize($location['parent_path'] ?? []));

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'column_path' => $is_root ? null : ($location['column_path'] ?? null),
                    'is_root' => $is_root,
                    'nodes' => [],
                ];
            }

            $groups[$key]['nodes'][] = [
                'node' => $node,
                'node_path' => $location['node_path'] ?? array_merge($location['parent_path'], [$location['index']]),
                'parent_path' => $location['parent_path'],
            ];
        }

        return array_values($groups);
    }

    private static function clone_repeatable_groups(&$elements, $groups, $needed) {
        if ($needed <= 0 || !$groups) return;

        for ($i = 0; $i < $needed; $i++) {
            $group = $groups[$i % count($groups)];
            foreach ($group['nodes'] as $node_info) {
                $copy = self::deep_clone_element($node_info['node']);
                self::append_to_path($elements, $node_info['parent_path'], $copy);
            }
        }
    }

    private static function remove_repeatable_groups_from_end(&$elements, $remove_count) {
        if ($remove_count <= 0) return;

        $locations = [];
        self::find_repeatable_locations($elements, $locations);
        $groups = self::group_repeatable_locations($locations);
        $groups = array_reverse($groups);

        foreach (array_slice($groups, 0, $remove_count) as $group) {
            foreach (array_reverse($group['nodes']) as $node_info) {
                $parent =& self::get_node_reference($elements, $node_info['parent_path']);
                if ($parent === null || !isset($parent['elements']) || !is_array($parent['elements'])) {
                    unset($parent);
                    continue;
                }
                foreach ($parent['elements'] as $index => $child) {
                    if (($child['id'] ?? '') === ($node_info['node']['id'] ?? '')) {
                        array_splice($parent['elements'], $index, 1);
                        break;
                    }
                }
                unset($parent);
            }
        }
    }

    private static function collect_repeatables(&$elements, &$refs) {
        foreach ($elements as &$el) {
            if (WFEBPG_Template::is_repeatable($el)) {
                $refs[] =& $el;
            }
            if (isset($el['elements']) && is_array($el['elements'])) {
                self::collect_repeatables($el['elements'], $refs);
            }
        }
        unset($el);
    }

    private static function remove_repeatables_from_end(&$elements, $remove_count, &$removed = 0) {
        for ($i = count($elements) - 1; $i >= 0; $i--) {
            if (WFEBPG_Template::is_repeatable($elements[$i])) {
                array_splice($elements, $i, 1);
                $removed++;
                if ($removed >= $remove_count) return true;
                continue;
            }
            if (isset($elements[$i]['elements']) && is_array($elements[$i]['elements'])) {
                if (self::remove_repeatables_from_end($elements[$i]['elements'], $remove_count, $removed)) return true;
            }
        }
        return false;
    }

    /**
     * Clone only the marked repeatable widget. New widgets are inserted into
     * the same parent arrays as the existing repeatable widgets, distributed
     * round-robin so a four-column icon-box grid keeps using its existing columns.
     */
    private static function clone_repeatable_widgets(&$elements, $needed) {
        if ($needed <= 0) return;

        $locations = [];
        self::find_repeatable_locations($elements, $locations);
        if (!$locations) return;

        $templates = [];
        foreach ($locations as $location) {
            $templates[] = [
                'node' => $location['node'],
                'node_path' => $location['node_path'] ?? array_merge($location['parent_path'], [$location['index']]),
                'parent_path' => $location['parent_path'],
                'column_path' => $location['column_path'] ?? null,
                'index' => $location['index'],
            ];
        }

        for ($i = 0; $i < $needed; $i++) {
            $source = $templates[$i % count($templates)]['node'];
            $copy = self::deep_clone_element($source);
            $parent = $templates[$i % count($templates)]['parent_path'];
            self::append_to_path($elements, $parent, $copy);
        }
    }

    private static function find_repeatable_locations(&$elements, &$locations, $parent_path = [], $column_path = null) {
        foreach ($elements as $index => &$el) {
            $current_column_path = $column_path;
            if (($el['elType'] ?? '') === 'column') {
                $current_column_path = array_merge($parent_path, [$index]);
            }

            if (WFEBPG_Template::is_repeatable($el) && !self::is_internal_repeatable_widget($el)) {
                $locations[] = [
                    'node' => $el,
                    'node_path' => array_merge($parent_path, [$index]),
                    'parent_path' => $parent_path,
                    'column_path' => $current_column_path,
                    'index' => $index,
                ];

                // A marked section/container is the complete repeatable unit.
                // Its descendants are intentionally not registered as separate
                // repeatable groups; they are cloned with the parent.
                if (self::is_repeatable_root($el)) {
                    continue;
                }
            }

            if (isset($el['elements']) && is_array($el['elements'])) {
                self::find_repeatable_locations(
                    $el['elements'],
                    $locations,
                    array_merge($parent_path, [$index]),
                    $current_column_path
                );
            }
        }
        unset($el);
    }

    private static function append_to_path(&$root, $path, $copy) {
        $ref =& $root;
        foreach ($path as $index) {
            if (!isset($ref[$index]['elements']) || !is_array($ref[$index]['elements'])) return;
            $ref =& $ref[$index]['elements'];
        }
        $ref[] = $copy;
        unset($ref);
    }

    /**
     * Deep-clone an Elementor element tree. Every native Elementor element ID
     * is replaced with a fresh ID; custom data-customID markers are preserved.
     */
    private static function deep_clone_element($element) {
        if (!is_array($element)) return $element;
        if (isset($element['id'])) $element['id'] = self::new_element_id();
        if (isset($element['elements']) && is_array($element['elements'])) {
            foreach ($element['elements'] as &$child) $child = self::deep_clone_element($child);
            unset($child);
        }
        return $element;
    }

    private static function new_element_id() {
        return substr(str_replace('-', '', wp_generate_uuid4()), 0, 8);
    }

    private static function walk_mutate(&$elements, $callback) {
        foreach ($elements as &$el) {
            $callback($el);
            if (isset($el['elements']) && is_array($el['elements'])) {
                self::walk_mutate($el['elements'], $callback);
            }
        }
        unset($el);
    }

    private static function apply_phone_links(&$elements) {
        foreach ($elements as &$el) {
            if (isset($el['settings']) && is_array($el['settings'])) {
                foreach ($el['settings'] as $key => $value) {
                    if (is_string($value)) $el['settings'][$key] = WFEBPG_Phone_Linker::html($value);
                }
            }
            if (isset($el['elements']) && is_array($el['elements'])) {
                self::apply_phone_links($el['elements']);
            }
        }
        unset($el);
    }

    /**
     * Force Elementor to rebuild the frontend CSS for the generated document.
     * Programmatic _elementor_data writes do not always invalidate Elementor's
     * generated CSS files, which can make a new page appear extremely narrow
     * until the page is opened/saved in Elementor once.
     */
    private static function regenerate_elementor_css($post_id) {
        if (!class_exists('\Elementor\Core\Files\CSS\Post')) return;

        try {
            // Updating this post's CSS file is enough. Clearing Elementor's
            // global cache before/after every generated page is expensive and
            // affects unrelated pages.
            $css_file = new \Elementor\Core\Files\CSS\Post($post_id);
            if (method_exists($css_file, 'update')) {
                $css_file->update();
            }
        } catch (Throwable $e) {
            WFEBPG_Logger::log('Elementor CSS regeneration warning: ' . $e->getMessage(), 'warning');
        }
    }

    private static function count_markers(&$elements) {
        $counts = [
            self::H1_ID => 0,
            self::SECTION_TITLE_ID => 0,
            self::H_ID => 0,
            self::P_ID => 0,
            self::REPEAT_ID => 0,
            self::STEP_ID => 0,
        ];

        self::walk_mutate($elements, function (&$el) use (&$counts) {
            $marker = WFEBPG_Template::marker($el);
            if ($marker['type'] !== '' && isset($counts[$marker['type']])) {
                $counts[$marker['type']]++;
            }
            if (in_array('repeatable', $marker['flags'], true)) {
                $counts[self::REPEAT_ID]++;
            }
        });

        return $counts;
    }


    /**
     * Produce a read-only mapping trace for the Template Validator.
     * Every marked heading shows its hierarchical parent scope, the DOCX block
     * selected for it, and the Elementor element ID that received the mapping.
     * Paragraph markers show which previously mapped heading owns their content.
     */
    private static function build_mapping_debug($elements, $doc, $repeatable_items = [], $internal_repeat_collections = []) {
        $blocks = self::build_structured_doc_blocks($doc);
        $excluded_indices = [];
        foreach ((array) $repeatable_items as $item) {
            if (isset($item['source_index'])) $excluded_indices[(int) $item['source_index']] = true;
        }
        foreach ((array) $internal_repeat_collections as $collection) {
            foreach ((array) ($collection['items'] ?? []) as $item) {
                if (isset($item['source_index'])) $excluded_indices[(int) $item['source_index']] = true;
            }
        }

        $rows = [];
        $heading_cursors = [];
        $active_doc_paths = [];
        $active_blocks = [];

        $walk = function ($nodes) use (&$walk, &$rows, &$blocks, &$excluded_indices, &$heading_cursors, &$active_doc_paths, &$active_blocks) {
            foreach ((array) $nodes as $node) {
                if (!is_array($node)) continue;
                $marker = WFEBPG_Template::marker($node);
                if ($marker['type'] === '') {
                    if (isset($node['elements']) && is_array($node['elements'])) $walk($node['elements']);
                    continue;
                }

                $element_id = (string) ($node['id'] ?? '');
                $widget_type = (string) ($node['widgetType'] ?? ($node['elType'] ?? ''));
                $repeat = WFEBPG_Template::is_repeatable($node);

                if (preg_match('/^h([1-9][0-9]*)$/', $marker['type'], $hm)) {
                    $level = (int) $hm[1];
                    $parent_path = $level > 1 ? (string) ($active_doc_paths[$level - 1] ?? '') : '';

                    if ($repeat) {
                        $candidates = [];
                        foreach ($blocks as $block) {
                            if ((int) ($block['heading_level'] ?? 0) !== $level) continue;
                            if ((string) ($block['parent_path'] ?? '') !== $parent_path) continue;
                            $candidates[] = $block;
                        }
                        $rows[] = [
                            'kind' => 'repeat',
                            'element_id' => $element_id,
                            'widget' => $widget_type,
                            'marker' => $marker['raw'],
                            'level' => 'H' . $level,
                            'parent' => $level > 1 ? 'H' . ($level - 1) : 'ROOT',
                            'scope' => $parent_path !== '' ? $parent_path : 'ROOT',
                            'source' => count($candidates) . ' source item(s)',
                            'status' => count($candidates) ? 'REPEAT' : 'EMPTY',
                        ];
                    } else {
                        $block_index = null;
                        $block = self::next_structured_block_in_scope(
                            $blocks,
                            $heading_cursors,
                            $level,
                            $parent_path,
                            $excluded_indices,
                            $block_index
                        );
                        if ($block !== null) {
                            $active_doc_paths[$level] = (string) ($block['path'] ?? '');
                            $active_blocks[$level] = $block;
                            $active_blocks['latest'] = $block;
                        } else {
                            $active_doc_paths[$level] = $parent_path !== '' ? $parent_path . '.?' : '?';
                            $active_blocks[$level] = null;
                            $active_blocks['latest'] = null;
                        }
                        $rows[] = [
                            'kind' => 'heading',
                            'element_id' => $element_id,
                            'widget' => $widget_type,
                            'marker' => $marker['raw'],
                            'level' => 'H' . $level,
                            'parent' => $level > 1 ? 'H' . ($level - 1) : 'ROOT',
                            'scope' => $parent_path !== '' ? $parent_path : 'ROOT',
                            'source' => $block !== null ? (string) $block['heading'] . ' [' . ($block['path'] ?? '?') . ']' : 'NO MATCH',
                            'status' => $block !== null ? 'MATCH' : 'UNMATCHED',
                        ];
                    }
                } elseif ($marker['type'] === self::P_ID) {
                    $block = $active_blocks['latest'] ?? null;
                    $rows[] = [
                        'kind' => 'paragraph',
                        'element_id' => $element_id,
                        'widget' => $widget_type,
                        'marker' => $marker['raw'],
                        'level' => 'P',
                        'parent' => $block ? 'H' . (int) ($block['heading_level'] ?? 0) : 'NONE',
                        'scope' => $block['path'] ?? 'NONE',
                        'source' => $block ? (string) $block['heading'] : 'NO ACTIVE HEADING',
                        'status' => ($block && !empty($block['content'])) ? 'MATCH' : 'EMPTY',
                    ];
                } elseif ($repeat) {
                    $rows[] = [
                        'kind' => 'repeat',
                        'element_id' => $element_id,
                        'widget' => $widget_type,
                        'marker' => $marker['raw'],
                        'level' => strtoupper($marker['type']),
                        'parent' => '—',
                        'scope' => '—',
                        'source' => 'Internal/legacy repeatable',
                        'status' => 'REPEAT',
                    ];
                }

                if (isset($node['elements']) && is_array($node['elements'])) $walk($node['elements']);
            }
        };
        $walk($elements);

        return $rows;
    }

    /**
     * Analyze an Elementor template before generation. Used by the Template
     * Validator admin screen and intentionally kept read-only.
     */
    public static function validate_template_json($json, $doc = null) {
        $elements = WFEBPG_Template::decode($json);
        $counts = self::count_markers($elements);
        $repeat_sections = 0;
        $repeat_columns = 0;
        $image_locations = 0;
        $repeat_image_locations = 0;

        self::analyze_elements($elements, $repeat_sections, $repeat_columns, $image_locations, $repeat_image_locations);

        $result = [
            'counts' => $counts,
            'repeat_sections' => $repeat_sections,
            'repeat_columns' => $repeat_columns,
            'image_locations' => $image_locations,
            'repeat_image_locations' => $repeat_image_locations,
            'doc' => null,
            'warnings' => [],
            'errors' => [],
            'mapping_debug' => [],
        ];

        if ($doc !== null) {
            $result['doc'] = [
                'h1' => 0,
                'h2' => 0,
                'h3' => 0,
                'paragraphs' => 0,
                'legacy_yellow_repeatables' => count($doc['repeatables'] ?? []),
            ];
            foreach (self::build_structured_doc_blocks($doc) as $block) {
                $level = (int) ($block['heading_level'] ?? 0);
                $key = 'h' . $level;
                if (!isset($result['doc'][$key])) $result['doc'][$key] = 0;
                $result['doc'][$key]++;
                $result['doc']['paragraphs'] += count((array) ($block['content'] ?? []));
            }

            if ($counts[self::H1_ID] < 1 && $result['doc']['h1']) {
                $result['errors'][] = 'DOCX contains an H1, but the template has no h1 marker.';
            }
            if ($result['doc']['legacy_yellow_repeatables'] > 0 && $counts[self::REPEAT_ID] < 1) {
                $result['errors'][] = 'DOCX contains legacy yellow repeatable headings, but the template has no repeatable marker.';
            }

            $repeatable_levels = WFEBPG_Template::repeatable_heading_levels($elements);
            if (count($repeatable_levels) === 1) {
                $level = (int) $repeatable_levels[0];
                if (empty($result['doc']['h' . $level])) {
                    $result['warnings'][] = 'The template marks H' . $level . ' as repeatable, but this DOCX contains no H' . $level . ' headings.';
                }
            } elseif (count($repeatable_levels) > 1) {
                $boundary = (int) min($repeatable_levels);
                $deeper = array_values(array_filter($repeatable_levels, function ($level) use ($boundary) {
                    return (int) $level > $boundary;
                }));
                if ($deeper) {
                    $result['warnings'][] = 'H' . $boundary . ' is the repeatable boundary; deeper repeatable markers H' . implode(', H', $deeper) . ' will be populated inside each H' . $boundary . ' item.';
                }
            }

            if ($result['doc']['h2'] > 0 && $counts[self::SECTION_TITLE_ID] < 1 && !$repeatable_levels) {
                $result['warnings'][] = 'DOCX contains H2 sections, but the template has no h2 marker.';
            }
            if ($result['doc']['h3'] > 0 && $counts[self::H_ID] < 1 && $counts[self::P_ID] < 1) {
                $result['warnings'][] = 'DOCX contains H3 content, but the template has no h3 or p marker.';
            }

            $debug_repeatables = self::build_repeatable_items($doc, $elements);
            $debug_internal = self::build_internal_repeatable_items($doc, $elements);
            $result['mapping_debug'] = self::build_mapping_debug($elements, $doc, $debug_repeatables, $debug_internal);
        }

        if ($repeat_sections > 0 && $repeat_image_locations > 0) {
            $result['warnings'][] = 'Repeatable cards contain image/background settings. An Image Pool can randomize those images and prevent duplicates within each generated section.';
        }
        if ($counts[self::REPEAT_ID] > 0 && $repeat_sections === 0) {
            $result['warnings'][] = 'Repeatable markers were found, but no containing Elementor section/container was detected. Element-level cloning will be used.';
        }

        return $result;
    }

    private static function analyze_elements($elements, &$repeat_sections, &$repeat_columns, &$image_locations, &$repeat_image_locations, $inside_repeat_section = false) {
        foreach ((array) $elements as $el) {
            $is_repeat_section = (($el['elType'] ?? '') === 'section' && WFEBPG_Template::is_repeatable($el));
            if ($is_repeat_section) $repeat_sections++;
            $custom = WFEBPG_Template::custom_id($el);
            $has_image = false;
            $settings = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : [];
            foreach (['background_image','background_image_mobile','image'] as $key) {
                if (!empty($settings[$key]) && is_array($settings[$key])) {
                    $has_image = true;
                    $image_locations++;
                    if ($inside_repeat_section || $is_repeat_section) $repeat_image_locations++;
                }
            }
            if (($el['elType'] ?? '') === 'column' && $inside_repeat_section && $has_image) $repeat_columns++;
            $child_inside = $inside_repeat_section || $is_repeat_section;
            if (isset($el['elements']) && is_array($el['elements'])) {
                self::analyze_elements($el['elements'], $repeat_sections, $repeat_columns, $image_locations, $repeat_image_locations, $child_inside);
            }
        }
    }

    public static function generate($job) {
        $doc = WFEBPG_DOCX_Reader::read($job['docx']);
        $json = file_get_contents($job['template']);
        if ($json === false) throw new Exception('Unable to read Elementor JSON template.');

        $elements = WFEBPG_Template::decode($json);
        $page_settings = WFEBPG_Template::page_settings($json);
        // The supplied raw Elementor export has an empty page_settings array.
        // Use Elementor's header/footer page layout so WordPress does not place
        // the generated Elementor content inside the theme's narrow post column.
        if (empty($page_settings)) {
            $page_settings = ['page_layout' => 'elementor_header_footer'];
        }
        $template_counts = self::count_markers($elements);

        WFEBPG_Logger::log(
            'Mapping DOCX: ' . count($doc['items']) . ' paragraphs, ' .
            count($doc['repeatables']) . ' legacy yellow repeatable headings. Template markers: ' .
            'h1=' . $template_counts[self::H1_ID] . ', ' .
            'sectionTitle=' . $template_counts[self::SECTION_TITLE_ID] . ', ' .
            'h=' . $template_counts[self::H_ID] . ', ' .
            'p=' . $template_counts[self::P_ID] . ', ' .
            'repeatable=' . $template_counts[self::REPEAT_ID] . '.'
        );

        // Build an independent DOCX collection for every modern repeatable
        // Elementor region. This is important when one template contains
        // Services, Process, FAQ, or other repeatable sections: each region
        // must start its own cursor instead of consuming the previous region's
        // records.
        $repeatable_collections = self::build_repeatable_collections($doc, $elements);
        $repeatables = $repeatable_collections
            ? self::flatten_repeatable_collections($repeatable_collections)
            : array_values((array) ($doc['repeatables'] ?? []));

        $internal_repeat_collections = self::build_internal_repeatable_items($doc, $elements);
        $nonrepeat_exclusions = $repeatables;
        foreach ($internal_repeat_collections as $collection) {
            $nonrepeat_exclusions = array_merge($nonrepeat_exclusions, (array) ($collection['items'] ?? []));
        }

        // One template handles every document. The template explicitly declares
        // which elements may receive content; everything else is left alone.
        self::populate_nonrepeat($elements, $doc, $nonrepeat_exclusions);

        // A Text Editor marked h3|p|repeat is an inline document stream, not a
        // repeatable Elementor section. Populate the one editor with every
        // H3/P record in its heading scope and remove its repeat marker so the
        // section-expansion code cannot clone the surrounding Elementor layout.
        self::populate_inline_repeat_text_editors($elements, $doc);

        $image_pool = !empty($job['image_pool_ids'])
            ? array_values(array_unique(array_map('absint', (array) $job['image_pool_ids'])))
            : [];

        // New marker-driven repeatables use ordinary DOCX headings. A marker
        // such as data-customID|h2|repeatable makes each H2 a repeatable item.
        // Yellow headings remain supported as a legacy fallback.
        if ($repeatables) {
            if (!$template_counts[self::REPEAT_ID]) {
                throw new Exception('The DOCX contains repeatable content, but the Elementor template has no repeatable marker.');
            }

            $repeatable_source = $repeatable_collections ?: $repeatables;

            self::populate_repeatables(
                $elements,
                $repeatable_source,
                absint($job['widgets_per_section'] ?? 0),
                $image_pool,
                $doc
            );
        }

        self::apply_phone_links($elements);

        $title = self::clean_filename((string)($job['page_title'] ?? basename($job['docx'])));
        if ($title === '') throw new Exception('Could not derive a page title from filename.');
        $slug = self::slug($title);

        $existing = get_page_by_path($slug, OBJECT, 'page');
        if ($existing && empty($job['overwrite'])) {
            $slug .= '-' . wp_generate_password(4, false, false);
        }

        $post = [
            'post_title' => $title,
            'post_name' => $slug,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_parent' => absint($job['parent'] ?? 0),
            'post_content' => '',
        ];

        $was_overwrite = false;
        if ($existing && !empty($job['overwrite'])) {
            $post['ID'] = $existing->ID;
            $id = wp_update_post(wp_slash($post), true);
            $was_overwrite = true;
        } else {
            $id = wp_insert_post(wp_slash($post), true);
        }

        if (is_wp_error($id)) throw new Exception('Page creation failed: ' . $id->get_error_message());

        $elementor_json = WFEBPG_Template::encode($elements);

        // Keep the generated page in Elementor's full content area while
        // retaining the site's normal header/footer.
        update_post_meta($id, '_wp_page_template', 'elementor_header_footer');
        update_post_meta($id, '_elementor_page_template', 'header-footer');

        update_post_meta($id, '_elementor_edit_mode', 'builder');
        update_post_meta($id, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '');
        update_post_meta($id, '_elementor_data', wp_slash($elementor_json));
        if (!empty($page_settings)) {
            update_post_meta($id, '_elementor_page_settings', $page_settings);
        } else {
            delete_post_meta($id, '_elementor_page_settings');
        }

        if (class_exists('\Elementor\Plugin')) {
            try {
                $document = \Elementor\Plugin::$instance->documents->get($id);
                if ($document) {
                    $save_data = ['elements' => $elements];
                    if (!empty($page_settings)) $save_data['settings'] = $page_settings;
                    $document->save($save_data);

                    // Elementor may normalize/re-save document data during
                    // document->save(). Re-write the generated JSON afterward
                    // so DOCX-mapped content remains authoritative.
                    update_post_meta($id, '_elementor_data', wp_slash($elementor_json));
                    if (!empty($page_settings)) update_post_meta($id, '_elementor_page_settings', $page_settings);
                }
            } catch (Throwable $e) {
                WFEBPG_Logger::log('Elementor document save fallback used: ' . $e->getMessage(), 'warning');
                update_post_meta($id, '_elementor_data', wp_slash($elementor_json));
            }
        }

        // Rebuild Elementor's generated CSS after the final JSON is in place.
        // This prevents the page from relying on a stale/missing CSS file until
        // someone manually opens the page in Elementor.
        self::regenerate_elementor_css($id);

        // Mark only pages that were actually created by this plugin. Pages that
        // were deliberately overwritten are tracked for review but are never
        // automatically deleted by the Reset button.
        update_post_meta($id, '_wfebpg_generated', $was_overwrite ? '0' : '1');

        clean_post_cache($id);
        wp_cache_delete($id, 'post_meta');

        $created_pages = get_option('wfebpg_created_pages', []);
        array_unshift($created_pages, [
            'id' => (int) $id,
            'title' => $title,
            'time' => current_time('mysql'),
            'plugin_created' => $was_overwrite ? 0 : 1,
        ]);
        update_option('wfebpg_created_pages', array_slice($created_pages, 0, 300), false);

        WFEBPG_Logger::log('Generated page #' . $id . ' — ' . $title, 'success');
        return $id;
    }
}
