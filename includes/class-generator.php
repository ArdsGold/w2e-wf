<?php
if (!defined('ABSPATH')) exit;

class WFEBPG_Generator {
    const H1_ID = 'h1NonRepeat';
    const SECTION_TITLE_ID = 'sectionTitleNonRepeat';
    const H_ID  = 'hNonRepeat';
    const P_ID  = 'pNonRepeat';
    const REPEAT_ID = 'repeatableItem';
    const STEP_ID = 'stepNumber';

    public static function clean_filename($name) {
        $name = pathinfo($name, PATHINFO_FILENAME);
        $name = preg_replace('/[\s._+\-]+$/u', '', $name);
        $name = preg_replace('/[._+\-]+/u', ' ', $name);
        $name = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name);
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    public static function slug($title) { return sanitize_title($title); }

    public static function enqueue($args) {
        $q = get_option('wfebpg_queue', []);
        $q[] = $args;
        update_option('wfebpg_queue', $q, false);

        // Schedule a near-immediate single event as well as the recurring
        // worker. This helps the queue start promptly instead of waiting for
        // the next minute tick. WordPress still requires WP-Cron to be
        // triggered by traffic or a real cron request.
        if (!wp_next_scheduled('wfebpg_process_queue')) {
            wp_schedule_single_event(time() + 5, 'wfebpg_process_queue');
        }
    }

    public static function process_queue() {
        $q = get_option('wfebpg_queue', []);
        if (!$q) return;

        $job = array_shift($q);
        update_option('wfebpg_queue', $q, false);

        try {
            self::generate($job);
        } catch (Throwable $e) {
            WFEBPG_Logger::log($e->getMessage(), 'error');
        }
    }

    /**
     * Populate all non-repeat widgets using the custom IDs in the template.
     * Mapping is based on occurrence order in the DOCX:
     * h1NonRepeat -> first non-yellow heading
     * hNonRepeat  -> subsequent non-yellow headings
     * pNonRepeat  -> paragraph/content blocks in document order
     */
    /**
     * Populate non-repeat content according to the semantic order of the DOCX.
     *
     * The template uses pNonRepeat for several different Elementor widgets:
     * - Text Editor: body text belonging to the most recently mapped heading.
     * - Icon Box: one heading + its body paragraph.
     * - Toggle: multiple heading/body pairs (FAQ entries).
     *
     * h1NonRepeat/hNonRepeat consume heading blocks in order. pNonRepeat
     * widgets then consume the appropriate block(s), preventing paragraphs
     * from being shifted or duplicated merely because the DOCX contains many
     * headings between paragraph blocks.
     */
    private static function populate_nonrepeat(&$elements, $doc) {
        $blocks = !empty($doc['nonrepeat_blocks']) && is_array($doc['nonrepeat_blocks'])
            ? $doc['nonrepeat_blocks']
            : self::legacy_nonrepeat_blocks($doc);

        $cursor = 0;
        $last_block = null;

        self::walk_mutate($elements, function (&$el) use (&$blocks, &$cursor, &$last_block) {
            $custom_id = WFEBPG_Template::custom_id($el);
            if ($custom_id === self::REPEAT_ID) return;

            $settings = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : [];
            $widget_type = isset($el['widgetType']) ? (string) $el['widgetType'] : '';

            if ($custom_id === self::H1_ID) {
                // H1 markers map specifically to a level-1 DOCX heading.
                $block = self::next_block_by_heading_level($blocks, $cursor, 1);
                if ($block !== null) {
                    self::set_widget_title($settings, $block['heading']);
                    $last_block = $block;
                }
            } elseif ($custom_id === self::SECTION_TITLE_ID) {
                // Major section-title markers map specifically to level-2 DOCX
                // headings, keeping them separate from normal H3 headings.
                $block = self::next_block_by_heading_level($blocks, $cursor, 2);
                if ($block !== null) {
                    self::set_widget_title($settings, $block['heading']);
                    $last_block = $block;
                }
            } elseif ($custom_id === self::H_ID) {
                // Normal heading markers map to level-3 DOCX headings in the
                // local-SEO document structure used by Wolf Forge.
                $block = self::next_block_by_heading_level($blocks, $cursor, 3);
                if ($block !== null) {
                    self::set_widget_title($settings, $block['heading']);
                    $last_block = $block;
                }
            } elseif ($custom_id === self::P_ID) {
                if ($widget_type === 'icon-box') {
                    $block = self::next_block($blocks, $cursor);
                    if ($block !== null) {
                        self::set_icon_box_content($settings, $block);
                        $last_block = $block;
                    }
                } elseif ($widget_type === 'toggle') {
                    self::set_toggle_content($settings, $blocks, $cursor);
                } else {
                    // Text Editor content belongs to the last mapped heading.
                    // If no suitable block is active, find the next block with
                    // body content rather than consuming an unrelated heading.
                    $block = $last_block;
                    if ($block === null || empty($block['content'])) {
                        $block = self::next_block_with_content($blocks, $cursor);
                        if ($block !== null) $last_block = $block;
                    }
                    if ($block !== null && !empty($block['content'])) {
                        self::set_widget_text($settings, implode("\n\n", $block['content']));
                    }
                }
            }

            $el['settings'] = $settings;
        });
    }

    /**
     * Generic mode deliberately does not depend on yellow repeatable headings.
     * It uses the DOCX's non-yellow content blocks and maps them to the marked
     * Elementor widgets in document/template order. This makes Generic mode
     * useful even when the DOCX has no repeatable markers.
     */
    /**
     * Generic mode maps the DOCX by its actual H1/H2/H3 hierarchy.
     *
     * pNonRepeat is intentionally reused for different widget types:
     * Text Editor = body for the most recently mapped heading,
     * Icon Box = the next H3 + its body inside the current H2,
     * Toggle = the remaining H3 + body pairs inside the current H2.
     */
    private static function populate_generic(&$elements, $doc) {
        $blocks = !empty($doc['nonrepeat_blocks']) && is_array($doc['nonrepeat_blocks'])
            ? array_values($doc['nonrepeat_blocks'])
            : [];

        if (!$blocks) {
            $blocks = [];
            foreach (($doc['items'] ?? []) as $item) {
                if (!empty($item['repeatable'])) continue;
                if (!empty($item['heading'])) {
                    $blocks[] = [
                        'heading' => $item['text'],
                        'heading_level' => (int) ($item['heading_level'] ?? 0),
                        'content' => [],
                    ];
                } elseif (!empty($blocks)) {
                    $last = count($blocks) - 1;
                    $blocks[$last]['content'][] = $item['text'];
                }
            }
        }

        // Build one H1 block plus ordered H2 groups. Every H3 belongs to the
        // H2 immediately before it. This mirrors the structure of the DOCX.
        $h1_block = null;
        $groups = [];
        $current_group = null;

        foreach ($blocks as $block) {
            $level = (int) ($block['heading_level'] ?? 0);

            if ($level === 1) {
                if ($h1_block === null) $h1_block = $block;
                continue;
            }

            if ($level === 2) {
                if ($current_group !== null) $groups[] = $current_group;
                $current_group = [
                    'heading' => $block['heading'],
                    'heading_level' => 2,
                    'content' => $block['content'] ?? [],
                    'h3' => [],
                ];
                continue;
            }

            if ($level === 3 && $current_group !== null) {
                $current_group['h3'][] = $block;
                continue;
            }

            if ($current_group !== null && !empty($block['content'])) {
                foreach ($block['content'] as $body) {
                    $current_group['content'][] = $body;
                }
            }
        }

        if ($current_group !== null) $groups[] = $current_group;

        $group_cursor = 0;
        $h3_cursors = [];
        foreach ($groups as $i => $_group) $h3_cursors[$i] = 0;

        $current_group_index = null;
        $last_block = $h1_block;
        $fallback_h3 = [];

        self::walk_mutate($elements, function (&$el) use (
            &$groups,
            &$group_cursor,
            &$h3_cursors,
            &$current_group_index,
            &$last_block,
            &$h1_block,
            &$fallback_h3
        ) {
            $custom_id = WFEBPG_Template::custom_id($el);
            if ($custom_id === self::REPEAT_ID) return;

            $settings = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : [];
            $widget_type = isset($el['widgetType']) ? (string) $el['widgetType'] : '';

            if ($custom_id === self::H1_ID) {
                if ($h1_block !== null && !empty($h1_block['heading'])) {
                    self::set_widget_title($settings, $h1_block['heading']);
                    $last_block = $h1_block;
                }
            } elseif ($custom_id === self::SECTION_TITLE_ID) {
                if (isset($groups[$group_cursor])) {
                    $current_group_index = $group_cursor;
                    $group = $groups[$group_cursor];
                    $group_cursor++;

                    self::set_widget_title($settings, $group['heading']);
                    $last_block = $group;
                }
            } elseif ($custom_id === self::H_ID) {
                $block = null;

                if ($current_group_index !== null && isset($groups[$current_group_index])) {
                    $idx = $h3_cursors[$current_group_index] ?? 0;
                    if (isset($groups[$current_group_index]['h3'][$idx])) {
                        $block = $groups[$current_group_index]['h3'][$idx];
                        $h3_cursors[$current_group_index] = $idx + 1;
                    }
                }

                if ($block === null) {
                    if (!$fallback_h3) {
                        foreach ($groups as $group) {
                            foreach ($group['h3'] as $h3) $fallback_h3[] = $h3;
                        }
                    }
                    if ($fallback_h3) $block = array_shift($fallback_h3);
                }

                if ($block !== null) {
                    self::set_widget_title($settings, $block['heading']);
                    $last_block = $block;
                }
            } elseif ($custom_id === self::P_ID) {
                if ($widget_type === 'icon-box') {
                    $block = null;

                    if ($current_group_index !== null && isset($groups[$current_group_index])) {
                        $idx = $h3_cursors[$current_group_index] ?? 0;
                        if (isset($groups[$current_group_index]['h3'][$idx])) {
                            $block = $groups[$current_group_index]['h3'][$idx];
                            $h3_cursors[$current_group_index] = $idx + 1;
                        }
                    }

                    if ($block === null) {
                        if (!$fallback_h3) {
                            foreach ($groups as $group) {
                                foreach ($group['h3'] as $h3) $fallback_h3[] = $h3;
                            }
                        }
                        if ($fallback_h3) $block = array_shift($fallback_h3);
                    }

                    if ($block !== null) {
                        self::set_icon_box_content($settings, $block);
                        $last_block = $block;
                    }
                } elseif ($widget_type === 'toggle') {
                    if ($current_group_index !== null && isset($groups[$current_group_index])) {
                        $group = $groups[$current_group_index];
                        $idx = $h3_cursors[$current_group_index] ?? 0;

                        if (isset($settings['tabs']) && is_array($settings['tabs'])) {
                            foreach ($settings['tabs'] as &$tab) {
                                if (!isset($group['h3'][$idx])) break;

                                $faq = $group['h3'][$idx];
                                $h3_cursors[$current_group_index] = ++$idx;

                                if (is_array($tab)) {
                                    $tab['tab_title'] = $faq['heading'];
                                    $tab['tab_content'] = wpautop(implode("\n\n", $faq['content'] ?? []));
                                }
                            }
                            unset($tab);
                        }
                    }
                } else {
                    // Text editors inherit the body from the most recently
                    // mapped heading. This handles hero, process, and closing
                    // paragraphs without a global paragraph cursor.
                    if ($last_block !== null && !empty($last_block['content'])) {
                        self::set_widget_text($settings, implode("\n\n", $last_block['content']));
                    }
                }
            }

            $el['settings'] = $settings;
        });
    }

    private static function legacy_nonrepeat_blocks($doc) {
        $blocks = [];
        $current = null;
        foreach (($doc['items'] ?? []) as $item) {
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

    private static function next_block_by_heading_level(&$blocks, &$cursor, $level) {
        $count = count($blocks);
        for ($i = $cursor; $i < $count; $i++) {
            if ((int) ($blocks[$i]['heading_level'] ?? 0) !== (int) $level) continue;
            $cursor = $i + 1;
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

    private static function set_icon_box_content(&$settings, $block) {
        $settings['title_text'] = $block['heading'];
        $settings['description_text'] = implode("\n\n", $block['content']);
    }

    private static function set_toggle_content(&$settings, &$blocks, &$cursor) {
        if (!isset($settings['tabs']) || !is_array($settings['tabs'])) return;

        foreach ($settings['tabs'] as $index => &$tab) {
            $block = self::next_block($blocks, $cursor);
            if ($block === null) break;

            if (is_array($tab)) {
                $tab['tab_title'] = $block['heading'];
                $tab['tab_content'] = wpautop(implode("\n\n", $block['content']));
            }
        }
        unset($tab);
    }

    private static function set_widget_title(&$settings, $value) {
        if (array_key_exists('title', $settings)) {
            $settings['title'] = $value;
        } elseif (array_key_exists('title_text', $settings)) {
            $settings['title_text'] = $value;
        }
    }

    private static function set_widget_text(&$settings, $value) {
        $html = wpautop($value);
        if (array_key_exists('editor', $settings)) {
            $settings['editor'] = $html;
        } elseif (array_key_exists('description_text', $settings)) {
            $settings['description_text'] = wp_strip_all_tags($value);
        } elseif (array_key_exists('text', $settings)) {
            $settings['text'] = $value;
        } elseif (array_key_exists('content', $settings)) {
            $settings['content'] = $html;
        } elseif (array_key_exists('html', $settings)) {
            $settings['html'] = $html;
        }
    }

    /**
     * Yellow DOCX headings are the actual repeatable content boundaries.
     * Each yellow heading starts one item; all following non-heading paragraphs
     * belong to that item until the next yellow heading.
     *
     * repeatableItem is treated as the actual marked Elementor widget. The
     * widget itself is cloned/populated; its parent column/container is never
     * cloned merely to create another item.
     */
    private static function populate_repeatables(&$elements, $repeatables, $widgets_per_section = 0) {
        if (!$repeatables) return;

        // When a section contains repeatableItem widgets, use that whole
        // section as the visual prototype and clone the section when the
        // configured widget limit is reached. The expansion pass also tells
        // us whether a repeatable section was found, so we do not traverse the
        // entire Elementor tree twice.
        if ($widgets_per_section > 0 && self::expand_repeatable_sections($elements, $repeatables, $widgets_per_section)) {
            return;
        }

        // Backward-compatible fallback: if no repeatable section is found,
        // clone the marked widgets themselves.
        $refs = [];
        self::collect_repeatables($elements, $refs);
        $existing = count($refs);
        $wanted = count($repeatables);

        if ($existing === 0 && $wanted > 0) {
            throw new Exception('Unique template contains no data-customID|repeatableItem widget.');
        }

        if ($wanted < $existing) {
            self::remove_repeatables_from_end($elements, $existing - $wanted);
        } elseif ($wanted > $existing) {
            self::clone_repeatable_widgets($elements, $wanted - $existing);
        }

        $refs = [];
        self::collect_repeatables($elements, $refs);
        foreach ($refs as $i => &$widget) {
            if (isset($repeatables[$i])) self::populate_repeatable_widget($widget, $repeatables[$i]);
        }
        unset($widget);
    }

    private static function section_repeatable_locations($section) {
        $locations = [];
        if (!is_array($section) || !isset($section['elements']) || !is_array($section['elements'])) return $locations;
        self::find_repeatable_locations($section['elements'], $locations);
        return $locations;
    }

    /**
     * Replace every section containing repeatableItem widgets with one or more
     * cloned sections. Each generated section receives at most
     * $widgets_per_section marked widgets. The source widgets are cloned in
     * round-robin order so an existing four-card design can preserve its four
     * visual prototypes. The widget's parent Column is preserved by appending
     * the cloned widget back into that same relative parent inside the cloned
     * section.
     */
    private static function expand_repeatable_sections(&$elements, $repeatables, $widgets_per_section) {
        $cursor = 0;
        $found_section = self::expand_repeatable_sections_recursive(
            $elements,
            $repeatables,
            $widgets_per_section,
            $cursor
        );

        if ($found_section && $cursor < count($repeatables)) {
            throw new Exception('The template does not contain enough repeatable section capacity for all yellow DOCX headings.');
        }

        return $found_section;
    }

    private static function expand_repeatable_sections_recursive(&$elements, $repeatables, $limit, &$cursor) {
        $found_section = false;
        $element_count = count($elements);

        for ($i = 0; $i < $element_count; $i++) {
            if (($elements[$i]['elType'] ?? '') === 'section') {
                $locations = self::section_repeatable_locations($elements[$i]);
                if ($locations) {
                    $found_section = true;
                    $remaining = count($repeatables) - $cursor;
                    if ($remaining <= 0) {
                        self::remove_all_repeatables($elements[$i]['elements']);
                        continue;
                    }

                    $prototype = $elements[$i];
                    $prototype_widgets = [];
                    foreach ($locations as $location) {
                        $prototype_widgets[] = [
                            'node' => $location['node'],
                            'parent_path' => $location['parent_path'],
                            'column_path' => $location['column_path'] ?? null,
                        ];
                    }

                    $prototype_color_profile = self::repeatable_color_profile(
                        $prototype,
                        $prototype_widgets
                    );

                    $prototype_count = count($prototype_widgets);
                    if ($prototype_count === 0) continue;

                    // Resolve Media Library attachments once per generation
                    // rather than repeating WordPress lookups per section.
                    $prepared_image_pool = self::prepare_image_pool(
                        $GLOBALS['wfebpg_active_image_pool'] ?? []
                    );

                    $section_count = (int) ceil($remaining / $limit);
                    $replacement = [];

                    for ($section_index = 0; $section_index < $section_count; $section_index++) {
                        $section = self::deep_clone_element($prototype);
                        self::remove_all_repeatables($section['elements']);

                        $chunk_count = min($limit, count($repeatables) - $cursor);
                        $section_images = self::random_image_pool(
                            $prepared_image_pool,
                            $chunk_count
                        );

                        // Center the cards when the final section is only partially filled.
                        // With four prototype columns and two remaining cards, for example,
                        // use columns 2 and 3 instead of leaving them at the far left.
                        $source_offset = 0;
                        if ($chunk_count < $limit && $chunk_count < $prototype_count) {
                            $source_offset = (int) floor(($prototype_count - $chunk_count) / 2);
                        }

                        for ($j = 0; $j < $chunk_count; $j++) {
                            $source_index = $chunk_count < $limit
                                ? ($source_offset + $j) % $prototype_count
                                : ($section_index * $limit + $j) % $prototype_count;
                            $source = $prototype_widgets[$source_index];
                            $copy = self::deep_clone_element($source['node']);
                            self::populate_repeatable_widget($copy, $repeatables[$cursor]);

                            $column_path = $source['column_path'];
                            if ($column_path !== null) {
                                // Image assignment and color assignment share
                                // one path traversal for the target column.
                                self::apply_repeatable_card_visuals(
                                    $section['elements'],
                                    $column_path,
                                    $section_images[$j] ?? null,
                                    $prototype_color_profile,
                                    $j,
                                    $section_index
                                );
                            }

                            self::append_to_path($section['elements'], $source['parent_path'], $copy);
                            $cursor++;
                        }

                        if ($chunk_count < $limit) {
                            self::remove_unused_repeatable_columns(
                                $section,
                                $prototype_widgets,
                                $chunk_count,
                                $source_offset
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
                    $cursor
                )) {
                    $found_section = true;
                }
            }
        }

        return $found_section;
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

    private static function remove_all_repeatables(&$elements) {
        if (!is_array($elements)) return;
        for ($i = count($elements) - 1; $i >= 0; $i--) {
            if (WFEBPG_Template::custom_id($elements[$i]) === self::REPEAT_ID) {
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
    private static function repeatable_color_profile($section, $prototype_widgets) {
        $profile = [];

        foreach ((array) $prototype_widgets as $source) {
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

    private static function collect_repeatables(&$elements, &$refs) {
        foreach ($elements as &$el) {
            if (WFEBPG_Template::custom_id($el) === self::REPEAT_ID) {
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
            if (WFEBPG_Template::custom_id($elements[$i]) === self::REPEAT_ID) {
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

            if (WFEBPG_Template::custom_id($el) === self::REPEAT_ID) {
                $locations[] = [
                    'node' => $el,
                    'parent_path' => $parent_path,
                    'column_path' => $current_column_path,
                    'index' => $index,
                ];
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
            if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager) && method_exists(\Elementor\Plugin::$instance->files_manager, 'clear_cache')) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }
            $css_file = new \Elementor\Core\Files\CSS\Post($post_id);
            if (method_exists($css_file, 'update')) {
                $css_file->update();
            }
        } catch (Throwable $e) {
            WFEBPG_Logger::log('Elementor CSS regeneration warning: ' . $e->getMessage(), 'warning');
        }

        if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            try {
                if (method_exists(\Elementor\Plugin::$instance->files_manager, 'clear_cache')) {
                    \Elementor\Plugin::$instance->files_manager->clear_cache();
                }
            } catch (Throwable $e) {
                WFEBPG_Logger::log('Elementor cache clear warning: ' . $e->getMessage(), 'warning');
            }
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
            $id = WFEBPG_Template::custom_id($el);
            if (isset($counts[$id])) $counts[$id]++;
        });

        return $counts;
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
        ];

        if ($doc !== null) {
            $result['doc'] = [
                'h1' => 0,
                'h2' => 0,
                'h3' => 0,
                'repeatables' => count($doc['repeatables'] ?? []),
            ];
            foreach (($doc['nonrepeat_blocks'] ?? []) as $block) {
                $level = (int) ($block['heading_level'] ?? 0);
                if ($level === 1) $result['doc']['h1']++;
                elseif ($level === 2) $result['doc']['h2']++;
                elseif ($level === 3) $result['doc']['h3']++;
            }

            if ($counts[self::H1_ID] < 1 && $result['doc']['h1']) {
                $result['errors'][] = 'DOCX contains an H1, but the template has no h1NonRepeat marker.';
            }
            if ($result['doc']['repeatables'] > 0 && $counts[self::REPEAT_ID] < 1) {
                $result['errors'][] = 'DOCX contains yellow repeatable headings, but the template has no repeatableItem marker.';
            }
            if ($result['doc']['h2'] > 0 && $counts[self::SECTION_TITLE_ID] < 1) {
                $result['warnings'][] = 'DOCX contains H2 sections, but the template has no sectionTitleNonRepeat marker.';
            }
            if ($result['doc']['h3'] > 0 && $counts[self::H_ID] < 1 && $counts[self::P_ID] < 1) {
                $result['warnings'][] = 'DOCX contains H3 content, but the template has no hNonRepeat or pNonRepeat markers to receive it.';
            }
        }

        if ($repeat_sections > 0 && $repeat_image_locations > 0) {
            $result['warnings'][] = 'Repeatable cards contain image/background settings. An Image Pool can randomize those images and prevent duplicates within each generated section.';
        }
        if ($counts[self::REPEAT_ID] > 0 && $repeat_sections === 0) {
            $result['warnings'][] = 'repeatableItem markers were found, but no containing Elementor section was detected. Widget-level cloning will be used.';
        }

        return $result;
    }

    private static function analyze_elements($elements, &$repeat_sections, &$repeat_columns, &$image_locations, &$repeat_image_locations, $inside_repeat_section = false) {
        foreach ((array) $elements as $el) {
            $is_repeat_section = (($el['elType'] ?? '') === 'section' && !empty(self::section_repeatable_locations($el)));
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
            count($doc['repeatables']) . ' yellow repeatable headings. Template markers: ' .
            'h1=' . $template_counts[self::H1_ID] . ', ' .
            'sectionTitle=' . $template_counts[self::SECTION_TITLE_ID] . ', ' .
            'h=' . $template_counts[self::H_ID] . ', ' .
            'p=' . $template_counts[self::P_ID] . ', ' .
            'repeatable=' . $template_counts[self::REPEAT_ID] . '.'
        );

        if (($job['mode'] ?? 'generic') === 'generic') {
            self::populate_generic($elements, $doc);
        } else {
            self::populate_nonrepeat($elements, $doc);
        }

        // The selected Media Library pool is scoped to this generation job.
        // It is consumed only by repeatable sections; non-repeatable hero/section
        // backgrounds remain exactly as defined by the template.
        $GLOBALS['wfebpg_active_image_pool'] = !empty($job['image_pool_ids'])
            ? array_values(array_unique(array_map('absint', (array) $job['image_pool_ids'])))
            : [];

        if (($job['mode'] ?? 'generic') === 'unique') {
            if (!$template_counts[self::REPEAT_ID] && !empty($doc['repeatables'])) {
                throw new Exception('DOCX contains ' . count($doc['repeatables']) . ' yellow repeatable headings, but the Elementor template contains no data-customID|repeatableItem widgets.');
            }

            self::populate_repeatables(
                $elements,
                $doc['repeatables'],
                absint($job['widgets_per_section'] ?? 0)
            );
        }

        self::apply_phone_links($elements);

        $title = self::clean_filename(basename($job['docx']));
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
        unset($GLOBALS['wfebpg_active_image_pool']);
        return $id;
    }
}
