<?php

if (!function_exists('curlRequest')) {
    /**
     * Return response after sending a curl request
     * @param string $url , string $data, boolean $is_post, array $headers, boolean $auth
     * @return array
     */
    function curlRequest($url, $data = null, $is_post = false, $headers = null, $auth = false)
    {
        $curl = curl_init($url);
        if (!empty($headers) || $headers != null) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        if ($auth) {
            curl_setopt($curl, CURLOPT_USERPWD, $auth);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, false);

        if ($is_post) {
            $is_post === true ? curl_setopt($curl, CURLOPT_POST, 1) : curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        $output = curl_exec($curl);
        $header_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return ['header_code' => $header_code, 'body' => $output];
    }
}