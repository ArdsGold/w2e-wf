<?php
if (!defined('ABSPATH')) exit;

/** Add an Elementor Free control to widgets and structural elements. */
add_action('elementor/element/after_section_end', function ($element, $section_id, $args) {
    if (($args['tab'] ?? '') !== \Elementor\Controls_Manager::TAB_ADVANCED) return;
    if (!in_array($section_id, ['_section_style', 'section_layout', 'section_advanced'], true)) return;
    if ($element->get_controls('wfebpg_marker')) return;

    $element->start_controls_section('wfebpg_markers', [
        'label'=>'Wolf Forge', 'tab'=>\Elementor\Controls_Manager::TAB_ADVANCED,
    ]);
    $element->add_control('wfebpg_marker', [
        'label'=>'Wolf Forge Marker', 'type'=>\Elementor\Controls_Manager::TEXT,
        'label_block'=>true, 'default'=>'', 'placeholder'=>'h3|link', 'render_type'=>'none',
        'description'=>'Examples: h1, h2, h3|p, h3|link, h3|p|repeat, repeat, link, step. No data-customID prefix needed. A nonblank value overrides attribute and CSS-class markers. Leave blank to use those instead.',
    ]);
    $element->end_controls_section();
}, 10, 3);
