<?php

namespace MockServer\Utils;

/**
 * File-based rate limiter for production use
 * No external dependencies (no database required)
 */
class RateLimiter
{
    private $config;
    private $storageDir;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->storageDir = $config['storage']['rate_limits'] ?? '/tmp/rate_limits';
        
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    /**
     * Check if a request should be rate limited
     * 
     * @param string $identifier Unique identifier (IP address, user ID, etc.)
     * @param string $type Type of rate limit ('ip', 'global', 'endpoint')
     * @param array $limits Limit configuration
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => timestamp]
     */
    public function checkLimit($identifier, $type, $limits)
    {
        $maxRequests = $limits['max_requests'] ?? 100;
        $window = $limits['window'] ?? 60;
        
        $filename = $this->getFilename($identifier, $type);
        $data = $this->readLimitData($filename);
        
        $currentTime = time();
        
        // Reset if window has expired
        if ($currentTime >= $data['reset_at']) {
            $data = [
                'count' => 0,
                'reset_at' => $currentTime + $window,
            ];
        }
        
        // Increment request count
        $data['count']++;
        
        // Save updated data
        $this->saveLimitData($filename, $data);
        
        $allowed = $data['count'] <= $maxRequests;
        $remaining = max(0, $maxRequests - $data['count']);
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_at' => $data['reset_at'],
            'limit' => $maxRequests,
        ];
    }
    
    /**
     * Check IP-based rate limit
     */
    public function checkIpLimit($ip)
    {
        if (!$this->config['rate_limit']['ip_based']['enabled']) {
            return ['allowed' => true];
        }
        
        return $this->checkLimit(
            $ip,
            'ip',
            $this->config['rate_limit']['ip_based']
        );
    }
    
    /**
     * Check global rate limit
     */
    public function checkGlobalLimit()
    {
        if (!$this->config['rate_limit']['global']['enabled']) {
            return ['allowed' => true];
        }
        
        return $this->checkLimit(
            'global',
            'global',
            $this->config['rate_limit']['global']
        );
    }
    
    /**
     * Check endpoint-specific rate limit
     */
    public function checkEndpointLimit($ip, $endpoint)
    {
        $endpointLimits = $this->config['rate_limit']['endpoint_specific'] ?? [];
        
        if (!isset($endpointLimits[$endpoint])) {
            return ['allowed' => true];
        }
        
        return $this->checkLimit(
            $ip . ':' . $endpoint,
            'endpoint',
            $endpointLimits[$endpoint]
        );
    }
    
    /**
     * Get filename for rate limit data
     */
    private function getFilename($identifier, $type)
    {
        $hash = md5($identifier);
        return $this->storageDir . '/' . $type . '_' . $hash . '.json';
    }
    
    /**
     * Read rate limit data from file
     */
    private function readLimitData($filename)
    {
        if (!file_exists($filename)) {
            return [
                'count' => 0,
                'reset_at' => time() + 60,
            ];
        }
        
        $content = file_get_contents($filename);
        $data = json_decode($content, true);
        
        if (!is_array($data)) {
            return [
                'count' => 0,
                'reset_at' => time() + 60,
            ];
        }
        
        return $data;
    }
    
    /**
     * Save rate limit data to file
     */
    private function saveLimitData($filename, $data)
    {
        file_put_contents($filename, json_encode($data));
    }
    
    /**
     * Clean up expired rate limit files
     */
    public function cleanup()
    {
        $files = glob($this->storageDir . '/*.json');
        $currentTime = time();
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data) && isset($data['reset_at']) && $currentTime >= $data['reset_at'] + 3600) {
                // Delete files that are expired for more than 1 hour
                unlink($file);
            }
        }
    }
}
