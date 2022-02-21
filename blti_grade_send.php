<?php

require_once 'OAuth.php';

function sendOAuthBodyPOST($method, $endpoint, $oauth_consumer_key, $oauth_consumer_secret, $content_type, $body) {
    $hash = base64_encode(sha1($body, true));
    $parms = array('oauth_body_hash' => $hash);
    $test_token = '';
    $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
    $test_consumer = new OAuthConsumer($oauth_consumer_key, $oauth_consumer_secret, null);
    $acc_req = OAuthRequest::from_consumer_and_token($test_consumer, $test_token, $method, $endpoint, $parms);
    $acc_req->sign_request($hmac_method, $test_consumer, $test_token);
    // Pass this back up "out of band" for debugging
    global $LastOAuthBodyBaseString;
    $LastOAuthBodyBaseString = $acc_req->get_signature_base_string();
    $header = $acc_req->to_header();
    $header = $header."\r\nContent-Type: ".$content_type."\r\n";
    $params = array('http' => array('method' => 'POST', 'content' => $body, 'header' => $header));
    $ctx = stream_context_create($params);
    try {
     // $fp = @fopen($endpoint, 'r', false, $ctx);
    } catch (Exception $e) {
        $fp = false;
        echo "fopen fails";
    }
    if (isset($fp)) {
        $response = @stream_get_contents($fp);
    } else {
        $headers = explode("\r\n", $header);
        $response = sendXmlOverPost($endpoint, $body, $headers);
    }

    return $response;
}

function sendXmlOverPost($url, $xml, $header) {
    if (!function_exists('curl_init')) {
        return false;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

function getPOXGradeRequest() {
    return '<?xml version = "1.0" encoding = "UTF-8"?>
            <imsx_POXEnvelopeRequest xmlns = "http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
                <imsx_POXHeader>
                    <imsx_POXRequestHeaderInfo>
                    <imsx_version>V1.0</imsx_version>
                    <imsx_messageIdentifier>MESSAGE</imsx_messageIdentifier>
                    </imsx_POXRequestHeaderInfo>
                </imsx_POXHeader>
                <imsx_POXBody>
                    <OPERATION>
                    <resultRecord>
                        <sourcedGUID>
                        <sourcedId>SOURCEDID</sourcedId>
                        </sourcedGUID>
                        <result>
                        <resultScore>
                            <language>en-us</language>
                            <textString>GRADE</textString>
                        </resultScore>
                        </result>
                    </resultRecord>
                    </OPERATION>
                </imsx_POXBody>
            </imsx_POXEnvelopeRequest>';
}

function getPOXRequest() {
    return '<?xml version = "1.0" encoding = "UTF-8"?>
            <imsx_POXEnvelopeRequest xmlns = "http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
                <imsx_POXHeader>
                    <imsx_POXRequestHeaderInfo>
                    <imsx_version>V1.0</imsx_version>
                    <imsx_messageIdentifier>MESSAGE</imsx_messageIdentifier>
                    </imsx_POXRequestHeaderInfo>
                </imsx_POXHeader>
                <imsx_POXBody>
                    <OPERATION>
                    <resultRecord>
                        <sourcedGUID>
                        <sourcedId>SOURCEDID</sourcedId>
                        </sourcedGUID>
                    </resultRecord>
                    </OPERATION>
                </imsx_POXBody>
            </imsx_POXEnvelopeRequest>';
}

function getPOXResponse() {
    return '<?xml version="1.0" encoding="UTF-8"?>
            <imsx_POXEnvelopeResponse xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
                <imsx_POXHeader>
                    <imsx_POXResponseHeaderInfo>
                        <imsx_version>V1.0</imsx_version>
                        <imsx_messageIdentifier>%s</imsx_messageIdentifier>
                        <imsx_statusInfo>
                            <imsx_codeMajor>%s</imsx_codeMajor>
                            <imsx_severity>status</imsx_severity>
                            <imsx_description>%s</imsx_description>
                            <imsx_messageRefIdentifier>%s</imsx_messageRefIdentifier>
                        </imsx_statusInfo>
                    </imsx_POXResponseHeaderInfo>
                </imsx_POXHeader>
                <imsx_POXBody>%s
                </imsx_POXBody>
            </imsx_POXEnvelopeResponse>';
}

function parseResponse($response) {
    $retval = array();
    try {
        $xml = new SimpleXMLElement($response);
        $imsx_header = $xml->imsx_POXHeader->children();
        $parms = $imsx_header->children();
        $status_info = $parms->imsx_statusInfo;
        $retval['imsx_codeMajor'] = (string) $status_info->imsx_codeMajor;
        $retval['imsx_severity'] = (string) $status_info->imsx_severity;
        $retval['imsx_description'] = (string) $status_info->imsx_description;
        $retval['imsx_messageIdentifier'] = (string) $parms->imsx_messageIdentifier;
        $imsx_body = $xml->imsx_POXBody->children();
        $operation = $imsx_body->getName();
        $retval['response'] = $operation;
        $parms = $imsx_body->children();
    } catch (Exception $e) {
        $retval['imsx_codeMajor'] = "failure";
    }

    if ($operation == 'readResultResponse') {
        try {
            $retval['language'] = (string) $parms->result->resultScore->language;
            $retval['textString'] = (string) $parms->result->resultScore->textString;
        } catch (Exception $e) {
            throw new Exception("Error: Body parse error: ".$e->getMessage());
        }
    }
    return $retval;
}
