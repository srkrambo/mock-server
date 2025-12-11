<?php

namespace MockServer\Handlers;

class FileUploadHandler
{
    private $config;
    private $uploadDir;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->uploadDir = $config['storage']['uploads'];
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Handle direct raw/binary upload
     */
    public function handleRawUpload($filename = null, $maxSize = null)
    {
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            return ['success' => false, 'error' => 'No data received'];
        }
        
        // Check size limit
        if ($maxSize !== null && strlen($input) > $maxSize) {
            return [
                'success' => false,
                'error' => sprintf('Upload size (%d bytes) exceeds maximum allowed size of %d bytes', strlen($input), $maxSize),
            ];
        }
        
        if (!$filename) {
            $filename = 'upload_' . time() . '_' . uniqid();
        }
        
        $filepath = $this->uploadDir . '/' . basename($filename);
        
        if (file_put_contents($filepath, $input) !== false) {
            return [
                'success' => true,
                'filename' => basename($filename),
                'filepath' => $filepath,
                'size' => strlen($input),
                'upload_type' => 'raw',
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to save file'];
    }
    
    /**
     * Handle multipart form-data upload
     */
    public function handleMultipartUpload($maxSize = null)
    {
        if (empty($_FILES)) {
            return ['success' => false, 'error' => 'No files uploaded'];
        }
        
        $uploadedFiles = [];
        
        foreach ($_FILES as $fieldName => $file) {
            if (is_array($file['name'])) {
                // Multiple files
                for ($i = 0; $i < count($file['name']); $i++) {
                    if ($file['error'][$i] === UPLOAD_ERR_OK) {
                        // Check size limit
                        if ($maxSize !== null && $file['size'][$i] > $maxSize) {
                            return [
                                'success' => false,
                                'error' => sprintf('File %s size (%d bytes) exceeds maximum allowed size of %d bytes', 
                                    $file['name'][$i], $file['size'][$i], $maxSize),
                            ];
                        }
                        
                        $result = $this->saveUploadedFile(
                            $file['tmp_name'][$i],
                            $file['name'][$i],
                            $file['size'][$i],
                            $file['type'][$i]
                        );
                        if ($result['success']) {
                            $uploadedFiles[] = $result;
                        }
                    }
                }
            } else {
                // Single file
                if ($file['error'] === UPLOAD_ERR_OK) {
                    // Check size limit
                    if ($maxSize !== null && $file['size'] > $maxSize) {
                        return [
                            'success' => false,
                            'error' => sprintf('File %s size (%d bytes) exceeds maximum allowed size of %d bytes', 
                                $file['name'], $file['size'], $maxSize),
                        ];
                    }
                    
                    $result = $this->saveUploadedFile(
                        $file['tmp_name'],
                        $file['name'],
                        $file['size'],
                        $file['type']
                    );
                    if ($result['success']) {
                        $uploadedFiles[] = $result;
                    }
                }
            }
        }
        
        if (empty($uploadedFiles)) {
            return ['success' => false, 'error' => 'No files were successfully uploaded'];
        }
        
        return [
            'success' => true,
            'files' => $uploadedFiles,
            'upload_type' => 'multipart',
        ];
    }
    
    /**
     * Handle Base64 encoded upload from JSON
     */
    public function handleBase64Upload($data, $maxSize = null)
    {
        if (!isset($data['filename']) || !isset($data['content'])) {
            return ['success' => false, 'error' => 'Missing filename or content'];
        }
        
        $filename = basename($data['filename']);
        $base64Content = $data['content'];
        
        // Remove data URI prefix if present
        if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Content, $matches)) {
            $mimeType = $matches[1];
            $base64Content = $matches[2];
        }
        
        $decodedContent = base64_decode($base64Content, true);
        
        if ($decodedContent === false) {
            return ['success' => false, 'error' => 'Invalid base64 content'];
        }
        
        // Check size limit
        if ($maxSize !== null && strlen($decodedContent) > $maxSize) {
            return [
                'success' => false,
                'error' => sprintf('File size (%d bytes) exceeds maximum allowed size of %d bytes', 
                    strlen($decodedContent), $maxSize),
            ];
        }
        
        $filepath = $this->uploadDir . '/' . $filename;
        
        if (file_put_contents($filepath, $decodedContent) !== false) {
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => strlen($decodedContent),
                'upload_type' => 'base64',
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to save file'];
    }
    
    /**
     * Handle TUS resumable upload
     */
    public function handleTusUpload($method, $headers, $maxSize = null)
    {
        $tusResumable = $headers['Tus-Resumable'] ?? null;
        
        if (!$tusResumable) {
            return ['success' => false, 'error' => 'TUS protocol not detected - Tus-Resumable header missing'];
        }
        
        // Validate TUS version
        if ($tusResumable !== '1.0.0') {
            return ['success' => false, 'error' => 'Unsupported TUS version. Only 1.0.0 is supported'];
        }
        
        switch ($method) {
            case 'POST':
                return $this->tusCreate($headers, $maxSize);
            case 'PATCH':
                return $this->tusPatch($headers, $maxSize);
            case 'HEAD':
                return $this->tusHead($headers);
            case 'OPTIONS':
                return $this->tusOptions($maxSize);
            default:
                return ['success' => false, 'error' => 'Unsupported TUS method'];
        }
    }
    
    private function tusCreate($headers, $maxSize = null)
    {
        $uploadLength = $headers['Upload-Length'] ?? null;
        $uploadMetadata = $headers['Upload-Metadata'] ?? '';
        
        if (!$uploadLength) {
            return ['success' => false, 'error' => 'Upload-Length header required'];
        }
        
        // Check size limit
        if ($maxSize !== null && (int)$uploadLength > $maxSize) {
            return [
                'success' => false,
                'error' => sprintf('Upload length (%d bytes) exceeds maximum allowed size of %d bytes', 
                    (int)$uploadLength, $maxSize),
            ];
        }
        
        // Generate unique ID for this upload
        $uploadId = uniqid('tus_', true);
        $filepath = $this->uploadDir . '/' . $uploadId;
        
        // Create empty file
        file_put_contents($filepath, '');
        
        // Store metadata
        $metadata = [
            'id' => $uploadId,
            'length' => (int)$uploadLength,
            'offset' => 0,
            'metadata' => $uploadMetadata,
            'created' => time(),
            'max_size' => $maxSize,
        ];
        
        file_put_contents($filepath . '.meta', json_encode($metadata));
        
        return [
            'success' => true,
            'upload_id' => $uploadId,
            'location' => '/upload/' . $uploadId,
            'headers' => [
                'Location' => '/upload/' . $uploadId,
                'Tus-Resumable' => '1.0.0',
            ],
        ];
    }
    
    private function tusPatch($headers, $maxSize = null)
    {
        $uploadOffset = $headers['Upload-Offset'] ?? null;
        $contentType = $headers['Content-Type'] ?? '';
        
        if ($contentType !== 'application/offset+octet-stream') {
            return ['success' => false, 'error' => 'Invalid Content-Type for PATCH'];
        }
        
        // Extract upload ID from request URI
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('/\/upload\/([^\/]+)/', $uri, $matches);
        $uploadId = $matches[1] ?? null;
        
        if (!$uploadId) {
            return ['success' => false, 'error' => 'Upload ID not found'];
        }
        
        $filepath = $this->uploadDir . '/' . $uploadId;
        $metafile = $filepath . '.meta';
        
        if (!file_exists($metafile)) {
            return ['success' => false, 'error' => 'Upload not found'];
        }
        
        $metadata = json_decode(file_get_contents($metafile), true);
        
        if ($uploadOffset !== null && (int)$uploadOffset !== $metadata['offset']) {
            return ['success' => false, 'error' => 'Upload-Offset mismatch'];
        }
        
        // Append data
        $input = file_get_contents('php://input');
        
        // Check if appending this chunk would exceed max size
        $maxSizeToCheck = $metadata['max_size'] ?? $maxSize;
        if ($maxSizeToCheck !== null && ($metadata['offset'] + strlen($input)) > $maxSizeToCheck) {
            return [
                'success' => false,
                'error' => sprintf('Upload would exceed maximum allowed size of %d bytes', $maxSizeToCheck),
            ];
        }
        
        file_put_contents($filepath, $input, FILE_APPEND);
        
        $metadata['offset'] += strlen($input);
        file_put_contents($metafile, json_encode($metadata));
        
        $complete = $metadata['offset'] >= $metadata['length'];
        
        return [
            'success' => true,
            'upload_id' => $uploadId,
            'offset' => $metadata['offset'],
            'complete' => $complete,
            'headers' => [
                'Upload-Offset' => $metadata['offset'],
                'Tus-Resumable' => '1.0.0',
            ],
        ];
    }
    
    private function tusHead($headers)
    {
        // Extract upload ID from request URI
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('/\/upload\/([^\/]+)/', $uri, $matches);
        $uploadId = $matches[1] ?? null;
        
        if (!$uploadId) {
            return ['success' => false, 'error' => 'Upload ID not found'];
        }
        
        $filepath = $this->uploadDir . '/' . $uploadId;
        $metafile = $filepath . '.meta';
        
        if (!file_exists($metafile)) {
            return ['success' => false, 'error' => 'Upload not found'];
        }
        
        $metadata = json_decode(file_get_contents($metafile), true);
        
        return [
            'success' => true,
            'headers' => [
                'Upload-Offset' => $metadata['offset'],
                'Upload-Length' => $metadata['length'],
                'Tus-Resumable' => '1.0.0',
            ],
        ];
    }
    
    private function tusOptions($maxSize = null)
    {
        // Use maxSize if provided, otherwise fall back to config
        $tusMaxSize = $maxSize ?? ($this->config['tus']['max_size'] ?? 104857600);
        
        return [
            'success' => true,
            'headers' => [
                'Tus-Resumable' => '1.0.0',
                'Tus-Version' => '1.0.0',
                'Tus-Extension' => 'creation,termination',
                'Tus-Max-Size' => $tusMaxSize,
            ],
        ];
    }
    
    private function saveUploadedFile($tmpName, $originalName, $size, $type)
    {
        $filename = time() . '_' . basename($originalName);
        $filepath = $this->uploadDir . '/' . $filename;
        
        if (move_uploaded_file($tmpName, $filepath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'original_name' => $originalName,
                'filepath' => $filepath,
                'size' => $size,
                'type' => $type,
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
    
    public function listUploads()
    {
        $files = [];
        $dir = opendir($this->uploadDir);
        
        if ($dir === false) {
            return $files; // Return empty array if directory cannot be opened
        }
        
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || substr($file, -5) === '.meta') {
                continue;
            }
            
            $filepath = $this->uploadDir . '/' . $file;
            if (is_file($filepath)) {
                $files[] = [
                    'filename' => $file,
                    'size' => filesize($filepath),
                    'uploaded' => filemtime($filepath),
                ];
            }
        }
        
        closedir($dir);
        return $files;
    }
    
    /**
     * Clean up uploaded files older than specified hours
     * @param int $hours Number of hours (default: 5)
     * @return array ['deleted_count' => int, 'deleted_files' => array]
     */
    public function cleanupOldUploads($hours = 5)
    {
        $cutoffTime = time() - ($hours * 3600);
        $deletedCount = 0;
        $deletedFiles = [];
        
        $dir = opendir($this->uploadDir);
        
        if ($dir === false) {
            return ['deleted_count' => 0, 'deleted_files' => []];
        }
        
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filepath = $this->uploadDir . '/' . $file;
            if (is_file($filepath)) {
                $modifiedTime = filemtime($filepath);
                
                // Delete files older than cutoff time (including .meta files)
                if ($modifiedTime < $cutoffTime) {
                    if (unlink($filepath)) {
                        $deletedCount++;
                        $deletedFiles[] = $file;
                    }
                }
            }
        }
        
        closedir($dir);
        
        return [
            'deleted_count' => $deletedCount,
            'deleted_files' => $deletedFiles,
        ];
    }
}
