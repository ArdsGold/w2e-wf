<?php
if (!defined('ABSPATH')) exit;

/** Resolve opt-in widget links against published page titles on this site. */
class WFEBPG_Title_Linker {
    const PENDING_META = '_wfebpg_title_links_pending';
    const TARGETS_META = '_wfebpg_title_link_targets';

    private const WIDGET_FIELDS = [
        'heading' => ['title', 'link'],
        'icon-box' => ['title_text', 'link'],
        'image-box' => ['title_text', 'link'],
        'button' => ['text', 'link'],
        'flip-box' => ['title_text_a', 'link'],
        'elementskit-heading' => ['ekit_heading_title', 'ekit_heading_link'],
        'elementskit-icon-box' => ['ekit_icon_box_title_text', 'ekit_icon_box_global_link'],
        'jkit_heading' => ['sg_title_text', 'sg_title_link'],
        'jkit_icon_box' => ['sg_icon_text', 'sg_readmore_globallink'],
    ];

    private static function title_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $value) ?? '');
    }

    public static function is_marked($node) {
        $marker = WFEBPG_Template::marker($node);
        return $marker['type'] === 'link' || in_array('link', $marker['flags'], true);
    }

    public static function supports($node) {
        $type = $node['widgetType'] ?? '';
        return isset(self::WIDGET_FIELDS[$type]) || $type === 'icon-list';
    }

    /** Return whether any supported, blank links still need a matching page. */
    public static function apply(&$elements, $source_id, &$pages = null, $adopt_legacy = false) {
        $pending = false;
        $matches = [];
        $saved = get_post_meta($source_id, self::TARGETS_META, true);
        $saved = is_array($saved) ? $saved : [];
        $targets = [];
        $walk = function (&$nodes) use (&$walk, $source_id, &$pages, &$pending, &$matches, $saved, &$targets, $adopt_legacy) {
            foreach ($nodes as &$node) {
                $type = $node['widgetType'] ?? '';
                if (self::is_marked($node)) {
                    $settings =& $node['settings'];
                    if (!is_array($settings)) $settings = [];
                    if (isset(self::WIDGET_FIELDS[$type])) {
                        [$title_key, $link_key] = self::WIDGET_FIELDS[$type];
                        if ($type === 'elementskit-icon-box' && ($settings['ekit_icon_box_enable_btn'] ?? '') === 'yes') {
                            $link_key = 'ekit_icon_box_btn_url';
                        }
                        $key = ($node['id'] ?? '') . ':' . $link_key;
                        if (self::fill_link($settings, $title_key, $link_key, $source_id, $pages, $matches, $pending, $saved[$key] ?? null, $targets, $key, $adopt_legacy)) {
                            if ($type === 'jkit_icon_box') $settings['sg_readmore_enable_globallink'] = 'yes';
                            if ($type === 'elementskit-icon-box' && $link_key === 'ekit_icon_box_global_link') {
                                $settings['ekit_icon_box_show_global_link'] = 'yes';
                            }
                        }
                    } elseif ($type === 'icon-list') {
                        foreach ($settings['icon_list'] ?? [] as $key => $item) {
                            $tracking_key = ($node['id'] ?? '') . ':icon_list:' . ($item['_id'] ?? $key);
                            self::fill_link($settings['icon_list'][$key], 'text', 'link', $source_id, $pages, $matches, $pending, $saved[$tracking_key] ?? null, $targets, $tracking_key, $adopt_legacy);
                        }
                    } else {
                        WFEBPG_Logger::log('Title link marker: unsupported widget ' . $type . ' on page ' . $source_id . '.', 'warning');
                    }
                    unset($settings);
                }
                if (!empty($node['elements']) && is_array($node['elements'])) $walk($node['elements']);
            }
            unset($node);
        };
        $walk($elements);
        if ($targets) update_post_meta($source_id, self::TARGETS_META, $targets);
        else delete_post_meta($source_id, self::TARGETS_META);
        return $pending;
    }

    private static function fill_link(&$settings, $title_key, $link_key, $source_id, &$pages, &$matches, &$pending, $tracked, &$targets, $key, $adopt_legacy) {
        // Manual and dynamic links always take precedence. '#' is a placeholder.
        $link = $settings[$link_key] ?? [];
        if (!is_array($link) || !empty($settings['__dynamic__'][$link_key])) return false;
        $title = self::title_text($settings[$title_key] ?? '');
        $current = trim((string) ($link['url'] ?? ''));
        if (is_array($tracked) && $current === $tracked['url']) {
            if ($title === $tracked['title']) {
                $target = get_post($tracked['id']);
                $url = $target && $target->post_type === 'page' && $target->post_status === 'publish'
                    ? get_permalink($target->ID) : '';
                $settings[$link_key]['url'] = $url ?: '';
                $targets[$key] = array_merge($tracked, ['url'=>$url ?: '']);
                if (!$url) $pending = true;
                return true;
            }
            // A regenerated title needs a new match, rather than the previous target.
            $current = '';
        } elseif (is_array($tracked)) return false; // A manual edit releases ownership.
        elseif ($current !== '' && $current !== '#' && !$adopt_legacy) return false;
        $legacy_url = $current !== '' && $current !== '#' ? $current : '';
        if ($title === '' && $legacy_url !== '') return false;
        if ($legacy_url === '') $settings[$link_key] = array_merge($link, ['url'=>'']);
        if ($title === '') return false;

        if ($pages === null) {
            global $wpdb;
            // Read titles only; loading full page content is unnecessary.
            $pages = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'");
            if (!is_array($pages)) throw new RuntimeException('Unable to read published pages for title links.');
        }
        if (!array_key_exists($title, $matches)) {
            $matches[$title] = [];
            $pattern = '/\A' . preg_quote($title, '/') . '\z/iu';
            foreach ($pages as $page) {
                if ((int) $page->ID === (int) $source_id) continue;
                if (preg_match($pattern, self::title_text($page->post_title)) === 1) $matches[$title][] = (int) $page->ID;
            }
            if (count($matches[$title]) > 1) {
                WFEBPG_Logger::log('Ambiguous title link "' . $title . '" on page ' . $source_id . ': matching page IDs ' . implode(', ', $matches[$title]) . '. No target selected.', 'warning');
            }
        }
        if (count($matches[$title]) !== 1) {
            if ($legacy_url !== '') {
                WFEBPG_Logger::log('Existing title link on page ' . $source_id . ' left unchanged: no unique title match for "' . $title . '".', 'warning');
                return false;
            }
            $pending = true;
            return false;
        }
        $url = get_permalink($matches[$title][0]);
        // Legacy links have no ownership record: adopt only a verified URL/title pair.
        if ($legacy_url !== '' && $legacy_url !== $url) {
            WFEBPG_Logger::log('Title link on page ' . $source_id . ' left unchanged: existing URL could not be verified for "' . $title . '".', 'warning');
            return false;
        }
        if (!$url) {
            $pending = true;
            return false;
        }
        $settings[$link_key]['url'] = $url;
        $targets[$key] = ['id'=>(int)$matches[$title][0], 'url'=>$url, 'title'=>$title];
        return true;
    }

    /** Explicit refresh scans Elementor pages; automatic refresh scans tracked sources. */
    public static function refresh_generated($adopt_legacy = false) {
        // ponytail: scan source pages; add a reverse target index if site size makes this costly.
        $ids = get_posts([
            'post_type'=>'page', 'post_status'=>'any', 'posts_per_page'=>-1, 'fields'=>'ids',
            'meta_key'=>$adopt_legacy ? '_elementor_data' : self::TARGETS_META,
            'meta_compare'=>'EXISTS', 'orderby'=>'ID', 'order'=>'ASC',
        ]);
        $updated = 0;
        $pages = null;
        foreach ($ids as $id) {
            if (!$adopt_legacy && !get_post_meta($id, self::TARGETS_META, true)) continue;
            $raw = get_post_meta($id, '_elementor_data', true);
            $elements = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($elements)) continue;
            $before = $elements;
            $pending = self::apply($elements, $id, $pages, $adopt_legacy);
            if ($elements !== $before) {
                $encoded = WFEBPG_Template::encode($elements);
                update_post_meta($id, '_elementor_data', wp_slash($encoded));
                delete_post_meta($id, '_elementor_element_cache');
                clean_post_cache($id);
                if (class_exists('\\Elementor\\Core\\Files\\CSS\\Post')) {
                    (new \Elementor\Core\Files\CSS\Post($id))->delete();
                }
                $updated++;
            }
            self::remember_pending($id, $pending);
        }
        return $updated;
    }

    public static function page_saved($id, $post, $update, $before) {
        if ($post->post_type !== 'page') return;
        if ($before && $before->post_name === $post->post_name && (int)$before->post_parent === (int)$post->post_parent
            && $before->post_status === $post->post_status) return;
        self::schedule_refresh();
    }

    public static function page_deleted($id, $post) {
        if ($post->post_type === 'page') self::schedule_refresh();
    }

    private static function schedule_refresh() {
        // Coalesce bulk page updates and run after WordPress clears descendant caches.
        static $scheduled = false;
        if (!$scheduled) {
            $scheduled = true;
            add_action('shutdown', static function () { self::refresh_generated(); });
        }
    }

    public static function remember_pending($page_id, $pending) {
        if ($pending) update_post_meta($page_id, self::PENDING_META, '1');
        else delete_post_meta($page_id, self::PENDING_META);
    }

    /** Retry earlier generated pages after a new page has been saved. */
    public static function resolve_pending($skip_id, $after_save) {
        $source_ids = get_posts([
            'post_type'=>'page', 'post_status'=>'publish', 'posts_per_page'=>-1,
            'fields'=>'ids', 'meta_key'=>self::PENDING_META, 'meta_value'=>'1',
            'post__not_in'=>[(int) $skip_id], 'orderby'=>'ID', 'order'=>'ASC',
        ]);
        $pages = null;
        foreach ($source_ids as $source_id) {
            $raw = get_post_meta($source_id, '_elementor_data', true);
            $elements = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($elements)) {
                WFEBPG_Logger::log('Cannot retry title links on page ' . $source_id . ': invalid Elementor data.', 'warning');
                continue;
            }
            $pending = self::apply($elements, $source_id, $pages);
            $encoded = WFEBPG_Template::encode($elements);
            if ($encoded !== $raw) {
                update_post_meta($source_id, '_elementor_data', wp_slash($encoded));
                delete_post_meta($source_id, '_elementor_element_cache');
                clean_post_cache($source_id);
                $after_save($source_id);
            }
            self::remember_pending($source_id, $pending);
        }
    }
}
