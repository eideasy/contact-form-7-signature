<?php

/**
 * Plugin Name: eID Easy CF7 Qualified Signer
 * Plugin URI: https://eideasy.com
 * Description: Add Qualified Electronic Signatures to Contact Form 7 email PDF attachments
 * Version: 1.0.0
 * Author: eID Easy
 * Author URI: https://eideasy.com
 * Text Domain: eideasy
 * License: GPLv3
 */

require_once 'EidEasySigner.php';

function eideasy_signer_init()
{
    if (!class_exists('WPCF7')) {
        return;
    }

    $jsVersion = date("ymd-Gis", filemtime(plugin_dir_path(__FILE__) . 'redirector.js'));

    add_action('wpcf7_before_send_mail', [EidEasySigner::class, 'prepareSigning'], PHP_INT_MAX / 2);
    add_action("wp_ajax_eideasy_signing_url", [EidEasySigner::class, 'getSigningUrl']);
    add_action("wp_ajax_nopriv_eideasy_signing_url", [EidEasySigner::class, 'getSigningUrl']);

    if (is_admin()) {
        add_action('admin_init', [EidEasySigner::class, 'registerSettings']);
        add_action('admin_menu', [EidEasySigner::class, 'adminMenu']);
    }

    wp_enqueue_script('eideasy_polyfill', 'https://cdn.polyfill.io/v2/polyfill.min.js?features=Promise,fetch');

    wp_register_script('eideasy_scripts', plugins_url('redirector.js', __FILE__));
    wp_localize_script("eideasy_scripts",
        'eideasy_settings', [
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]
    );
    wp_enqueue_script('eideasy_scripts', false, ['eideasy_polyfill'], $jsVersion);

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), [EidEasySigner::class, 'get_settings_url']);
}

add_action('plugins_loaded', 'eideasy_signer_init');
