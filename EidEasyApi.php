<?php
defined('ABSPATH') or die('No script kiddies please!');

class EidEasyApi
{
    public static function sendCall($url, $params)
    {
        error_log("eID Easy API call $url");

        $clientId            = get_option('eideasy_client_id');
        $params['client_id'] = $clientId;
        $params['secret']    = get_option('eideasy_secret');
        $response            = wp_remote_post("https://id.eideasy.com$url", [
            'body'    => wp_json_encode($params),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("$clientId eID Easy API call $url failed: $error_message");
            wp_remote_get("https://id.eideasy.com/confirm_progress?message=" . urlencode($error_message));
            return null;
        }

        $bodyString = wp_remote_retrieve_body($response);
        $bodyArr    = json_decode($bodyString, true);
        if (($bodyArr['status'] ?? false) !== 'OK') {
            error_log("$clientId eID Easy invalid response $url: $bodyString");
            return null;
        }

        return $bodyArr;
    }
}
