<?php

use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V12\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V12\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Lib\V12\GoogleAdsException;
use Google\Ads\GoogleAds\Util\V12\ResourceNames;
use Google\Ads\GoogleAds\V12\Errors\GoogleAdsError;
use Google\Ads\GoogleAds\V12\Services\ClickConversion;
use Google\Ads\GoogleAds\V12\Services\ClickConversionResult;
use Google\Ads\GoogleAds\V12\Services\CustomVariable;
use Google\Ads\GoogleAds\V12\Services\UploadClickConversionsResponse;
use Google\ApiCore\ApiException;
use Psr\Http\Message\ServerRequestInterface;
use Google\Auth\CredentialsLoader;
use Google\Auth\OAuth2;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;

class GoogleApi extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('google_login_model');

	}
	public function login(){
		$google_client = new Google_Client();

//		$credentialsFilePath = dirname(__FILE__).DIRECTORY_SEPARATOR."credentials".DIRECTORY_SEPARATOR."credentials.json";
//		print_r(file_get_contents(dirname(__FILE__).DIRECTORY_SEPARATOR."credentials".DIRECTORY_SEPARATOR."credentials.json"));
//		print_r($google_client->setAuthConfig($credentialsFilePath));
//		$google_client->useApplicationDefaultCredentials();
		$google_client->setClientId('85299470386-ql918l3bt9gjsqvuudgnret3ksdid49k.apps.googleusercontent.com');
		$google_client->setClientSecret('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');
//		$google_client->setDeveloperKey('AIzaSyCc2WHLYOACcw0EET-NtxTFkWFn7PaulOg');
		$google_client->setScopes('https://www.googleapis.com/auth/adwords');
		$google_client->setRedirectUri('http://localhost/google_api_test/google/login');
		$google_client->setAccessType('offline');
		$google_client->setApprovalPrompt('force');

		$data = array();
		if(isset($_GET["code"]))
		{
			$token = $google_client->fetchAccessTokenWithAuthCode($_GET["code"]);

			if(!isset($token["error"]))
			{
				$google_client->setAccessToken($token['access_token']);
				//insert data
				$user_data = array(
					'access_token' => $token['access_token'],
					'refresh_token' => $token['refresh_token'],
					'expires_in' => $token['expires_in'],
					'created_at'  => date('Y-m-d H:i:s')
				);
				$this->google_login_model->Insert_user_data($user_data);
				$this->session->set_userdata('access_token', $token['access_token']);
			}
		}
		$login_button = '';
		if(!$this->session->userdata('access_token'))
		{
			$login_button = '<a href="'.$google_client->createAuthUrl().'">Get token</a>';
			$data['login_button'] = $login_button;
			$this->load->view('welcome_message', $data);
		}
		else
		{
			$this->load->view('welcome_message', $data);
		}
	}

	public function index(){
		$customer_id = '8346657454';
		$conversion_action_id = 1051176957;
		$gclid = '612f9de8791b21000117a0e5';
		$gbraid = null;
		$wbraid = null;
		$current_date = date('Y-m-d H:i:s');
		$conversion_date_time = date(DATE_ATOM, strtotime($current_date));
		$conversion_date_time = str_replace('T', ' ', $conversion_date_time);
		$conversion_value = 0.00;
		$conversion_custom_variable_id = null;
		$conversion_custom_variable_value = null;
//		die();
		// Generate a refreshable OAuth2 credential for authentication.
		$oAuth2Credential = (new OAuth2TokenBuilder())->fromFile()->build();

		// Construct a Google Ads client configured from a properties file and the
		// OAuth2 credentials above.
		$googleAdsClient = (new GoogleAdsClientBuilder())
			->fromFile()
			->withOAuth2Credential($oAuth2Credential)
			->build();

		try {
			self::uploadConversion(
				$googleAdsClient,
				$customer_id,
				$conversion_action_id,
				$gclid,
				$gbraid,
				$wbraid,
				$conversion_date_time,
				$conversion_value,
				$conversion_custom_variable_id,
				$conversion_custom_variable_value
			);
		} catch (GoogleAdsException $googleAdsException) {
			printf(
				"Request with ID '%s' has failed.%sGoogle Ads failure details:%s",
				$googleAdsException->getRequestId(),
				PHP_EOL,
				PHP_EOL
			);
			foreach ($googleAdsException->getGoogleAdsFailure()->getErrors() as $error) {
				/** @var GoogleAdsError $error */
				printf(
					"\t%s: %s%s",
					$error->getErrorCode()->getErrorCode(),
					$error->getMessage(),
					PHP_EOL
				);
			}
			exit(1);
		} catch (ApiException $apiException) {
			printf(
				"ApiException was thrown with message '%s'.%s",
				$apiException->getMessage(),
				PHP_EOL
			);
			exit(1);
		}
	}

	private function uploadConversion(GoogleAdsClient $googleAdsClient, $customerId, $conversionActionId, $gclid=null,  $gbraid=null,  $wbraid=null, $conversionDateTime, $conversionValue, $conversionCustomVariableId=null, $conversionCustomVariableValue=null){
		$nonNullFields = array_filter(
			[$gclid, $gbraid, $wbraid],
			function ($field) {
				return !is_null($field);
			}
		);
		if (count($nonNullFields) !== 1) {
			throw new \UnexpectedValueException(
				sprintf(
					"Exactly 1 of gclid, gbraid or wbraid is required, but %d ID values were "
					. "provided",
					count($nonNullFields)
				)
			);
		}

		// Creates a click conversion by specifying currency as USD.
		$clickConversion = new ClickConversion([
			'conversion_action' =>
				ResourceNames::forConversionAction($customerId, $conversionActionId),
			'conversion_value' => $conversionValue,
			'conversion_date_time' => $conversionDateTime,
			'currency_code' => 'USD'
		]);
		// Sets the single specified ID field.
		if (!is_null($gclid)) {
			$clickConversion->setGclid($gclid);
		} elseif (!is_null($gbraid)) {
			$clickConversion->setGbraid($gbraid);
		} else {
			$clickConversion->setWbraid($wbraid);
		}


		if (!is_null($conversionCustomVariableId) && !is_null($conversionCustomVariableValue)) {
			$clickConversion->setCustomVariables([new CustomVariable([
				'conversion_custom_variable' => ResourceNames::forConversionCustomVariable(
					$customerId,
					$conversionCustomVariableId
				),
				'value' => $conversionCustomVariableValue
			])]);
		}

		// Issues a request to upload the click conversion.
		$conversionUploadServiceClient = $googleAdsClient->getConversionUploadServiceClient();
		/** @var UploadClickConversionsResponse $response */
		$response = $conversionUploadServiceClient->uploadClickConversions(
			$customerId,
			[$clickConversion],
			true
		);

		// Prints the status message if any partial failure error is returned.
		// Note: The details of each partial failure error are not printed here, you can refer to
		// the example HandlePartialFailure.php to learn more.
		if ($response->hasPartialFailureError()) {
			printf(
				"Partial failures occurred: '%s'.%s",
				$response->getPartialFailureError()->getMessage(),
				PHP_EOL
			);
		} else {
			// Prints the result if exists.
			/** @var ClickConversionResult $uploadedClickConversion */
			$uploadedClickConversion = $response->getResults()[0];
			printf(
				"Uploaded click conversion that occurred at '%s' from Google Click ID '%s' " .
				"to '%s'.%s",
				$uploadedClickConversion->getConversionDateTime(),
				$uploadedClickConversion->getGclid(),
				$uploadedClickConversion->getConversionAction(),
				PHP_EOL
			);
		}
	}

	public function logout()
	{
		$this->session->unset_userdata('access_token');

		$this->session->unset_userdata('user_data');

		redirect('google/login');
	}
}

