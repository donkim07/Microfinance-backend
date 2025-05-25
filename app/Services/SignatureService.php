<?php

namespace App\Services;

use App\Models\FinancialServiceProvider;
use Exception;

class SignatureService
{
    /**
     * Sign data with the application's private key
     *
     * @param string $data
     * @return string
     * @throws Exception
     */
    public function signData(string $data): string
    {
        try {
            $privateKeyPath = storage_path('app/private/private_key.pem');
            
            if (!file_exists($privateKeyPath)) {
                throw new Exception("Private key file not found");
            }
            
            $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));
            
            if (!$privateKey) {
                throw new Exception("Failed to load private key");
            }
            
            $signature = null;
            if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new Exception("Failed to sign data");
            }
            
            openssl_free_key($privateKey);
            
            return base64_encode($signature);
        } catch (Exception $e) {
            throw new Exception("Signature creation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Verify signature with FSP's public key
     *
     * @param string $data
     * @param string $signature
     * @param string $fspCode
     * @return bool
     * @throws Exception
     */
    public function verifySignature(string $data, string $signature, string $fspCode): bool
    {
        try {
            $fsp = FinancialServiceProvider::where('fsp_code', $fspCode)->first();
            
            if (!$fsp || !$fsp->public_key_certificate) {
                throw new Exception("FSP public key certificate not found");
            }
            
            $publicKey = openssl_pkey_get_public($fsp->public_key_certificate);
            
            if (!$publicKey) {
                throw new Exception("Failed to load FSP public key");
            }
            
            $decodedSignature = base64_decode($signature);
            $result = openssl_verify($data, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);
            
            openssl_free_key($publicKey);
            
            if ($result === 1) {
                return true;
            } elseif ($result === 0) {
                return false;
            } else {
                throw new Exception("Signature verification error");
            }
        } catch (Exception $e) {
            throw new Exception("Signature verification failed: " . $e->getMessage());
        }
    }
    
    /**
     * Extract data from XML for signature verification
     *
     * @param string $xmlString
     * @return array
     * @throws Exception
     */
    public function extractDataAndSignature(string $xmlString): array
    {
        try {
            $xml = new \SimpleXMLElement($xmlString);
            
            if (!isset($xml->Data) || !isset($xml->Signature)) {
                throw new Exception("Missing Data or Signature element in XML");
            }
            
            $data = $xml->Data->asXML();
            $signature = (string) $xml->Signature;
            
            return [
                'data' => $data,
                'signature' => $signature
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to extract data and signature: " . $e->getMessage());
        }
    }
    
    /**
     * Sign an XML document
     *
     * @param string $xmlString
     * @return string
     * @throws Exception
     */
    public function signXml(string $xmlString): string
    {
        try {
            $xml = new \SimpleXMLElement($xmlString);
            
            if (!isset($xml->Data)) {
                throw new Exception("Missing Data element in XML");
            }
            
            $data = $xml->Data->asXML();
            $signature = $this->signData($data);
            
            // Create a new XML document with Data and Signature
            $document = new \SimpleXMLElement('<Document></Document>');
            $document->addChild('Data');
            
            // Import Data content
            $domData = dom_import_simplexml($document->Data);
            $dataFragment = dom_import_simplexml($xml->Data);
            $importedNode = $domData->ownerDocument->importNode($dataFragment, true);
            $domData->appendChild($importedNode);
            
            // Add Signature
            $document->addChild('Signature', $signature);
            
            return $document->asXML();
        } catch (Exception $e) {
            throw new Exception("Failed to sign XML: " . $e->getMessage());
        }
    }
    
    /**
     * Generate a key pair for testing purposes
     *
     * @return array
     * @throws Exception
     */
    public function generateKeyPair(): array
    {
        try {
            // Generate a new private/public key pair
            $config = [
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ];
            
            $res = openssl_pkey_new($config);
            
            if (!$res) {
                throw new Exception("Failed to generate key pair");
            }
            
            // Extract the private key
            openssl_pkey_export($res, $privateKey);
            
            // Extract the public key
            $publicKey = openssl_pkey_get_details($res)["key"];
            
            return [
                'private_key' => $privateKey,
                'public_key' => $publicKey
            ];
        } catch (Exception $e) {
            throw new Exception("Key pair generation failed: " . $e->getMessage());
        }
    }
} 