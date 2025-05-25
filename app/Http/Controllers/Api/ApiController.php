<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\XmlService;
use App\Services\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Exception;

class ApiController extends Controller
{
    protected $xmlService;
    protected $signatureService;
    
    /**
     * Create a new controller instance.
     *
     * @param XmlService $xmlService
     * @param SignatureService $signatureService
     * @return void
     */
    public function __construct(XmlService $xmlService, SignatureService $signatureService)
    {
        $this->xmlService = $xmlService;
        $this->signatureService = $signatureService;
    }
    
    /**
     * Extract and validate XML from the request
     *
     * @param Request $request
     * @return array
     * @throws Exception
     */
    protected function extractAndValidateXml(Request $request): array
    {
        if (!$request->isXml() && !$request->isJson()) {
            throw new Exception('Invalid content type. XML or JSON expected.', 400);
        }
        
        $content = $request->getContent();
        
        if ($request->isJson()) {
            // If JSON, convert to XML format
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON format', 400);
            }
            $content = $this->xmlService->generateXml($data);
        }
        
        try {
            // Extract data and signature
            $parts = $this->signatureService->extractDataAndSignature($content);
            $data = $parts['data'];
            $signature = $parts['signature'];
            
            // Parse the XML to array
            $parsedData = $this->xmlService->parseXmlToArray($data);
            
            // Get FSP code from header
            if (!isset($parsedData['Header']['FSPCode'])) {
                throw new Exception('Missing FSP code in the request header', 400);
            }
            
            $fspCode = $parsedData['Header']['FSPCode'];
            
            // Verify signature (uncomment in production)
            // if (!$this->signatureService->verifySignature($data, $signature, $fspCode)) {
            //     throw new Exception('Invalid signature', 401);
            // }
            
            return $parsedData;
        } catch (Exception $e) {
            throw new Exception('Failed to process XML: ' . $e->getMessage(), 400);
        }
    }
    
    /**
     * Generate standard XML response
     *
     * @param int $code
     * @param string $description
     * @param string $fspCode
     * @param string $messageId
     * @return Response
     */
    protected function xmlResponse(int $code, string $description, string $fspCode, string $messageId): Response
    {
        $xml = $this->xmlService->generateResponseXml($code, $description, $fspCode, $messageId);
        
        return response($xml)
            ->header('Content-Type', 'application/xml')
            ->header('X-Response-Code', $code);
    }
    
    /**
     * Generate standard error response
     *
     * @param Exception $e
     * @param string $fspCode
     * @param string $messageId
     * @return Response
     */
    protected function errorResponse(Exception $e, string $fspCode = '0000', string $messageId = '0000'): Response
    {
        $statusCode = $e->getCode() ?: 500;
        $description = $e->getMessage() ?: 'An error occurred';
        
        $xml = $this->xmlService->generateResponseXml(8005, $description, $fspCode, $messageId);
        
        return response($xml, $statusCode > 0 ? $statusCode : 500)
            ->header('Content-Type', 'application/xml')
            ->header('X-Response-Code', 8005);
    }
} 