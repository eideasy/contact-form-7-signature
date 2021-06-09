<?php
defined('ABSPATH') or die('No script kiddies please!');

require_once 'EidEasySignerPendingTable.php';

class EidEasySignerAdmin
{
    public static function registerSettings()
    {
        register_setting('eideasy_signer', 'eideasy_cf7_signed_forms');
        register_setting('eideasy_signer', 'eideasy_client_id');
        register_setting('eideasy_signer', 'eideasy_secret');
        register_setting('eideasy_signer', 'eideasy_test_mode');
        register_setting('eideasy_signer', 'eideasy_skip_signing_flag');
        register_setting('eideasy_signer', 'eideasy_signature_redirect');
        register_setting('eideasy_signer', 'eideasy_no_download');
        register_setting('eideasy_signer', 'eideasy_no_emails');

        register_setting('eideasy_signer', 'eideasy_provider_signatures_enabled');
        register_setting('eideasy_signer', 'eideasy_provider_signatures_notify');
        register_setting('eideasy_signer', 'eideasy_cf7_user_email_field');
        register_setting('eideasy_signer', 'eideasy_notify_email_subject');
        register_setting('eideasy_signer', 'eideasy_notify_email_sender');
        register_setting('eideasy_signer', 'eideasy_notify_email_content');
    }

    public static function adminMenu()
    {
        add_menu_page('eID Easy Signer', 'eID Easy Signer', 'manage_options', 'eid-easy-signer-settings', [EidEasySignerAdmin::class, 'showAdmin']);
    }

    public static function getSettingsUrl($links)
    {
        $links[] = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=eid-easy-signer-settings')) . '">eID Easy Signer settings</a>';

        return $links;
    }

    public static function showAdmin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $default_tab = null;
        $tab         = sanitize_text_field(isset($_GET['tab']) ? $_GET['tab'] : $default_tab);
        ?>
        <div class="wrap">
            <h1><?= esc_html(get_admin_page_title()); ?></h1>
            <p>This plugin will allow you to make Contact Form 7 submissions digitally signeable after submitting
                form.</p>
            <?php if (!get_option('eideasy_client_id') || !get_option('eideasy_secret')) { ?>
                <p>
                    Sign up for client_id/secret at <a href="https://id.eideasy.com" target="_blank">id.eideasy.com</a>
                </p>
            <?php } ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=eid-easy-signer-settings"
                   class="nav-tab <?php if (strlen($tab) === 0) { ?>nav-tab-active<?php } ?>">
                    Settings
                </a>
                <a href="?page=eid-easy-signer-settings&tab=pending-contracts"
                   class="nav-tab <?php if ($tab === 'pending-contracts') { ?>nav-tab-active<?php } ?>">
                    Pending contracts
                </a>
            </nav>

            <?php
            if (strlen($tab) === 0) {
                self::settingsPage();
            } else {
                self::addSignaturesPage();
            }
            ?>
        </div>
        <?php
    }

    public static function addSignaturesPage()
    {
        // Show contracts that are waiting for provider signature.
        $table = new EidEasySignerPendingTable();

        ?>
        <div class="wrap"><h2>Contracts waiting for provider signature</h2>
            <form method="post">
                <input type="hidden" name="page" value="<?php echo sanitize_text_field($_REQUEST['page']) ?>"/>

                <?php
                $table->prepare_items();
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public static function settingsPage()
    {
        ?>
        <form action="options.php" method="post">

        <h2>General settings</h2>
        <?php if (get_option("eideasy_test_mode")) { ?>
            <div class="notice notice-error">
                <p>eID Easy signer sandbox mode active. Make sure to use correct test client_id and secret.</p>
            </div>
        <?php } ?>
        <table class="form-table">
            <tr>
                <th>eID Easy client ID</th>
                <td>
                    <input name="eideasy_client_id" size="50"
                           value="<?php echo esc_attr(get_option('eideasy_client_id')); ?>"/>
                </td>
            </tr>
            <tr>
                <th>eID Easy secret</th>
                <td>
                    <input name="eideasy_secret" size="50" type="password"
                           value="<?php echo esc_attr(get_option('eideasy_secret')); ?>"/>
                </td>
            </tr>
            <tr>
                <th>List of CF7 form ID-s</th>
                <td>
                    <input name="eideasy_cf7_signed_forms" size="50" placeholder="123,527"
                           value="<?php echo esc_attr(get_option('eideasy_cf7_signed_forms')); ?>"/>
                    <br>
                    <small>List all the Contact Form 7 or Fluent Forms ID-s where you want single attachment to be signed after form is
                        submitted. These will not send PDF to admin e-mail and user will be redirected to the signing
                        page right after submitting of the form.</small>
                </td>
            </tr>
            <tr>
                <th>Redirect after signing</th>
                <td>
                    <input name="eideasy_signature_redirect" size="50" placeholder="https://example.com/thank-you"
                           value="<?php echo esc_attr(get_option('eideasy_signature_redirect')); ?>"/>
                    <br>
                    <small>URL where the user should be redirected after signing the document</small>
                </td>
            </tr>
            <tr>
                <th>Skip digital signing flag</th>
                <td>
                    <input name="eideasy_skip_signing_flag" size="50" placeholder="i-am-old"
                           value="<?php echo esc_attr(get_option('eideasy_skip_signing_flag')); ?>"/>
                    <br>
                    <small>If Contact Form 7 field with this name contains something or is checked then digital signing
                        is skipped. Does not apply to Fluent Forms</small>
                </td>
            </tr>
            <tr>
                <th>No signed file download</th>
                <td>
                    <input name="eideasy_no_download" type="checkbox" value="1"
                        <?php checked('1', get_option('eideasy_no_download')); ?> />
                    <small>If checked then signed file is not downloaded for the user after download. You need to make sure that user gets the file if needed.
                </td>
            </tr>
            <tr>
                <th>Send notification e-mails</th>
                <td>
                    <input name="eideasy_no_emails" type="checkbox" value="1"
                        <?php checked('1', get_option('eideasy_no_emails')); ?> />
                    <small>If checked then notification e-mails will be sent for every signature created
                </td>
            </tr>
            <tr>
                <th>Use sandbox environment</th>
                <td>
                    <input name="eideasy_test_mode" type="checkbox" value="1"
                        <?php checked('1', get_option('eideasy_test_mode')); ?> />
                    <small>Make sure that correct client_id and secret are used, most likely Client ID: 2IaeiZXbcKzlP1KvjZH9ghty2IJKM8Lg and Secret: 56RkLgZREDi1H0HZAvzOSAVlxu1Flx41. Read more from <a
                                href="https://eideasy.com/developer-documentation/sandbox/">here</a></small>
                </td>
            </tr>
        </table>
        <h2>Service provider signing settings</h2>
        <table class="form-table">
            <tr>
                <th>Enable service provider signatures</th>
                <td>
                    <input name="eideasy_provider_signatures_enabled" type="checkbox" value="1"
                        <?php checked('1', get_option('eideasy_provider_signatures_enabled')); ?> />
                    <small>Tick if you want to add electronic signature to the document from your side as well</small>
                </td>
            </tr>
            <tr>
                <th>Notify user after service provider signature</th>
                <td>
                    <input name="eideasy_provider_signatures_notify" type="checkbox" value="1"
                        <?php checked('1', get_option('eideasy_provider_signatures_notify')); ?> />
                    <small>Send an e-mail to the customer after service provider signature has been added</small>
                </td>
            </tr>
            <tr>
                <th>CF7 customer e-mail field name</th>
                <td>
                    <input name="eideasy_cf7_user_email_field" size="50" placeholder="email"
                           value="<?php echo esc_attr(get_option('eideasy_cf7_user_email_field')); ?>"/>
                    <br>
                    <small>Contact Form 7 field name where the user e-mail is stored. Will be used to send notification after provider has signed.</small>
                </td>
            </tr>
            <tr>
                <th>Notify e-mail subject</th>
                <td>
                    <input name="eideasy_notify_email_subject" size="50" placeholder="Our signed file is attached"
                           value="<?php echo esc_attr(get_option('eideasy_notify_email_subject')); ?>"/>
                </td>
            </tr>
            <tr>
                <th>Notify e-mail sender address</th>
                <td>
                    <input name="eideasy_notify_email_sender" size="50" placeholder="ceo@example.com"
                           value="<?php echo esc_attr(get_option('eideasy_notify_email_sender')); ?>"/>
                </td>
            </tr>
            <tr>
                <th>Notify e-mail content</th>
                <td>
                    <textarea name="eideasy_notify_email_content" cols="50" rows="5"
                              placeholder="Attached is our signed contract, invoice coming soon.&#10;&#10;Thanks!"><?php echo esc_attr(get_option('eideasy_notify_email_content')); ?></textarea>
                </td>
            </tr>
        </table>
        <?php

        settings_fields('eideasy_signer');
        do_settings_sections('eideasy_signer');

        // output save settings button
        submit_button('Save settings');

        echo "</form>";
    }
}
