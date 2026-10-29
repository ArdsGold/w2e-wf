<?php
if (!defined('ABSPATH')) exit;

/** Compare against the plugin header on the repository's main branch. */
function wfebpg_repository_version() {
    $cached = get_transient('wfebpg_repository_version');
    if (is_array($cached) && isset($cached['version'])) return $cached['version'];

    $response = wp_remote_get(
        'https://raw.githubusercontent.com/ArdsGold/w2e-wf/main/wolf-forge-elementor-page-generator.php',
        ['timeout'=>3, 'redirection'=>0, 'limit_response_size'=>16384]
    );
    $version = '';
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $body = wp_remote_retrieve_body($response);
        if (preg_match('/^[ \t]*\*[ \t]*Version:[ \t]*(r?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)[ \t]*\r?$/mi', $body, $match)) {
            $version = $match[1];
        }
    }
    // Cache failures too, so unavailable GitHub access does not delay every visit.
    set_transient('wfebpg_repository_version', ['version'=>$version], $version !== '' ? 6 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS);
    return $version;
}

function wfebpg_render_version_status($installed_version) {
    $remote_version = wfebpg_repository_version();
    echo '<p class="description wfebpg-version-status">Version <strong>' . esc_html($installed_version) . '</strong> &middot; ';
    if ($remote_version === '') {
        echo 'GitHub version check unavailable.';
    } else {
        $comparison = version_compare(ltrim($installed_version, 'rR'), ltrim($remote_version, 'rR'));
        if ($comparison < 0) {
            echo '<a href="' . esc_url('https://github.com/ArdsGold/w2e-wf') . '" target="_blank" rel="noopener noreferrer">Update available: '
                . esc_html($remote_version) . ' &mdash; View on GitHub</a>';
        } elseif ($comparison === 0) {
            echo 'Up to date with GitHub.';
        } else {
            echo 'Installed version is ahead of GitHub (' . esc_html($remote_version) . ').';
        }
    }
    echo '</p>';
}
