<?php

class EidEasyProviderSigner
{
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

    public static function prepareProviderSignature()
    {
        $state = sanitize_text_field($_GET['eideasy-state'] ?? null);
        if (!$state) {
            return; // Nothing to do here as state not set
        }

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
        $redirect         = admin_url('admin.php?page=eid-easy-signer-settings&tab=pending-contracts');
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

        error_log("New pending signature prepared $eidProviderState: " . json_encode($pendingSignature));
    }
}