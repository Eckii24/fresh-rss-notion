<?php

class FreshExtension_Notion_Controller extends Minz_ActionController
{
    private const NOTION_API_BASE = 'https://api.notion.com/v1';
    private const NOTION_API_VERSION = '2022-06-28';
    private const YOUTUBE_API_BASE = 'https://www.googleapis.com/youtube/v3';
    
    public function addToNotionAction()
    {
        $this->view->_layout(false);
        header('Content-Type: application/json');

        // Get configuration
        $api_key = FreshRSS_Context::$user_conf->notion_api_key ?? '';
        $database_id = FreshRSS_Context::$user_conf->notion_database_id ?? '';
        $youtube_api_key = FreshRSS_Context::$user_conf->youtube_api_key ?? '';
        
        // Property mappings
        $url_property = FreshRSS_Context::$user_conf->notion_url_property ?? 'Url';
        $name_property = FreshRSS_Context::$user_conf->notion_name_property ?? 'Name';
        $author_property = FreshRSS_Context::$user_conf->notion_author_property ?? 'Author';
        $date_property = FreshRSS_Context::$user_conf->notion_date_property ?? 'Date';
        $fixed_properties = FreshRSS_Context::$user_conf->notion_fixed_properties ?? '';

        // Validate configuration
        if (empty($api_key) || empty($database_id)) {
            echo json_encode([
                'success' => false,
                'error' => 'Missing Notion API configuration. Please configure the extension first.'
            ]);
            return;
        }

        // Get entry
        $entry_id = Minz_Request::param('id');
        $entry_dao = FreshRSS_Factory::createEntryDao();
        $entry = $entry_dao->searchById($entry_id);

        if ($entry === null) {
            echo json_encode(['success' => false, 'error' => 'Article not found']);
            return;
        }

        $video_url = $entry->link();
        
        // Extract YouTube video ID
        $video_id = $this->extractYouTubeVideoId($video_url);
        if (!$video_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid YouTube video URL']);
            return;
        }

        try {
            // Check for duplicates first
            $duplicate_check = $this->checkForDuplicate($api_key, $database_id, $video_url, $url_property);
            if ($duplicate_check['exists']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'A page with this URL already exists in the database'
                ]);
                return;
            }

            // Get YouTube video metadata
            $video_data = $this->getYouTubeVideoData($video_id, $youtube_api_key, $entry);
            
            // Create Notion page
            $notion_response = $this->createNotionPage(
                $api_key, 
                $database_id, 
                $video_data, 
                $url_property, 
                $name_property, 
                $author_property, 
                $date_property,
                $fixed_properties
            );

            // Mark entry as read
            $entry_dao->markRead($entry_id, true);

            echo json_encode([
                'success' => true,
                'message' => 'Successfully added to Notion database',
                'notion_page_id' => $notion_response['id'] ?? null
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function extractYouTubeVideoId($url)
    {
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    private function checkForDuplicate($api_key, $database_id, $url, $url_property)
    {
        $query_url = self::NOTION_API_BASE . "/databases/{$database_id}/query";
        
        $data = [
            'filter' => [
                'property' => $url_property,
                'url' => [
                    'equals' => $url
                ]
            ]
        ];

        $response = $this->callNotionAPI($query_url, $data, $api_key);
        
        return [
            'exists' => !empty($response['results']),
            'count' => count($response['results'] ?? [])
        ];
    }

    private function getYouTubeVideoData($video_id, $youtube_api_key, $entry)
    {
        $video_data = [
            'url' => "https://www.youtube.com/watch?v={$video_id}",
            'title' => $entry->title(),
            'author' => '',
            'upload_date' => $entry->date(true),
        ];

        // Try to get metadata from YouTube API if available
        if (!empty($youtube_api_key)) {
            try {
                $api_data = $this->getYouTubeAPIData($video_id, $youtube_api_key);
                if ($api_data) {
                    $video_data['title'] = $api_data['title'];
                    $video_data['author'] = $api_data['channel_title'];
                    $video_data['upload_date'] = $api_data['upload_date'];
                }
            } catch (Exception $e) {
                // Fall back to RSS data if API fails
            }
        }

        // If no API key, try to extract from HTML
        if (empty($video_data['author'])) {
            $html_data = $this->getYouTubeHTMLData($video_id);
            if ($html_data) {
                if (empty($video_data['title']) || $video_data['title'] === $entry->title()) {
                    $video_data['title'] = $html_data['title'];
                }
                $video_data['author'] = $html_data['channel'];
            }
        }

        return $video_data;
    }

    private function getYouTubeAPIData($video_id, $api_key)
    {
        $url = self::YOUTUBE_API_BASE . "/videos?part=snippet&id={$video_id}&key={$api_key}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("YouTube API Error: HTTP {$http_code}");
        }

        $data = json_decode($response, true);
        
        if (empty($data['items'])) {
            return null;
        }

        $snippet = $data['items'][0]['snippet'];
        
        return [
            'title' => $snippet['title'],
            'channel_title' => $snippet['channelTitle'],
            'upload_date' => strtotime($snippet['publishedAt'])
        ];
    }

    private function getYouTubeHTMLData($video_id)
    {
        try {
            $url = "https://www.youtube.com/watch?v={$video_id}";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            
            $html = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 && $html) {
                $title = '';
                $channel = '';
                
                // Extract title
                if (preg_match('/<title>([^<]*)<\/title>/i', $html, $matches)) {
                    $title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                    $title = str_replace(' - YouTube', '', $title);
                }
                
                // Extract channel name
                if (preg_match('/"ownerChannelName":"([^"]*)"/', $html, $matches)) {
                    $channel = $matches[1];
                } elseif (preg_match('/"author":"([^"]*)"/', $html, $matches)) {
                    $channel = $matches[1];
                }
                
                return [
                    'title' => $title,
                    'channel' => $channel
                ];
            }
        } catch (Exception $e) {
            // If we can't get video info, return null
        }
        
        return null;
    }

    private function createNotionPage($api_key, $database_id, $video_data, $url_property, $name_property, $author_property, $date_property, $fixed_properties)
    {
        $url = self::NOTION_API_BASE . "/pages";
        
        $properties = [];
        
        // Get actual property types from Notion database
        $database_schema = $this->getNotionDatabaseSchema($api_key, $database_id);
        
        // Required properties
        $properties[$url_property] = [
            'url' => $video_data['url']
        ];
        
        $properties[$name_property] = [
            'title' => [
                [
                    'text' => [
                        'content' => $video_data['title']
                    ]
                ]
            ]
        ];
        
        if (!empty($video_data['author'])) {
            $author_type = $database_schema[$author_property] ?? 'multi_select';
            $properties[$author_property] = $this->formatPropertyValue($video_data['author'], $author_type);
        }
        
        if (!empty($video_data['upload_date'])) {
            $properties[$date_property] = [
                'date' => [
                    'start' => date('Y-m-d', $video_data['upload_date'])
                ]
            ];
        }
        
        // Parse and add fixed properties using actual property types from database
        if (!empty($fixed_properties)) {
            $fixed_props = $this->parseFixedProperties($fixed_properties);
            foreach ($fixed_props as $prop_name => $prop_value) {
                $prop_type = $database_schema[$prop_name] ?? 'rich_text';
                $properties[$prop_name] = $this->formatPropertyValue($prop_value, $prop_type);
            }
        }

        $data = [
            'parent' => [
                'database_id' => $database_id
            ],
            'properties' => $properties,
            'children' => [
                [
                    'object' => 'block',
                    'type' => 'video',
                    'video' => [
                        'type' => 'external',
                        'external' => [
                            'url' => $video_data['url']
                        ]
                    ]
                ]
            ]
        ];

        return $this->callNotionAPI($url, $data, $api_key);
    }


    private function parseFixedProperties($fixed_properties_string)
    {
        $properties = [];
        
        // Try to parse as JSON first: {"Type": "Video", "Platform": "YouTube"}
        try {
            $parsed = json_decode($fixed_properties_string, true);
            if (is_array($parsed)) {
                return $parsed;
            }
        } catch (Exception $e) {
            // Continue to simple format parsing
        }
        
        // Parse simple format: Type=Video,Platform=YouTube,Status=To Read
        $pairs = explode(',', $fixed_properties_string);
        foreach ($pairs as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (count($parts) === 2) {
                $properties[trim($parts[0])] = trim($parts[1]);
            }
        }
        
        return $properties;
    }

    private function formatPropertyValue($value, $type = 'rich_text')
    {
        switch ($type) {
            case 'title':
                return [
                    'title' => [
                        [
                            'text' => [
                                'content' => (string) $value
                            ]
                        ]
                    ]
                ];
            
            case 'rich_text':
                return [
                    'rich_text' => [
                        [
                            'text' => [
                                'content' => (string) $value
                            ]
                        ]
                    ]
                ];
            
            case 'select':
                return [
                    'select' => [
                        'name' => (string) $value
                    ]
                ];
            
            case 'multi_select':
                // Handle both single values and comma-separated values
                $values = is_array($value) ? $value : explode(',', $value);
                $options = [];
                foreach ($values as $val) {
                    $val = trim($val);
                    if (!empty($val)) {
                        $options[] = ['name' => $val];
                    }
                }
                return [
                    'multi_select' => $options
                ];
            
            case 'number':
                return [
                    'number' => is_numeric($value) ? (float) $value : null
                ];
            
            case 'checkbox':
                return [
                    'checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                ];
            
            case 'url':
                return [
                    'url' => (string) $value
                ];
            
            case 'email':
                return [
                    'email' => (string) $value
                ];
            
            case 'phone_number':
                return [
                    'phone_number' => (string) $value
                ];
            
            case 'date':
                // Try to parse the date
                $timestamp = is_numeric($value) ? $value : strtotime($value);
                if ($timestamp !== false) {
                    return [
                        'date' => [
                            'start' => date('Y-m-d', $timestamp)
                        ]
                    ];
                }
                return null;
            
            default:
                // Default to rich_text for unknown types
                return [
                    'rich_text' => [
                        [
                            'text' => [
                                'content' => (string) $value
                            ]
                        ]
                    ]
                ];
        }
    }

    private function getNotionDatabaseSchema($api_key, $database_id)
    {
        $url = self::NOTION_API_BASE . "/databases/{$database_id}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Notion-Version: ' . self::NOTION_API_VERSION,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            return [];
        }

        $result = json_decode($response, true);
        $properties = [];
        
        if (isset($result['properties'])) {
            foreach ($result['properties'] as $name => $property) {
                $properties[$name] = $property['type'];
            }
        }
        
        return $properties;
    }

    private function callNotionAPI($url, $data, $api_key)
    {
        $json_data = json_encode($data);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json',
                'Notion-Version: ' . self::NOTION_API_VERSION,
                'Content-Length: ' . strlen($json_data)
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception('CURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        $result = json_decode($response, true);
        
        if ($http_code !== 200 && $http_code !== 201) {
            $error_message = $result['message'] ?? "HTTP {$http_code}";
            throw new Exception("Notion API Error: {$error_message}");
        }

        return $result;
    }
}