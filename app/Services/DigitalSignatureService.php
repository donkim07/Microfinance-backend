<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DigitalSignatureService
{
    /**
     * Generate a signature token for verification.
     *
     * @param int $userId
     * @param string $action
     * @param int $referenceId
     * @return string
     */
    public function generateSignatureToken($userId, $action, $referenceId)
    {
        $token = Str::random(64);
        $expires = now()->addHours(24);
        
        // Store token data in cache or database
        \Cache::put(
            "signature_token_{$token}", 
            [
                'user_id' => $userId,
                'action' => $action,
                'reference_id' => $referenceId,
                'expires_at' => $expires,
            ],
            $expires
        );
        
        return $token;
    }
    
    /**
     * Verify a signature token.
     *
     * @param string $token
     * @return array|bool
     */
    public function verifySignatureToken($token)
    {
        $data = \Cache::get("signature_token_{$token}");
        
        if (!$data) {
            return false;
        }
        
        if (now()->isAfter(\Carbon\Carbon::parse($data['expires_at']))) {
            \Cache::forget("signature_token_{$token}");
            return false;
        }
        
        return $data;
    }
    
    /**
     * Store a signature image.
     *
     * @param string $signatureData Base64 encoded signature image
     * @param int $userId
     * @param string $documentType
     * @param int $documentId
     * @return string|bool
     */
    public function storeSignature($signatureData, $userId, $documentType, $documentId)
    {
        try {
            // Decode base64 signature
            $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
            $signatureData = str_replace(' ', '+', $signatureData);
            $signatureImage = base64_decode($signatureData);
            
            if (!$signatureImage) {
                return false;
            }
            
            // Generate a unique filename
            $filename = "signatures/{$documentType}/{$documentId}/{$userId}_" . Str::random(8) . '.png';
            
            // Store the signature image
            Storage::disk('private')->put($filename, $signatureImage);
            
            return $filename;
        } catch (\Exception $e) {
            \Log::error("Error storing signature: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Get a stored signature image.
     *
     * @param string $signaturePath
     * @return string|bool
     */
    public function getSignature($signaturePath)
    {
        try {
            if (!Storage::disk('private')->exists($signaturePath)) {
                return false;
            }
            
            $signatureData = Storage::disk('private')->get($signaturePath);
            
            return base64_encode($signatureData);
        } catch (\Exception $e) {
            \Log::error("Error retrieving signature: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Generate a digital signature for a document.
     *
     * @param int $userId
     * @param string $documentType
     * @param int $documentId
     * @param array $documentData
     * @return string|bool
     */
    public function signDocument($userId, $documentType, $documentId, $documentData)
    {
        try {
            // Create a unique signature reference
            $reference = Str::random(16);
            
            // Create signature record
            $signature = [
                'reference' => $reference,
                'user_id' => $userId,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'document_hash' => $this->hashDocumentData($documentData),
                'signed_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];
            
            // Store signature record in database
            // This would typically be done using a model, but for simplicity we'll use cache
            \Cache::forever("digital_signature_{$reference}", $signature);
            
            return $reference;
        } catch (\Exception $e) {
            \Log::error("Error signing document: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Verify a digital signature for a document.
     *
     * @param string $reference
     * @param array $documentData
     * @return bool
     */
    public function verifyDocumentSignature($reference, $documentData)
    {
        try {
            $signature = \Cache::get("digital_signature_{$reference}");
            
            if (!$signature) {
                return false;
            }
            
            $currentHash = $this->hashDocumentData($documentData);
            
            // Compare original hash with current hash
            return $signature['document_hash'] === $currentHash;
        } catch (\Exception $e) {
            \Log::error("Error verifying document signature: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Create a hash of document data for verification.
     *
     * @param array $documentData
     * @return string
     */
    private function hashDocumentData($documentData)
    {
        // Sort data to ensure consistent hash
        ksort($documentData);
        
        // Convert to JSON and hash
        $json = json_encode($documentData);
        
        return hash('sha256', $json);
    }
    
    /**
     * Generate a signed PDF document with signature.
     *
     * @param string $templatePath
     * @param array $data
     * @param string $signaturePath
     * @param array $signaturePosition
     * @return string|bool Path to generated PDF
     */
    public function generateSignedPDF($templatePath, $data, $signaturePath, $signaturePosition)
    {
        // This method would use a PDF library to generate a PDF with signature
        // For now, we'll return a placeholder implementation
        try {
            // This would be where you integrate with a PDF library like TCPDF, FPDF, or DomPDF
            // Here's a conceptual example of how it might work:
            
            /*
            $pdf = new \TCPDF();
            $pdf->AddPage();
            
            // Load template
            $pdf->setSourceFile($templatePath);
            $tplIdx = $pdf->importPage(1);
            $pdf->useTemplate($tplIdx);
            
            // Add data to PDF
            foreach ($data as $key => $value) {
                // Position and add text based on template mappings
            }
            
            // Add signature if available
            if ($signaturePath && Storage::disk('private')->exists($signaturePath)) {
                $signatureImage = Storage::disk('private')->get($signaturePath);
                $pdf->Image('@'.$signatureImage, 
                    $signaturePosition['x'], 
                    $signaturePosition['y'], 
                    $signaturePosition['width'], 
                    $signaturePosition['height']
                );
            }
            
            // Generate output filename
            $outputPath = 'signed_documents/'.Str::random(16).'.pdf';
            
            // Save PDF
            Storage::disk('private')->put($outputPath, $pdf->Output('', 'S'));
            
            return $outputPath;
            */
            
            // For now, return a placeholder
            return 'signed_documents/' . Str::random(16) . '.pdf';
        } catch (\Exception $e) {
            \Log::error("Error generating signed PDF: {$e->getMessage()}");
            return false;
        }
    }
} 