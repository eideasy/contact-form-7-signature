<?php

class EidEasySigner
{

    public function adminMenu()
    {
        add_submenu_page('wpcf7', 'eID Easy Signer', 'eID Easy Signer settings', 'manage_options', 'eid-easy-signer-settings', [EidEasySigner::class, 'showAdmin']);
    }

    public function registerSettings()
    {
        register_setting('eideasy_signer', 'eideasy_cf7_signed_forms');
        register_setting('eideasy_signer', 'eideasy_client_id');
        register_setting('eideasy_signer', 'eideasy_secret');
        register_setting('eideasy_signer', 'eideasy_skip_signing_flag');
        register_setting('eideasy_signer', 'eideasy_signature_redirect');
    }

    static function get_settings_url($links)
    {
        $links[] = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=eid-easy-signer-settings')) . '">eID Easy Signer settings</a>';

        return $links;
    }

    public function showAdmin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>

        <div id="sa-admin" class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <p>This plugin will allow you to make Contact Form 7 submissions digitally signeable after submitting form.</p>
        <p>Sign up for client_id/secret at <a href="https://id.eideasy.com" target="_blank">id.eideasy.com</a></p>
        <form action="options.php" method="post">

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
                    <input name="eideasy_cf7_signed_forms" size="50"
                           value="<?php echo esc_attr(get_option('eideasy_cf7_signed_forms')); ?>"/>
                    <br>
                    <small>List all the Contact Form 7 ID-s where you want single attachment to be signed after form is
                        submitted. These will not send PDF to admin e-mail and user will be redirected to the signing
                        page right after submitting of the form.</small>
                </td>
            </tr>
            <tr>
                <th>Redirect after signing</th>
                <td>
                    <input name="eideasy_signature_redirect" size="50"
                           value="<?php echo esc_attr(get_option('eideasy_signature_redirect')); ?>"/>
                    <br>
                    <small>URL shere the user should be redirected after signing the document</small>
                </td>
            </tr>
            <tr>
                <th>Skip digital signing flag</th>
                <td>
                    <input name="eideasy_skip_signing_flag" size="50"
                           value="<?php echo esc_attr(get_option('eideasy_skip_signing_flag')); ?>"/>
                    <br>
                    <small>If Contact Form 7 field with this name contains something or is checked then digital signing
                        is skipped</small>
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

    public function getSigningUrl()
    {
        $unitTag    = wpcf7_sanitize_unit_tag($_GET['unit_tag']);
        $signingUrl = get_option("eideasy_signing_url_$unitTag");
        delete_option("eideasy_signing_url_$unitTag");
        wp_send_json([
            'signing_url' => $signingUrl,
        ]);
    }

    public function prepareSigning($contact_form)
    {
        $submission = WPCF7_Submission::get_instance();
        if (!self::useSigning($contact_form->id())) {
            return;
        }

        $postedData = $submission->get_posted_data();

        error_log("Preparing for eID Easy signing: " . $submission->get_meta('unit_tag') . ", " . json_encode($postedData));

        $skipSigningFlag = get_option("eideasy_skip_signing_flag");
        if ($skipSigningFlag && isset($postedData[$skipSigningFlag])) {
            foreach ($postedData[$skipSigningFlag] as $skipFlag) {
                if (strlen($skipFlag) > 0) {
                    error_log('Signing skipped as submission has skip signing flag set');
                    return;
                }
            }

        }

        $fileLocations = array_values($submission->uploaded_files());

        if (is_array($fileLocations) && count($fileLocations) > 0) {
            $filePath = $fileLocations[0];
        } else {
            error_log("eID Easy did not find any attachments to sign");
            return;
        }

        $pathParts = explode(DIRECTORY_SEPARATOR, $filePath);
        $fileName  = $pathParts[count($pathParts) - 1];

        $clientId = get_option('eideasy_client_id');
        $secret   = get_option("eideasy_secret");
        $redirect = get_option("eideasy_signature_redirect");

        $baseUrl = "https://id.eideasy.com/api/v2/prepare_external_doc";

        $contents = base64_encode(file_get_contents($filePath));

        $response = wp_remote_post($baseUrl, [
            'body'    => wp_json_encode([
                'secret'             => $secret,
                'client_id'          => $clientId,
                'filename'           => $fileName,
                'signature_redirect' => $redirect,
                'file_content'       => $contents,
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("eID Easy preparing signature failed");
            wp_remote_get("https://id.eideasy.com/confirm_progress?message=" . urlencode($error_message));
            return;
        }

        $bodyString = wp_remote_retrieve_body($response);
        $bodyArr    = json_decode($bodyString, true);
        if (!is_array($bodyArr) || !array_key_exists('doc_id', $bodyArr)) {
            error_log("eID Easy invalid response: $bodyString");
            return;
        }

        $docId      = $bodyArr['doc_id'];
        $signingUrl = "https://id.eideasy.com/sign_contract_external?client_id=$clientId&doc_id=$docId";

        add_filter('wpcf7_skip_mail', [EidEasySigner::class, 'skipMail']);

        update_option('eideasy_signing_url_' . $submission->get_meta('unit_tag'), $signingUrl);
    }

    public function skipMail()
    {
        return true;
    }

    public function useSigning($formId)
    {
        $usedFormsString = get_option('eideasy_cf7_signed_forms');
        if (!$usedFormsString) {
            return false;
        }

        $formsArray = explode(",", $usedFormsString);
        return in_array($formId, $formsArray);
    }
}


