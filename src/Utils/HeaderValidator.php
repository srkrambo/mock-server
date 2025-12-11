<?php

namespace MockServer\Utils;

class HeaderValidator
{
    /**
     * Validate Content-Type header for different request types
     */
    public static function validateContentType($contentType, $expectedTypes = [])
    {
        if (empty($contentType)) {
            return [
                'valid' => false,
                'error' => 'Content-Type header is missing',
            ];
        }
        
        if (empty($expectedTypes)) {
            return ['valid' => true];
        }
        
        foreach ($expectedTypes as $expected) {
            if (strpos($contentType, $expected) !== false) {
                return ['valid' => true];
            }
        }
        
        return [
            'valid' => false,
            'error' => 'Invalid Content-Type. Expected: ' . implode(', ', $expectedTypes) . ', Got: ' . $contentType,
        ];
    }
    
    /**
     * Validate required headers are present
     */
    public static function validateRequiredHeaders($headers, $requiredHeaders)
    {
        $missing = [];
        
        foreach ($requiredHeaders as $required) {
            $found = false;
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, $required) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $required;
            }
        }
        
        if (!empty($missing)) {
            return [
                'valid' => false,
                'error' => 'Missing required headers: ' . implode(', ', $missing),
                'missing' => $missing,
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate Authorization header
     */
    public static function validateAuthorizationHeader($authHeader, $expectedScheme = null)
    {
        if (empty($authHeader)) {
            return [
                'valid' => false,
                'error' => 'Authorization header is missing',
            ];
        }
        
        if ($expectedScheme) {
            if (stripos($authHeader, $expectedScheme) !== 0) {
                return [
                    'valid' => false,
                    'error' => "Authorization header must use $expectedScheme scheme",
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate TUS protocol headers
     */
    public static function validateTusHeaders($headers, $method)
    {
        $required = ['Tus-Resumable'];
        
        switch ($method) {
            case 'POST':
                $required[] = 'Upload-Length';
                break;
            case 'PATCH':
                $required[] = 'Upload-Offset';
                $required[] = 'Content-Type';
                break;
        }
        
        return self::validateRequiredHeaders($headers, $required);
    }
    
    /**
     * Validate file upload size from Content-Length header
     */
    public static function validateContentLength($contentLength, $maxSize)
    {
        if (empty($contentLength)) {
            return [
                'valid' => false,
                'error' => 'Content-Length header is missing',
            ];
        }
        
        // Validate numeric content
        if (!is_numeric($contentLength)) {
            return [
                'valid' => false,
                'error' => 'Content-Length must be a numeric value',
            ];
        }
        
        $size = (int)$contentLength;
        
        if ($size > $maxSize) {
            return [
                'valid' => false,
                'error' => "File size ($size bytes) exceeds maximum allowed size ($maxSize bytes)",
            ];
        }
        
        if ($size <= 0) {
            return [
                'valid' => false,
                'error' => 'Invalid Content-Length value',
            ];
        }
        
        return ['valid' => true, 'size' => $size];
    }
    
    /**
     * Validate Accept header for API responses
     */
    public static function validateAcceptHeader($acceptHeader, $supportedTypes = ['application/json'])
    {
        if (empty($acceptHeader)) {
            // Default to JSON if not specified
            return ['valid' => true, 'type' => 'application/json'];
        }
        
        // Parse Accept header
        $accepts = array_map('trim', explode(',', $acceptHeader));
        
        foreach ($accepts as $accept) {
            // Handle quality values (e.g., application/json;q=0.8)
            $parts = explode(';', $accept);
            $mimeType = trim($parts[0]);
            
            if ($mimeType === '*/*' || in_array($mimeType, $supportedTypes)) {
                return ['valid' => true, 'type' => $mimeType === '*/*' ? $supportedTypes[0] : $mimeType];
            }
        }
        
        return [
            'valid' => false,
            'error' => 'Unsupported Accept header. Supported types: ' . implode(', ', $supportedTypes),
        ];
    }
    
    /**
     * Get header value case-insensitively
     */
    public static function getHeader($headers, $name)
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }
}
