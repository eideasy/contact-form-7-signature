<?php
defined('ABSPATH') or die('No script kiddies please!');

class EidEasyProviderSigner
{
    public static function checkProviderReturn()
    {
        $state          = sanitize_text_field($_GET['eideasy-state'] ?? "");
        $providerReturn = (sanitize_text_field($_GET['provider-sign-return'] ?? "")) === 'true';
        $redirect       = admin_url('admin.php?page=eid-easy-signer-settings&tab=pending-contracts');
        if (strlen($state) > 0 && $providerReturn) {
            EidEasyProviderSigner::completeProviderSignature($state);
            wp_redirect($redirect);
            exit;
        }
    }

    public static function checkSignerReturn()
    {
        if (get_option('eideasy_provider_signatures_enabled')) {
            $state        = sanitize_text_field($_GET['eideasy-state'] ?? "");
            $signerReturn = (sanitize_text_field($_GET['signer-return'] ?? "")) === 'true';
            if (strlen($state) > 0 && $signerReturn) {
                EidEasyProviderSigner::prepareProviderSignature($state);

                wp_redirect(get_option('eideasy_signature_redirect'));
                exit;
            }
        } else {
            error_log('Provider signatures not enabled, not preparing second signature');
        }
    }

    protected static function notifyCustomer($docId)
    {
        $bodyArr = EidEasyApi::sendCall('/files/download_external_signed_doc', ['doc_id' => $docId]);

        $signedFileContents = $bodyArr['signed_file_contents'];
        $filename           = $bodyArr['filename'];

        $subject     = get_option('eideasy_notify_email_subject');
        $message     = get_option('eideasy_notify_email_content');
        $senderEmail = get_option('eideasy_notify_email_sender');

        $to                = null;
        $pendingSignatures = get_option('eideasy_pending_provider_signatures', []);
        foreach ($pendingSignatures as $value) {
            if ($value['doc_id'] === $docId) {
                $to = $value['user_email'];
                break;
            }
        }
        if (!$to) {
            error_log("User email missing, cannot notify");
            return;
        }

        $headers = [
            "From:$senderEmail",
            "Bcc:$senderEmail",
        ];

        $signedFilePath = sys_get_temp_dir() . "/$filename";
        file_put_contents($signedFilePath, base64_decode($signedFileContents));

        error_log("Sending notification e-mail to $to");
        wp_mail($to, $subject, $message, $headers, [$signedFilePath]);
        unlink($signedFilePath);
    }

    public static function completeProviderSignature($state)
    {
        $signState = get_option("eideasy_signing_state_$state");
        $docId     = $signState['doc_id'] ?? null;
        if (!$docId) {
            error_log("$state provider signature must have been completed already");
            return;
        }
        delete_option("eideasy_signing_state_$state");

        // Send notification e-mail to the customer
        if (get_option('eideasy_provider_signatures_notify')) {
            self::notifyCustomer($docId);
        } else {
            error_log('Notifications disabled');
        }

        while (get_option('eideasy_pending_provider_lock')) {
            usleep(100000);
            wp_cache_delete('eideasy_pending_provider_lock', 'options');
        }
        update_option('eideasy_pending_provider_lock', true, false);

        $pendingSignatures = get_option('eideasy_pending_provider_signatures', []);
        foreach ($pendingSignatures as $key => $value) {
            if ($value['doc_id'] === $docId) {
                unset($pendingSignatures[$key]);
            }
        }

        error_log("Removed pending contract as admin finished signing $state, $docId");
        update_option('eideasy_pending_provider_signatures', $pendingSignatures, false);
        update_option('eideasy_pending_provider_lock', false);
    }

    public static function prepareProviderSignature($state)
    {
        if (!get_option('eideasy_provider_signatures_enabled')) {
            error_log("eID Easy provider signatures not enabled");
            return;
        }

        $signState = get_option("eideasy_signing_state_$state");
        $docId     = $signState['doc_id'] ?? null;
        delete_option("eideasy_signing_state_$state");
        if (strlen($docId) === 0) {
            error_log("Empty state, cannot add service provider signature: $state");
            return;
        }

        error_log("Preparing eID Easy service provider signature");

        $bodyArr = EidEasyApi::sendCall('/files/download_external_signed_doc', ['doc_id' => $docId]);
        if (!$bodyArr) {
            error_log('Getting customer signed filed failed');
            return;
        }

        $signedFileContents = $bodyArr['signed_file_contents'];
        $filename           = $bodyArr['filename'];
        $signerId           = $bodyArr['signer_id'];

        $eidProviderState = wp_generate_uuid4();
        $redirect         = home_url('?provider-sign-return=true');
        $redirect         .= (parse_url($redirect, PHP_URL_QUERY) ? '&' : '?') . "eideasy-state=$eidProviderState";

        $params  = [
            'filename'           => $filename,
            'container'          => $signedFileContents,
            'signature_redirect' => $redirect,
            'noemails'           => true,
        ];
        $bodyArr = EidEasyApi::sendCall('/api/v2/prepare-add-signature', $params);
        if (!$bodyArr) {
            error_log('Preparing provider adding signature failed');
            return;
        }

        $docId = $bodyArr['doc_id'];
        update_option('eideasy_signing_state_' . $eidProviderState, ['doc_id' => $docId], false);

        $pendingSignature = [
            'doc_id'     => $docId,
            'filename'   => $filename,
            'signer_id'  => $signerId,
            'user_email' => $signState['user_email'] ?? '-',
        ];

        while (get_option('eideasy_pending_provider_lock')) {
            usleep(100000);
            wp_cache_delete('eideasy_pending_provider_lock', 'options');
        }
        update_option('eideasy_pending_provider_lock', true, false);

        $pendingSignatures = get_option('eideasy_pending_provider_signatures', []);
        foreach ($pendingSignatures as $key => $value) {
            if ($value['doc_id'] === $docId) {
                unset($pendingSignatures[$key]);
            }
        }
        $pendingSignatures[] = $pendingSignature;
        update_option('eideasy_pending_provider_signatures', $pendingSignatures, false);
        update_option('eideasy_pending_provider_lock', false);

        $clientId = get_option('eideasy_client_id');
        $signUrl  = "<a href=\"https://id.eideasy.com/add-signature?client_id=$clientId&doc_id=$docId\">siit.</a>";

        $to      = get_option('eideasy_notify_email_sender');
        $subject = "Leping ootab allkirja";
        $message = "Kliendi poolt pani allkirja $signerId failile $filename.<br><br> Teise allkirja saab lisada $signUrl";

        error_log("Sending provider notification e-mail to $to");
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($to, $subject, $message, $headers);

        error_log("New pending signature prepared $eidProviderState: " . json_encode($pendingSignature));
    }
}