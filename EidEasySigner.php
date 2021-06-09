<?php

defined('ABSPATH') or die('No script kiddies please!');

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once 'EidEasySignerPendingTable.php';
require_once 'EidEasyApi.php';


class EidEasySigner
{
    public static function getSigningUrl()
    {
        $unitTag    = wpcf7_sanitize_unit_tag($_GET['unit_tag']);
        $signingUrl = get_option("eideasy_signing_url_$unitTag");
        delete_option("eideasy_signing_url_$unitTag");
        wp_send_json([
            'signing_url' => $signingUrl,
        ]);
    }

    public static function insertRedirectToUrl($returnData, $form, $confirmation)
    {
        $unitTag = $_GET['t'];
        $formId  = $form->id;

        if (!self::useSigning($formId)) {
            return $returnData;
        }

        $returnData['redirectUrl'] = home_url("?eideasy_redirect_fluent_unit_tag=$unitTag");

        return $returnData;
    }

    public static function prepareFluentFormsSignature($emailAttachments, $emailData, $formData, $entry, $form)
    {
        $unitTag = $_GET['t'];
        $formId  = $form->id;

        if (count($emailAttachments) === 0) {
            return $emailAttachments;
        }

        if (!self::useSigning($formId)) {
            return $emailAttachments;
        }

        error_log("Preparing for eID Easy signing in mail components: $unitTag");

        self::prepareSigningApi($unitTag, $emailAttachments);

        return $emailAttachments;
    }

    public static function prepareSigningMailComponents($components, $contact_form, $mail)
    {
        $submission = WPCF7_Submission::get_instance();
        if (!self::useSigning($contact_form->id())) {
            return $components;
        }

        $unitTag = $submission->get_meta('unit_tag');

        $signingUrl = get_option("eideasy_signing_url_$unitTag");
        if ($signingUrl) {
            error_log("Signature already prepared, skipping");
            return $components;
        }

        error_log("Preparing for eID Easy signing in mail components: $unitTag");

        $fileLocations = $components['attachments'];
        self::prepareSigningApi($unitTag, $fileLocations);

        return $components;
    }

    public static function prepareSigning($contact_form)
    {
        $submission = WPCF7_Submission::get_instance();
        if (!self::useSigning($contact_form->id())) {
            return;
        }

        $postedData = $submission->get_posted_data();

        $unitTag = $submission->get_meta('unit_tag');
        error_log("Preparing for eID Easy signing: $unitTag - " . json_encode($postedData));

        $skipSigningFlag = get_option("eideasy_skip_signing_flag");
        if ($skipSigningFlag && isset($postedData[$skipSigningFlag]) && is_array($postedData[$skipSigningFlag])) {
            foreach ($postedData[$skipSigningFlag] as $skipFlag) {
                if (strlen($skipFlag) > 0) {
                    error_log('Signing skipped as submission has skip signing flag set');
                    return;
                }
            }
        }

        $fileLocations = array_values($submission->uploaded_files());
        self::prepareSigningApi($unitTag, $fileLocations);
    }

    protected static function prepareSigningApi($unitTag, $fileLocations)
    {
        if (is_array($fileLocations) && count($fileLocations) > 0) {
            $filePath = $fileLocations[0];
        } else {
            error_log("eID Easy did not find any attachments to sign");
            return;
        }

        $pathParts = explode(DIRECTORY_SEPARATOR, $filePath);
        $fileName  = $pathParts[count($pathParts) - 1];

        $clientId = get_option('eideasy_client_id');
        $redirect = get_option("eideasy_signature_redirect");
        if (get_option('eideasy_provider_signatures_enabled')) {
            $state    = wp_generate_uuid4();
            $redirect .= (parse_url($redirect, PHP_URL_QUERY) ? '&' : '?') . "signer-return=true&eideasy-state=$state";
        }

        $contents = base64_encode(file_get_contents($filePath));
        $params   = [
            'files'              => [[
                'fileName'    => $fileName,
                'mimeType'    => 'application/pdf',
                'fileContent' => $contents,
            ]],
            'signature_redirect' => $redirect,
        ];
        if (get_option('eideasy_no_emails')) {
            $params['noemails'] = true;
        }
        if (get_option('eideasy_no_download')) {
            $params['nodownload'] = true;
        }
        error_log(print_r($params, true));

        $bodyArr = EidEasyApi::sendCall('/api/signatures/prepare-files-for-signing', $params);
        if (!$bodyArr) {
            error_log("Preparing signing failed");
            return;
        }

        $docId = $bodyArr['doc_id'];
        if (get_option("eideasy_test_mode")) {
            $env = "test";
        } else {
            $env = "id";
        }
        $signingUrl = "https://$env.eideasy.com/sign_contract_external?client_id=$clientId&doc_id=$docId";

        add_filter('wpcf7_skip_mail', [EidEasySigner::class, 'skipMail']);

        // Email where user notifications will be sent later
        $userEmail = null;
        if (get_option('eideasy_cf7_user_email_field')) {
            $userEmail = $postedData[get_option('eideasy_cf7_user_email_field')] ?? null;
        }

        update_option('eideasy_signing_url_' . $unitTag, $signingUrl, false);
        if (get_option('eideasy_provider_signatures_enabled')) {
            error_log("Contract prepared with provider signature $state -> $docId & $userEmail");
            update_option('eideasy_signing_state_' . $state, ['doc_id' => $docId, 'user_email' => $userEmail], false);
        }
    }

    public static function skipMail()
    {
        return true;
    }

    public static function useSigning($formId)
    {
        $usedFormsString = get_option('eideasy_cf7_signed_forms');
        if (!$usedFormsString) {
            return false;
        }

        $formsArray = explode(",", $usedFormsString);
        return in_array($formId, $formsArray);
    }
}


