<?php

namespace Alice\Configuration\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Alice\Configuration\Models\KycToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Alice\Configuration\AliceConfigurationFacade;




class AliceController extends Controller
{
    
    public function authenticate(Request $request){
        try {
             // Define the required parameters
            $requiredParameters = ['alice_key'];
            // Validate required parameters
            $validationResult = $this->validateRequiredParameters($request, $requiredParameters);
            if ($validationResult !== null) {
                return $validationResult;
            }
            $url = config('aliceConstants.KYC_URL');
            $headers = ['apikey:' . $request->alice_key];
            $loginResponse = curlRequest($url . 'login_token', NULL, false, $headers, false);
            if ($loginResponse['header_code'] == 200) {
                $loginToken = json_decode($loginResponse['body'])->token;
                $authHeaders = ['Content-Type: application/json', 'Authorization: Bearer ' . $loginToken];
                $backendResponse = curlRequest($url . 'backend_token', NULL, false, $authHeaders, false);
                $backendToken = json_decode($backendResponse['body'])->token;
            }
            $data = [
                'login_token'    => $loginToken,
                'backend_token' => $backendToken
            ];
            return $data ;
        } catch (\Exception $e) {
            return returnErrors($e, 'kycToken');
        }

    }


    public function createAliceKycUser(Request $request){
       
         // Define the required parameters
        $requiredParameters = ['login_token', 'backend_token', 'email', 'first_name', 'last_name'];
         // Check if all required parameters are present in the request
         $validationResult = $this->validateRequiredParameters($request, $requiredParameters);
         if ($validationResult !== null) {
             return $validationResult;
         }
        // Check if all required parameters are present in the request
        $validator = Validator::make($request->all(), [
            'login_token' => 'required',
            'backend_token' => 'required',
            'email' => 'required|email',
            'first_name' => 'required',
            'last_name' => 'required',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $url = config('aliceConstants.KYC_URL');
        $aliceToken=$request->backend_token;
        $payLoad = [
            'first_name' => $request['first_name'],
            'last_name'  => $request['last_name'],
            'email'      => $request['email'],
        ];
        $boundary = uniqid();
        $backendToken = [
            "Content-Type: multipart/form-data; boundary=---" . $boundary,
            'Authorization: Bearer ' . $aliceToken
        ];

        $aliceUserId = curlRequest(
            $url . 'user',
            self::buildMultipartFormData($payLoad, $boundary),
            true,
            $backendToken,
            false
        );
        if ($aliceUserId['header_code'] == 401) {      
            return response()->json(['error' => 'token expired'], 401);
        }
        if ($aliceUserId['header_code'] == 200) {
            $userId = json_decode($aliceUserId['body'])->user_id;
            $loginToken=$request->login_token;
            $userKycStatus = self::kycUserStatus( $loginToken,$userId);
            $data = [
                'kyc_user_id' => $userId,
                'kyc_status'  => $userKycStatus ,
            ];
            return ['data' => $data];

        }
      
    }


       /**
     * the following function is used to make multipart form data
     * @param $data
     * @param $boundary
     * @return string
     */
    public static function buildMultipartFormData($data, $boundary): string
    {
        $formData = '';
        foreach ($data as $name => $value) {
            $formData .= "-----$boundary\r\n";
            $formData .= "Content-Disposition: form-data; name=\"$name\"\r\n\r\n";
            $formData .= "$value\r\n";
        }
        $formData .= "-----$boundary--\r\n";
        return $formData;
    }

    public static function kycUserStatus($loginToken,$userId)
    {
        try {
            if ($userId) {
                $url = config('aliceConstants.KYC_URL');
                $userLoginToken =  $loginToken;
                $loginToken = ['Authorization: Bearer ' . $userLoginToken];

                $userToken = curlRequest($url . 'user_token/' . $userId, NULL, false, $loginToken, false);
                if ($userToken['header_code'] == 200) {

                    $userKycStatus = curlRequest(
                        $url . 'user/status',
                        NULL,
                        false,
                        ['Authorization: Bearer ' . json_decode($userToken['body'])->token],
                        false
                    );
                    if ($userKycStatus['header_code'] == 200) {
                        return json_decode($userKycStatus['body'])->user->state;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            }
            return false;
        } catch (\Exception $e) {
            return returnErrors($e, __FUNCTION__);
        }
    }



      /**
     * the given function used to get user token from alice to perform users kyc the token return the iframe to perform kyc
     * @param $token
     * @return false|JsonResponse
     */
    public function performKyc(Request $request)
    {
        try {
            $requiredParameters = ['login_token','kyc_user_id' ];
            

            // // Check if all required parameters are present in the request
             $validationResult = $this->validateRequiredParameters($request, $requiredParameters);
             if ($validationResult !== null) {
                 return $validationResult;
             }
            $url = config('aliceConstants.KYC_URL');
            $loginToken = ['Authorization: Bearer ' . $request->login_token];

            $userToken = curlRequest(
                $url . 'user_token/' . $request->kyc_user_id,
                NULL,
                false,
                $loginToken,
                false
            );
            if ($userToken['header_code'] == 401) {      
                return response()->json(['error' => 'token expired'], 401);
            }
            if ($userToken['header_code'] == 200) {
                return ['user_token' => json_decode($userToken['body'])->token];
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return returnErrors($e, __FUNCTION__);
        }
    }



     /**
     * the given function used to get backend token with user
     * @param $token
     * @return false|JsonResponse
     */
    public function backendTokenWithUserId(Request $request)
    {
        try {

            $requiredParameters = ['kyc_user_id','login_token' ];
            // Create an array to store missing parameters
           $missingParameters = [];
           // Check if all required parameters are present in the request
            // Validate required parameters
            $validationResult = $this->validateRequiredParameters($request, $requiredParameters);
            if ($validationResult !== null) {
                return $validationResult;
            }
            $url = config('aliceConstants.KYC_URL');
            // $aliceToken = KycToken::first();
            // $KycUserToken = KycUser::where('user_token', $token)->first();
            $loginToken = ['Authorization: Bearer ' . $request->login_token];

            $userToken = curlRequest(
                $url . 'backend_token/' . $request->kyc_user_id,
                NULL,
                false,
                $loginToken,
                false
            );
            if ($userToken['header_code'] == 401) {      
                return response()->json(['error' => 'token expired'], 401);
            }
            if ($userToken['header_code'] == 200) {
                return ['backend_token_with_userid' => json_decode($userToken['body'])->token];

            } else {
                return false;
            }
        } catch (\Exception $e) {
            return returnErrors($e, __FUNCTION__);
        }
    }


        /**
     * @param $backendTokenWithUser
     * @return array|JsonResponse|mixed
     */
    public static function getKycUserReport(Request $request)
    {
        try {
                 // Define the required parameters
            $requiredParameters = ['backend_token'];
              // Check if all required parameters are present in the request
           // Validate required parameters
            $validationResult = self::validateRequiredParameters($request, $requiredParameters);
            if ($validationResult !== null) {
                return $validationResult;
            }
            $url = config('aliceConstants.KYC_URL');
            //creating multipart form data for alice user
            $payLoad = [];
            $boundary = uniqid();
            $backendTokenWithUser=$request->backend_token;
            $backendToken = [
                "Content-Type: multipart/form-data; boundary=---" . $boundary,
                'Authorization: Bearer ' . $backendTokenWithUser
            ];

            $aliceUserId = curlRequest(
                $url . 'user/report',
                self::buildMultipartFormData($payLoad, $boundary),
                false,
                $backendToken,
                false
            );
            // dd($aliceUserId );
            if ($aliceUserId['header_code'] == 401) {      
                return response()->json(['error' => 'token expired'], 401);
            }
            if ($aliceUserId['header_code'] == 200) {
                return json_decode($aliceUserId['body']);
            } else {
                return $aliceUserId;
            }
        } catch (\Exception $e) {
            return returnErrors($e, __FUNCTION__);
        }
    }


    public static function updateUserStatusAfterDocumentCheck(Request $request)
    {
        try {
            // Define the required parameters
            $requiredParameters = ['backend_token_with_userid',"status"];
                // Check if all required parameters are present in the request
             // Validate required parameters
             $validationResult = self::validateRequiredParameters($request, $requiredParameters);
             if ($validationResult !== null) {
                 return $validationResult;
             }
            $allowedStatusValues = ['REJECTED', 'TO_REVIEW', 'ACCEPTED', 'NOT_STARTED', 'IN_PROGRESS'];
             // Check if the 'status' parameter is present and is a valid value
            if (!$request->has('status') || !in_array($request->status, $allowedStatusValues)) {
                // Return an error response specifying the allowed status values
                return response()->json(['error' => 'Invalid status parameter. Allowed values are: ' . implode(', ', $allowedStatusValues)], 400);
            }


            $url = config('aliceConstants.KYC_URL');
            //creating multipart form data for alice user
            $backendTokenWithUser = [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $request->backend_token_with_userid
            ];
            $data = [
                'state'          => $request->status,
                'operator'       => 'auto',
                'update_reasons' => [
                    [
                        'reason' => $request->status == 'ACCEPTED' ? 'Document Matched' : 'Invalid Document',
                    ]
                ]
            ];

            $updateStatus = curlRequest($url . 'user/state', json_encode($data), 'PATCH', $backendTokenWithUser, false);
            if ($updateStatus['header_code'] == 401) {      
                return response()->json(['error' => 'token expired'], 401);
            }
            if ($updateStatus['header_code'] == 200) {
                return ['Status' => 'updated'];
                // return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return returnErrors($e, __FUNCTION__);
        }
    }
     


    public function updateUserReportAgainstThresholdCheck(Request $request)
    {
        try {
                // Define the required parameters
            $requiredParameters = ['backend_token'];
                // Check if all required parameters are present in the request
            // Validate required parameters
            $validationResult = self::validateRequiredParameters($request, $requiredParameters);
            if ($validationResult !== null) {
                return $validationResult;
            }
            $backendToken = $request->backend_token;
            if ($backendToken){
                $report = self::getKycUserReport(new request(['backend_token'=>$request->backend_token]));
                if(isset($report->original['error'])){
                    return response()->json(['error' => 'token expired'], 401);
                }
                $documentSecurity =$report->report->documents[0]->result->document_security;
                $documentRead=$report->report->documents[0]->result->document_read;
                $documentUpload=$report->report->documents[0]->meta->completed; 
                // Initialize a new array to store the extracted items      
                $extractedArray = [];
                // Define the keys you are looking for
                $desiredKeys = ['not_a_printed_document', 'not_a_screen_document'];
                $responseArray=$report->report->documents[0]->checks;
                // Iterate through the response array
                foreach ($responseArray as $item) {
                    // Check if the current item's key is one of the desired keys
                    if (in_array($item->key, $desiredKeys)) {
                        // Push the current item into the extracted array
                        $extractedArray[] = $item;
                    }
                }
                $extractedArray[] = (object)[
                    "key" => 'document_security',
                    "value" => $documentSecurity,
                ];
                $extractedArray[] = (object)[
                    "key" => 'document_read',
                    "value" =>  $documentRead,
                ];
                $extractedArray[] = (object)[
                    "key" => 'document_upload',
                    "value" => $documentUpload,
                ];

                $score = 0;
                  // Iterate through the array of objects
                foreach ($extractedArray as $object) {
                    if ($object->key == 'not_a_printed_document' && $object->value > 50 ) {
                        $score = 100;
                    }
                    if ($object->key == ('not_a_screen_document')  && $object->value > 50 ) {
                        $score = 100;
                    }
                    if (($object->key == 'document_read' && $object->value =='ok') && ($object->key == 'document_security' && $object->value =='ok') ) {
                        $score =  100;
                    }
                    else{
                        $score-=100;
                    }
                    if ($object->key == 'document_upload' && $object->value =='true' ) {

                        $score = 100;
                    }
                    else{
                        $score-=100;
                    }
                }
                $status='NOT_STARTED';
                $score > 0 ? $status='ACCEPTED' : $status='REJECTED';

                $updateStatus=self::updateUserStatusAfterDocumentCheck(new request(['backend_token_with_userid'=>$backendToken,'status'=>$status]));
                $message="";
                $status=='ACCEPTED'? $message="Your kyc accepted":$message="Your kyc rejected";
                return ['Status' => $message];

                }
            else{
                return response()->json(['error' => ['Token is required']], 204);
            }
        } catch (\Exception $e) {
            // Call the returnErrors function and pass the exception and the current function name as parameters
            return returnErrors($e, __FUNCTION__);
        }
    } 






    /**
     * Validate the presence of required parameters in the request.
     *
     * @param Request $request
     * @param array $requiredParameters
     * @return \Illuminate\Http\JsonResponse|null
     */
    public static function validateRequiredParameters(Request $request, array $requiredParameters)
    {
         // Create an array to store missing parameters
        $missingParameters = [];

        // Check if all required parameters are present in the request
        foreach ($requiredParameters as $param) {
            if (!$request->has($param)) {
                // If a required parameter is missing, add it to the list of missing parameters
                $missingParameters[] = $param;
            }
        }

        // Check if two or more parameters are missing
        if (count($missingParameters) >= 1) {
            // Return an error response specifying the missing parameters
            return ['error' => 'The following parameters are missing: ' . implode(', ', $missingParameters)];
        }

        // Validation passed
        return null;
    }





}
