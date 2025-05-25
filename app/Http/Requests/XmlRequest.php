<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;

class XmlRequest extends Request
{
    /**
     * Check if the request content type is XML
     *
     * @return bool
     */
    public function isXml(): bool
    {
        $contentType = $this->header('Content-Type');
        return strpos($contentType, 'application/xml') !== false || 
               strpos($contentType, 'text/xml') !== false;
    }
    
    /**
     * Check if the request content type is JSON
     *
     * @return bool
     */
    public function isJson(): bool
    {
        $contentType = $this->header('Content-Type');
        return strpos($contentType, 'application/json') !== false;
    }
} 