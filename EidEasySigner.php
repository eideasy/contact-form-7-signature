<?php

defined('ABSPATH') or die('No script kiddies please!');

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once 'EidEasySignerPendingTable.php';
require_once 'EidEasyApi.php';


class EidEasySigner
{
    public static function getSigningUrl()
    {
        $postedDataHash = sanitize_text_field($_GET['posted_data_hash']);
        if (strlen($postedDataHash) === 0) {
            wp_send_json([
                'signing_url' => null,
                'message'     => 'Invalid posted_data_hash'
            ]);
        }
        $signingUrl = get_option("eideasy_signing_url_$postedDataHash");
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

        if (!self::useSigning($formId)) {
            return $emailAttachments;
        }

        if (count($emailAttachments) === 0) {
            error_log("No attachment to sign for Fluent Form $formId");
            return $emailAttachments;
        }


        error_log("Preparing for eID Easy signing in Fluent Form: $unitTag");

        self::prepareSigningApi($unitTag, $emailAttachments);

        return $emailAttachments;
    }

    public static function prepareSigningMailComponents($components, $contact_form, $mail)
    {
        $submission = WPCF7_Submission::get_instance();
        if (!self::useSigning($contact_form->id())) {
            return $components;
        }

        $postedDataHash = $submission->get_posted_data_hash();

        $signingUrl = get_option("eideasy_signing_url_$postedDataHash");
        if ($signingUrl) {
            error_log("Signature already prepared, skipping");
            return $components;
        }

        error_log("Preparing for eID Easy signing in mail components: $postedDataHash");

        $fileLocations = $components['attachments'];
        self::prepareSigningApi($postedDataHash, $fileLocations, $submission->get_posted_data());

        return $components;
    }

    public static function prepareSigning($contact_form)
    {
        $submission = WPCF7_Submission::get_instance();
        if (!self::useSigning($contact_form->id())) {
            return;
        }

        $postedData     = $submission->get_posted_data();
        $postedDataHash = $submission->get_posted_data_hash();
        error_log("Preparing for eID Easy signing: $postedDataHash - " . json_encode($postedData));

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
        self::prepareSigningApi($postedDataHash, $fileLocations, $postedData);
    }

    protected static function prepareSigningApi($postedHash, $fileLocations, $formData = null)
    {
        error_log("Starting to sign files: " . json_encode($fileLocations));

        // Make sure PDF generated from form fields is in the top, before other uploaded files
        $fileLocations = array_reverse($fileLocations);

        if (is_string($fileLocations)) {
            $fileLocations = [$fileLocations];
        }
        if (!is_array($fileLocations)) {
            error_log("eID Easy files to be signed locations is not array of string file paths");
            return;
        }
        if (count($fileLocations) === 0) {
            error_log("eID Easy did not find any attachments to sign");
            return;
        }

        $clientId = get_option('eideasy_client_id');
        $redirect = get_option("eideasy_signature_redirect");
        if (get_option('eideasy_provider_signatures_enabled')) {
            $state    = wp_generate_uuid4();
            $redirect .= (parse_url($redirect, PHP_URL_QUERY) ? '&' : '?') . "signer-return=true&eideasy-state=$state";
        }

        $files         = [];
        $counter       = 1;
        $usedFileNames = [];
        foreach ($fileLocations as $filePath) {
            if (is_array($filePath)) {
                $filePath = reset($filePath);
            }
            $pathParts = explode(DIRECTORY_SEPARATOR, $filePath);
            $fileName  = $pathParts[count($pathParts) - 1];

            // Handle duplicate file names
            while (isset($usedFileNames[$fileName])) {
                $fileName = $counter . "_" . $fileName;
                $counter++;
            }
            $usedFileNames[$fileName] = $fileName;

            $contents = base64_encode(file_get_contents($filePath));

            $files[] = [
                'fileName'    => $fileName,
                'mimeType'    => mime_content_type($filePath),
                'fileContent' => $contents,
            ];
        }

        $params = [
            'files'              => $files,
            'signature_redirect' => $redirect,
        ];
        if (get_option('eideasy_no_emails')) {
            $params['noemails'] = true;
        }
        if (get_option('eideasy_no_download')) {
            $params['nodownload'] = true;
        }

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
        update_option('eideasy_signing_url_' . $postedHash, $signingUrl);

        add_filter('wpcf7_skip_mail', [EidEasySigner::class, 'skipMail']);

        // Email where user notifications will be sent later
        $userEmail = null;
        if (get_option('eideasy_cf7_user_email_field')) {
            $userEmail = $postedData[get_option('eideasy_cf7_user_email_field')] ?? null;
        }

        if (get_option('eideasy_provider_signatures_enabled')) {
            error_log("Contract prepared with provider signature $state -> $docId & $userEmail");
            $notificationEmail = self::getNotificationEmail($formData);
            update_option('eideasy_signing_state_' . $state, ['doc_id' => $docId, 'user_email' => $userEmail, 'notification_email' => $notificationEmail]);
        }
    }

    public static function getNotificationEmail($formData)
    {
        $notificationEmail = get_option('eideasy_notify_email_address') ?? get_option('eideasy_notify_email_sender');

        $configurableNotifications = explode(':', $notificationEmail);
        if (count($configurableNotifications) === 2) {
            $fieldName = $configurableNotifications[0];

            $pairs = explode(',', $configurableNotifications[1]);
            error_log("Parsed config: $fieldName -> " . json_encode($pairs));
            $defaultTo   = null;
            $triedValues = [];
            foreach ($pairs as $pair) {
                $emailPair = explode("=", $pair);
                error_log("Comparing $pair: " . json_encode($emailPair));
                $triedValues[] = $emailPair[0];
                if (!$defaultTo) {
                    $defaultTo = $emailPair[0];
                }

                if (!$formData || !isset($formData[$fieldName])) {
                    error_log("No field $fieldName in input form data");
                    return $defaultTo; // Use first valid e-mail
                }

                $formValue = $formData[$fieldName][0] ?? $formData[$fieldName] ?? "-";

                if ($formValue === $emailPair[0]) {
                    error_log("Found notification e-mail " . $emailPair[1]);
                    return $emailPair[1];
                }
            }
            error_log("No good notification e-mail found, using default $defaultTo from $notificationEmail, fieldName=$fieldName, tried valued: " . json_encode($triedValues));
            return $defaultTo ?? get_option('eideasy_notify_email_sender');
        }

        error_log("Notification e-mail $notificationEmail");
        return $notificationEmail;
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