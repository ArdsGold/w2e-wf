<?php
if (!defined('ABSPATH')) exit;

/** Resolve opt-in widget links against published page titles on this site. */
class WFEBPG_Title_Linker {
    const PENDING_META = '_wfebpg_title_links_pending';

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
    public static function apply(&$elements, $source_id, &$pages = null) {
        $pending = false;
        $matches = [];
        $walk = function (&$nodes) use (&$walk, $source_id, &$pages, &$pending, &$matches) {
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
                        if (self::fill_link($settings, $title_key, $link_key, $source_id, $pages, $matches, $pending)) {
                            if ($type === 'jkit_icon_box') $settings['sg_readmore_enable_globallink'] = 'yes';
                            if ($type === 'elementskit-icon-box' && $link_key === 'ekit_icon_box_global_link') {
                                $settings['ekit_icon_box_show_global_link'] = 'yes';
                            }
                        }
                    } elseif ($type === 'icon-list') {
                        foreach ($settings['icon_list'] ?? [] as $key => $item) {
                            self::fill_link($settings['icon_list'][$key], 'text', 'link', $source_id, $pages, $matches, $pending);
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
        return $pending;
    }

    private static function fill_link(&$settings, $title_key, $link_key, $source_id, &$pages, &$matches, &$pending) {
        // Manual and dynamic links always take precedence. '#' is a placeholder.
        $link = $settings[$link_key] ?? [];
        if (!is_array($link) || !empty($settings['__dynamic__'][$link_key])) return false;
        if (trim((string) ($link['url'] ?? '')) !== '' && trim((string) $link['url']) !== '#') return false;
        $title = self::title_text($settings[$title_key] ?? '');
        $settings[$link_key] = array_merge($link, ['url'=>'']);
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
                WFEBPG_Logger::log('Ambiguous title link "' . $title . '" on page ' . $source_id . ': matching page IDs ' . implode(', ', $matches[$title]) . '. Link left blank.', 'warning');
            }
        }
        if (count($matches[$title]) !== 1) {
            $pending = true;
            return false;
        }
        $url = get_permalink($matches[$title][0]);
        if (!$url) {
            $pending = true;
            return false;
        }
        $settings[$link_key]['url'] = $url;
        return true;
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
