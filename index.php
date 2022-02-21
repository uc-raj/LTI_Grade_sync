
<?php 
error_reporting(E_ERROR | E_PARSE);
require_once 'blti.php';
include_once 'blti_grade_send.php';
		
$secret = "LUVDX64L6USTS2VN";
// $secret = "LUVDX64L6USTS2VA";

$context = new BLTI( $secret, false, false );

if ( ! empty( $_REQUEST['relaunch_url'] ) && ! empty( $_REQUEST['platform_state'] ) ) {
    basicLTIRelaunch( $_REQUEST['relaunch_url'], $_REQUEST['platform_state'] );
}

if ( ! $context->valid ) {
    print_r(validateLTIData( $context->message, $key ));
}else{
    if ( !empty( $_REQUEST['lis_result_sourcedid'] ) && !empty( $_REQUEST['lis_outcome_service_url'] ) && $_REQUEST['roles'] == 'Learner') {
        // user input
        $score = 10;

        // Get score from LMS.
		$read_detail = getScoreThroughLti( $_REQUEST['oauth_consumer_key'], $secret, $_REQUEST['lis_outcome_service_url'], $_REQUEST['lis_result_sourcedid'] );
        if ( isset( $read_detail['grade'] ) ) {
            $score = max( $read_detail['grade'], $score );
        }
        
        // convert 0 to 1 form  
        $grade    = ( $score == 0 || $score == '' ) ? 0 : $score / 100;
        $postBody = str_replace( array( 'SOURCEDID', 'GRADE', 'OPERATION', 'MESSAGE' ), array( $_REQUEST['lis_result_sourcedid'], $grade, 'replaceResultRequest', uniqid() ), getPOXGradeRequest() );
        $ret      = sendOAuthBodyPOST( 'POST', $_REQUEST['lis_outcome_service_url'], $_REQUEST['oauth_consumer_key'], $secre, 'application/xml', $postBody );
        $response = parseResponse( $ret );
        if( $response['imsx_codeMajor'] == 'success' ){
            echo "Update successfull...";
        }else{
            echo "Inavlid data";
        }
    
    } else {
        echo "Your score will not be updated on your LMS because you have instructor permission.";
    }
}

function validateLTIData( $msg ) {
    $err_id = '';
    $now    = time();
    if ( ! $_REQUEST['resource_link_id'] ) {
        $err_id = 'resource_link_id is required.';
    } elseif ( $_REQUEST['lti_version'] != 'LTI-1p0' ) {
        $err_id = 'Incorrect LTI version. LTI version should be LTI-1p0.';
    } elseif ( $_REQUEST['lti_message_type'] != 'basic-lti-launch-request' ) {
        $err_id = 'Incorrect lti_message_type. LTI version should be basic-lti-launch-request.';
    } elseif ( ! $_REQUEST['oauth_timestamp'] ) {
        $err_id = 'Missing oauth_timestamp parameter.';
    } elseif ( abs( $now - $_REQUEST['oauth_timestamp'] ) > 300 ) {
        $err_id = 'Expired timestamp, yours ' . $_REQUEST['oauth_timestamp'] . ', ours ' . $now . '.';
    } elseif ( strpos( $msg, 'signature' ) ) {
        $our_sign = explode( ' ', $msg );
        $now      = $our_sign[3];
        $err_id   = 'Invalid signature, yours ' . $_REQUEST['oauth_signature'] . ', ours ' . $now . '.';
    } elseif ( $err_id == '' && $msg == '' ) {
        $err_id = 'Invalid encrypted data. LTI Secret might be wrong.';
    } 

    return $err_id;
}

// 1.1.2 version lti code
function basicLTIRelaunch( $relaunch_url, $platform_state ) {
	$page = <<< EOD
    <html>
    <head>
    <script>
        function doOnLoad() {
            document.forms[0].submit();
        }
        window.onload=doOnLoad;
    </script>
    </head>
    <body>
        <form action="{$relaunch_url}" method="post">
            <input type="hidden" name="platform_state" value="{$platform_state}" />
            <input type="hidden" name="tool_state" value="{$platform_state}" />
        </form>
    </body>
    </html>
EOD;
	print_r( $page );
}

// Read data from lms
function getScoreThroughLti( $lti_key, $lti_secret, $outcome_url, $sourceid ) {
	$read_detail = array();
	if ( $lti_key && $lti_secret && $outcome_url && $sourceid ) {
		$postBody = str_replace( array( 'SOURCEDID', 'OPERATION', 'MESSAGE' ), array( $sourceid, 'readResultRequest', uniqid() ), getPOXRequest() );
		$ret      = sendOAuthBodyPOST( 'POST', $outcome_url, $lti_key, $lti_secret, 'application/xml', $postBody );
		$response = parseResponse( $ret );
		if ( ! empty( $response ) ) {
			$read_detail['grade']  = ( isset( $response['textString'] ) ) ? $response['textString'] * 100 : $response['textString'];
		}
	}
	return $read_detail;
}