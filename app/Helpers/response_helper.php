<?php
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

if(!function_exists('storeBase64Image')){
    function storeBase64Image($base64Image, $directory)
    {
        if (!$base64Image) {
            return null; // Return null if no image is provided
        }

        // Extract the mime type and the Base64 data
        $imageParts = explode(';base64,', $base64Image);

        // Get the image extension from the mime type
        $imageTypeAux = explode('image/', $imageParts[0]);
        $imageType = $imageTypeAux[1]; // e.g., 'jpeg', 'png', 'gif'

        // Decode the Base64 string into binary data
        $imageData = base64_decode($imageParts[1]);

        // Generate a unique file name for the image
        $fileName = Str::random(10) . '.' . $imageType;

        // Store the image in the public storage folder (or any custom directory)
        $path = Storage::put("public/{$directory}/{$fileName}", $imageData);
        
        // Return the stored path or URL to save in the database
        return $directory.'/'.$fileName;
    }
}