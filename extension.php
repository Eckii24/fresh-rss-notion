<?php

class NotionExtension extends Minz_Extension
{
    protected array $csp_policies = [
        'default-src' => '*',
    ];

    public function init()
    {
        $this->registerHook('entry_before_display', array($this, 'addNotionButton'));
        $this->registerController('Notion');
        Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
        Minz_View::appendScript($this->getFileUrl('script.js', 'js'));
    }

    public function addNotionButton($entry)
    {
        $url = $entry->link();
        
        // Only add button for YouTube videos (not shorts)
        if (!$this->isYouTubeVideo($url)) {
            return $entry;
        }

        $url_notion = Minz_Url::display(array(
            'c' => 'Notion',
            'a' => 'addToNotion',
            'params' => array(
                'id' => $entry->id()
            )
        ));

        $entry->_content(
            '<div class="notion-add-wrap">'
            . '<button data-request="' . $url_notion . '" class="notion-add-btn">Add to Notion</button>'
            . '<div class="notion-add-content"></div>'
            . '</div>'
            . $entry->content()
        );
        return $entry;
    }

    private function isYouTubeVideo($url)
    {
        // Check if it's a YouTube URL
        if (!preg_match('/(?:youtube\.com|youtu\.be)/', $url)) {
            return false;
        }
        
        // Exclude YouTube Shorts
        if (preg_match('/\/shorts\//', $url)) {
            return false;
        }
        
        // Only include /watch URLs (not youtu.be shortened URLs based on requirements)
        // The requirement specifically mentions detection by path start (/shorts vs /watch)
        return preg_match('/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/', $url) > 0;
    }

    public function handleConfigureAction()
    {
        if (Minz_Request::isPost()) {
            FreshRSS_Context::$user_conf->notion_api_key = Minz_Request::param('notion_api_key', '');
            FreshRSS_Context::$user_conf->notion_database_id = Minz_Request::param('notion_database_id', '');
            FreshRSS_Context::$user_conf->youtube_api_key = Minz_Request::param('youtube_api_key', '');
            
            // Property mappings
            FreshRSS_Context::$user_conf->notion_url_property = Minz_Request::param('notion_url_property', 'Url');
            FreshRSS_Context::$user_conf->notion_name_property = Minz_Request::param('notion_name_property', 'Name');
            FreshRSS_Context::$user_conf->notion_author_property = Minz_Request::param('notion_author_property', 'Author');
            FreshRSS_Context::$user_conf->notion_date_property = Minz_Request::param('notion_date_property', 'Date');
            
            // Property type configurations
            FreshRSS_Context::$user_conf->notion_property_configs = Minz_Request::param('notion_property_configs', '{}');
            
            // Additional fixed properties
            FreshRSS_Context::$user_conf->notion_fixed_properties = Minz_Request::param('notion_fixed_properties', '');
            
            FreshRSS_Context::$user_conf->save();
        }
    }
}