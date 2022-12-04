<?php
/**
 * Plugin Name: Electronic Signatures in WordPress by eID Easy
 * Plugin URI: https://eideasy.com
 * Description: Add Qualified Electronic Signatures to Contact Form 7 or Fluent Forms email PDF attachments.
 * Version: 3.2
 * Author: eID Easy
 * Text Domain: eid-easy
 * License: GPLv3
 */

defined('ABSPATH') or die('No script kiddies please!');

require_once 'EidEasySigner.php';
require_once 'EidEasySignerAdmin.php';
require_once 'EidEasyProviderSigner.php';

function eideasy_signer_scripts()
{
    wp_enqueue_script('eideasy_polyfill', 'https://cdn.polyfill.io/v2/polyfill.min.js?features=Promise,fetch');

    wp_register_script('eideasy_scripts', plugins_url('redirector.js', __FILE__));
    wp_localize_script("eideasy_scripts",
        'eideasy_settings', [
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]
    );

    $jsVersion = date("ymd-Gis", filemtime(plugin_dir_path(__FILE__) . 'redirector.js'));
    wp_enqueue_script('eideasy_scripts', false, ['eideasy_polyfill'], $jsVersion);
}

function eideasy_signer_init()
{
    if (is_admin()) {
        add_action('admin_init', [EidEasySignerAdmin::class, 'registerSettings']);
        add_action('admin_menu', [EidEasySignerAdmin::class, 'adminMenu']);
    }

    if (!class_exists('WPCF7') && !defined('FLUENTFORM')) {
        return;
    }

    if (class_exists('WPCF7')) {
        add_action('wpcf7_before_send_mail', [EidEasySigner::class, 'prepareSigning'], PHP_INT_MAX / 2);
        add_filter('wpcf7_mail_components', [EidEasySigner::class, 'prepareSigningMailComponents'], PHP_INT_MAX / 2, 3);
    }

    if (defined('FLUENTFORM')) {
        $unitTag = $_GET['eideasy_redirect_fluent_unit_tag'] ?? null;
        if ($unitTag) {
            $signingUrl = get_option("eideasy_signing_url_$unitTag");
            if ($signingUrl) {
                error_log("Redirecting eID Easy to fluent forms signature");
                wp_redirect($signingUrl);
                die;
            } else {
                error_log("No eID Easy signing url found from fluent forms");
            }
        }
        add_filter('fluentform_email_attachments', [EidEasySigner::class, 'prepareFluentFormsSignature'], PHP_INT_MAX / 2, 5);
        add_filter('fluentform_submission_confirmation', [EidEasySigner::class, 'insertRedirectToUrl'], 20, 3);
    }

    add_action("wp_ajax_eideasy_signing_url", [EidEasySigner::class, 'getSigningUrl']);
    add_action("wp_ajax_nopriv_eideasy_signing_url", [EidEasySigner::class, 'getSigningUrl']);

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), [EidEasySignerAdmin::class, 'getSettingsUrl']);

    add_action('parse_request', [EidEasyProviderSigner::class, 'checkSignerReturn']);
    add_action('parse_request', [EidEasyProviderSigner::class, 'checkProviderReturn']);
}

add_action('plugins_loaded', 'eideasy_signer_init');
add_action('wp_enqueue_scripts', 'eideasy_signer_scripts');
