<?php
defined('ABSPATH') or die('No script kiddies please!');

class EidEasyApi
{
    public static function sendCall($url, $params)
    {
        error_log("eID Easy API call $url");

        if (get_option("eideasy_test_mode")) {
            $env = "test";
        } else {
            $env = "id";
        }

        $clientId            = get_option('eideasy_client_id');
        $params['client_id'] = $clientId;
        $params['secret']    = get_option('eideasy_secret');
        $response            = wp_remote_post("https://$env.eideasy.com$url", [
            'body'    => wp_json_encode($params),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("$clientId $env eID Easy API call $url failed: $error_message");
            wp_remote_get("https://$env.eideasy.com/confirm_progress?message=" . urlencode($error_message). ", params: ".json_encode($params));
            return null;
        }

        $bodyString = wp_remote_retrieve_body($response);
        $bodyArr    = json_decode($bodyString, true);
        if (($bodyArr['status'] ?? false) !== 'OK') {
            error_log("$clientId $env eID Easy invalid response $url: $bodyString, params: ".json_encode($params));
            return null;
        }

        return $bodyArr;
    }
}
