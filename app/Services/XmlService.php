<?php

namespace App\Services;

use DOMDocument;
use SimpleXMLElement;
use Exception;

class XmlService
{
    /**
     * Parse an XML string to an associative array
     *
     * @param string $xmlString
     * @return array
     * @throws Exception
     */
    public function parseXmlToArray(string $xmlString): array
    {
        try {
            $xml = new SimpleXMLElement($xmlString);
            return $this->xmlToArray($xml);
        } catch (Exception $e) {
            throw new Exception("Failed to parse XML: " . $e->getMessage());
        }
    }

    /**
     * Convert SimpleXMLElement to array
     *
     * @param SimpleXMLElement $xml
     * @return array
     */
    private function xmlToArray(SimpleXMLElement $xml): array
    {
        $array = [];
        
        foreach ($xml->children() as $child) {
            $childName = $child->getName();
            
            if ($child->count() > 0) {
                // Element has children
                $value = $this->xmlToArray($child);
            } else {
                // Element has no children
                $value = (string) $child;
            }
            
            // Handle multiple elements with the same name
            if (isset($array[$childName])) {
                if (!is_array($array[$childName]) || !isset($array[$childName][0])) {
                    $array[$childName] = [$array[$childName]];
                }
                $array[$childName][] = $value;
            } else {
                $array[$childName] = $value;
            }
        }
        
        // Add attributes if any
        foreach ($xml->attributes() as $name => $value) {
            $array['@' . $name] = (string) $value;
        }
        
        return $array;
    }

    /**
     * Generate XML from an array
     *
     * @param array $data
     * @param string $rootElement
     * @return string
     * @throws Exception
     */
    public function generateXml(array $data, string $rootElement = 'Document'): string
    {
        try {
            $xml = new SimpleXMLElement("<{$rootElement}></{$rootElement}>");
            $this->arrayToXml($data, $xml);
            
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());
            
            return $dom->saveXML();
        } catch (Exception $e) {
            throw new Exception("Failed to generate XML: " . $e->getMessage());
        }
    }

    /**
     * Convert array to XML
     *
     * @param array $data
     * @param SimpleXMLElement $xml
     * @return void
     */
    private function arrayToXml(array $data, SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            // Handle attributes (keys starting with @)
            if (is_string($key) && str_starts_with($key, '@')) {
                $attributeName = substr($key, 1);
                $xml->addAttribute($attributeName, $value);
                continue;
            }
            
            // Handle normal elements
            if (is_array($value)) {
                // Check if the array is indexed or associative
                if (isset($value[0]) && is_array($value[0])) {
                    // Indexed array - create multiple elements with the same name
                    foreach ($value as $subValue) {
                        $subNode = $xml->addChild($key);
                        $this->arrayToXml($subValue, $subNode);
                    }
                } else {
                    // Associative array - create a single child element
                    $subNode = $xml->addChild($key);
                    $this->arrayToXml($value, $subNode);
                }
            } else {
                // Simple value
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }

    /**
     * Extract data from the XML 'Data' section
     *
     * @param string $xmlString
     * @return array
     * @throws Exception
     */
    public function extractDataFromXml(string $xmlString): array
    {
        try {
            $xml = new SimpleXMLElement($xmlString);
            
            if (!isset($xml->Data)) {
                throw new Exception("Missing 'Data' element in XML");
            }
            
            return $this->xmlToArray($xml->Data);
        } catch (Exception $e) {
            throw new Exception("Failed to extract data from XML: " . $e->getMessage());
        }
    }

    /**
     * Generate a standard response XML
     *
     * @param int $responseCode
     * @param string $description
     * @param string $fspCode
     * @param string $messageId
     * @return string
     */
    public function generateResponseXml(int $responseCode, string $description, string $fspCode, string $messageId): string
    {
        $data = [
            'Data' => [
                'Header' => [
                    'Sender' => 'ESS_UTUMISHI',
                    'Receiver' => 'FSPName',
                    'FSPCode' => $fspCode,
                    'MsgId' => $messageId,
                    'MessageType' => 'RESPONSE'
                ],
                'MessageDetails' => [
                    'ResponseCode' => $responseCode,
                    'Description' => $description
                ]
            ],
            'Signature' => 'Signature'
        ];

        return $this->generateXml($data);
    }
} 