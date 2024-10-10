<?php

if (!function_exists('sendSuccessResponse')) {
    /**
     * Common function to send API response.
     *
     * @param bool $success
     * @param string $message
     * @param array|null $data
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    function sendSuccessResponse($message, $code = 200, $data = null,$token = null)
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data) {
            $response['data'] = $data;
        }

        if($token){
            $response['token'] = $token;
        }

        return response()->json($response, $code);
    }
}

if (!function_exists('sendErrorResponse')) {
    /**
     * Common function to send API response.
     *
     * @param bool $success
     * @param string $message
     * @param array|null $data
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    function sendErrorResponse($message, $code = 400, $error = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($error) {
            $response['error'] = $error;
        }

        return response()->json($response, $code);
    }
}