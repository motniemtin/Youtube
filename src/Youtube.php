<?php
namespace Motniemtin\Youtube;

use Exception;
use Carbon\Carbon;
use Google_Client;
use Google_Service_YouTube;
use Illuminate\Support\Facades\DB;

class Youtube
{
    /**
     * Application Container
     *
     * @var Application
     */
    private $app;

    /**
     * Google Client
     *
     * @var \Google_Client
     */
    protected $client;

    /**
     * Google YouTube Service
     *
     * @var \Google_Service_YouTube
     */
    protected $youtube;

    /**
     * Video ID
     *
     * @var string
     */
    private $videoId;

    /**
     * Video Snippet
     *
     * @var array
     */
    private $snippet;

    /**
     * Thumbnail URL
     *
     * @var string
     */
    private $thumbnailUrl;

    /**
     * Constructor
     *
     * @param \Google_Client $client
     */
    protected $youtube_key; // from the config file    
    /**
     * @var array
     */
    public $APIs = [
        'categories.list' => 'https://www.googleapis.com/youtube/v3/videoCategories',
        'videos.list' => 'https://www.googleapis.com/youtube/v3/videos',
        'search.list' => 'https://www.googleapis.com/youtube/v3/search',
        'channels.list' => 'https://www.googleapis.com/youtube/v3/channels',
        'playlists.list' => 'https://www.googleapis.com/youtube/v3/playlists',
        'playlistItems.list' => 'https://www.googleapis.com/youtube/v3/playlistItems',
        'activities' => 'https://www.googleapis.com/youtube/v3/activities',
        'commentThreads.list' => 'https://www.googleapis.com/youtube/v3/commentThreads',
    ];
    /**
     * @var array
     */
    public $page_info = [];
    /**
     * Constructor
     * $youtube = new Youtube(['key' => 'KEY HERE'])
     *
     * @param string $key
     * @throws \Exception
     */   
    private $email;
    private $config;
    public function __construct($app, Google_Client $client)
    {
        $this->app = $app;
        $this->client=$client;
        // $this->client = $this->setup($client);

        // $this->youtube = new \Google_Service_YouTube($this->client);

        // if ($accessToken = $this->getLatestAccessTokenFromDB()) {
        //     $this->client->setAccessToken($accessToken);
        // }
    }
    public function loadUser($email){
        $this->email=$email;
        $this->config=DB::table('youtube')->where('email', $this->email)->first();
        $this->client = $this->setup($this->client);
        $this->youtube = new \Google_Service_YouTube($this->client);
        if($this->config->access_token!=null){
          $tmp=json_decode($this->config->access_token);
          if(isset($tmp->refresh_token))
            $this->client->setAccessToken($this->config->access_token);
        }
        $this->youtube_key=$this->config->key;
    }
    public function loadUserId($id){
        $this->config=DB::table('youtube')->where('id', $id)->first();
        $this->email=$this->config->email;
        $this->client = $this->setup($this->client);
        $this->youtube = new \Google_Service_YouTube($this->client);
        if($this->config->access_token!=null){
          $tmp=json_decode($this->config->access_token);
          if(isset($tmp->refresh_token))
            $this->client->setAccessToken($this->config->access_token);
        }
        $this->youtube_key=$this->config->key;
    }  
    /**
     * Upload the video to YouTube
     *
     * @param  string $path
     * @param  array $data
     * @param  string $privacyStatus
     * @return self
     * @throws Exception
     */
    public function upload($path, array $data = [], $privacyStatus = 'public')
    {
        if(!file_exists($path)) {
            throw new Exception('Video file does not exist at path: "'. $path .'". Provide a full path to the file before attempting to upload.');
        }

        $this->handleAccessToken();

        try {
            $video = $this->getVideo($data, $privacyStatus);

            // Set the Chunk Size
            $chunkSize = 1 * 1024 * 1024;

            // Set the defer to true
            $this->client->setDefer(true);

            // Build the request
            $insert = $this->youtube->videos->insert('status,snippet', $video);

            // Upload
            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $insert,
                'video/*',
                null,
                true,
                $chunkSize
            );

            // Set the Filesize
            $media->setFileSize(filesize($path));

            // Read the file and upload in chunks
            $status = false;
            $handle = fopen($path, "rb");

            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                $status = $media->nextChunk($chunk);
            }

            fclose($handle);

            $this->client->setDefer(false);

            // Set ID of the Uploaded Video
            $this->videoId = $status['id'];

            // Set the Snippet from Uploaded Video
            $this->snippet = $status['snippet'];

        }  catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $this;
    }

    /**
     * Update the video on YouTube
     *
     * @param  string $id
     * @param  array $data
     * @param  string $privacyStatus
     * @return self
     * @throws Exception
     */
    public function update($id, array $data = [], $privacyStatus = 'public')
    {
        $this->handleAccessToken();

        if (!$this->exists($id)) {
            throw new Exception('A video matching id "'. $id .'" could not be found.');
        }

        try {
            $video = $this->getVideo($data, $privacyStatus, $id);

            $status = $this->youtube->videos->update('status,snippet', $video);

            // Set ID of the Updated Video
            $this->videoId = $status['id'];

            // Set the Snippet from Updated Video
            $this->snippet = $status['snippet'];
        }  catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $this;
    }

    /**
     * Set a Custom Thumbnail for the Upload
     *
     * @param  string $imagePath
     * @return self
     * @throws Exception
     */
    public function withThumbnail($imagePath)
    {
        try {
            $videoId = $this->getVideoId();

            $chunkSizeBytes = 1 * 1024 * 1024;

            $this->client->setDefer(true);

            $setRequest = $this->youtube->thumbnails->set($videoId);

            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $setRequest,
                'image/png',
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize(filesize($imagePath));

            $status = false;
            $handle = fopen($imagePath, "rb");

            while (!$status && !feof($handle)) {
                $chunk  = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }

            fclose($handle);

            $this->client->setDefer(false);
            $this->thumbnailUrl = $status['items'][0]['default']['url'];

        } catch (\Google_Service_Exception $e) {
            throw new Exception($e->getMessage());
        } catch (\Google_Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $this;
    }

    /**
     * Delete a YouTube video by it's ID.
     *
     * @param  int $id
     * @return bool
     * @throws Exception
     */
    public function delete($id)
    {
        $this->handleAccessToken();

        if (!$this->exists($id)) {
            throw new Exception('A video matching id "'. $id .'" could not be found.');
        }

        return $this->youtube->videos->delete($id);
    }

    /**
     * @param $data
     * @param $privacyStatus
     * @param null $id
     * @return \Google_Service_YouTube_Video
     */
    private function getVideo($data, $privacyStatus, $id = null)
    {
        // Setup the Snippet
        $snippet = new \Google_Service_YouTube_VideoSnippet();

        if (array_key_exists('title', $data))       $snippet->setTitle($data['title']);
        if (array_key_exists('description', $data)) $snippet->setDescription($data['description']);
        if (array_key_exists('tags', $data))        $snippet->setTags($data['tags']);
        if (array_key_exists('category_id', $data)) $snippet->setCategoryId($data['category_id']);

        // Set the Privacy Status
        $status = new \Google_Service_YouTube_VideoStatus();
        $status->privacyStatus = $privacyStatus;

        // Set the Snippet & Status
        $video = new \Google_Service_YouTube_Video();
        if ($id)
        {
            $video->setId($id);
        }

        $video->setSnippet($snippet);
        $video->setStatus($status);

        return $video;
    }

    /**
     * Check if a YouTube video exists by it's ID.
     *
     * @param  int  $id
     *
     * @return bool
     */
    public function exists($id)
    {
        $this->handleAccessToken();

        $response = $this->youtube->videos->listVideos('status', ['id' => $id]);

        if (empty($response->items)) return false;

        return true;
    }

    /**
     * Return the Video ID
     *
     * @return string
     */
    public function getVideoId()
    {
        return $this->videoId;
    }

    /**
     * Return the snippet of the uploaded Video
     *
     * @return array
     */
    public function getSnippet()
    {
        return $this->snippet;
    }

    /**
     * Return the URL for the Custom Thumbnail
     *
     * @return string
     */
    public function getThumbnailUrl()
    {
        return $this->thumbnailUrl;
    }

    /**
     * Setup the Google Client
     *
     * @param Google_Client $client
     * @return Google_Client $client
     * @throws Exception
     */
    private function setup(Google_Client $client)
    {
        $client->setClientId($this->config->client_id);
        $client->setClientSecret($this->config->client_secret);
        $client->setScopes($this->app->config->get('youtube.scopes'));
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $client->setRedirectUri(url('/youtube/callback/'.$this->config->id));
        return $this->client = $client;
    }

    /**
     * Saves the access token to the database.
     *
     * @param  string  $accessToken
     */
    public function saveAccessTokenToDB($accessToken)
    {
        return DB::table('youtube')->where('email',$this->email)->update([
            'access_token' => json_encode($accessToken),
            'updated_at'   => Carbon::now()->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Handle the Access Token
     *
     * @return void
     */
    public function handleAccessToken()
    {
        if (is_null($accessToken = $this->client->getAccessToken())) {
            throw new \Exception('An access token is required.');
        }

        if($this->client->isAccessTokenExpired())
        {
            // If we have a "refresh_token"
            if (array_key_exists('refresh_token', $accessToken))
            {
                // Refresh the access token
                $this->client->refreshToken($accessToken['refresh_token']);

                // Save the access token
                $this->saveAccessTokenToDB($this->client->getAccessToken());
            }
        }
    }

    /**
     * Pass method calls to the Google Client.
     *
     * @param  string  $method
     * @param  array   $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->client, $method], $args);
    }
  /**
     * @param $key
     * @return Youtube
     */
    public function setApiKey($key)
    {
        $this->youtube_key = $key;
        return $this;
    }
    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->youtube_key;
    }
    /**
     * @param $regionCode
     * @return \StdClass
     * @throws \Exception
     */
    public function getCategories($regionCode = 'US', $part = ['snippet'])
    {
        $API_URL = $this->getApi('categories.list');
        $params = [
            'key' => $this->youtube_key,
            'part' => implode(', ', $part),
            'regionCode' => $regionCode
        ];
        $apiData = $this->api_get($API_URL, $params);
        return $this->decodeMultiple($apiData);
    }
    /**
     * @param string $videoId       Instructs the API to return comment threads containing comments about the specified channel. (The response will not include comments left on videos that the channel uploaded.)
     * @param integer $maxResults   Specifies the maximum number of items that should be returned in the result set. Acceptable values are 1 to 100, inclusive. The default value is 20.
     * @param string $order         Specifies the order in which the API response should list comment threads. Valid values are: time, relevance.
     * @param array $part           Specifies a list of one or more commentThread resource properties that the API response will include.
     * @param bool $pageInfo        Add page info to returned array.
     * @return array
     * @throws \Exception
     */
    public function getCommentThreadsByVideoId($videoId = null, $maxResults = 20, $order = null, $part = ['id', 'replies', 'snippet'], $pageInfo = false) {
        return $this->getCommentThreads(null, null, $videoId, $maxResults, $order, $part, $pageInfo);
    }
    /**
     * @param string $channelId     Instructs the API to return comment threads containing comments about the specified channel. (The response will not include comments left on videos that the channel uploaded.)
     * @param string $id            Specifies a comma-separated list of comment thread IDs for the resources that should be retrieved.
     * @param string $videoId       Instructs the API to return comment threads containing comments about the specified channel. (The response will not include comments left on videos that the channel uploaded.)
     * @param integer $maxResults   Specifies the maximum number of items that should be returned in the result set. Acceptable values are 1 to 100, inclusive. The default value is 20.
     * @param string $order         Specifies the order in which the API response should list comment threads. Valid values are: time, relevance.
     * @param array $part           Specifies a list of one or more commentThread resource properties that the API response will include.
     * @param bool $pageInfo        Add page info to returned array.
     * @return array
     * @throws \Exception
     */
    public function getCommentThreads($channelId = null, $id = null, $videoId = null, $maxResults = 20, $order = null, $part = ['id', 'replies', 'snippet'], $pageInfo = false)
    {
        $API_URL = $this->getApi('commentThreads.list');
        $params = array_filter([
            'channelId' => $channelId,
            'id' => $id,
            'videoId' => $videoId,
            'maxResults' => $maxResults,
            'part' => implode(', ', $part),
            'order' => $order,
        ]);
        $apiData = $this->api_get($API_URL, $params);
        if ($pageInfo) {
            return [
                'results' => $this->decodeList($apiData),
                'info' => $this->page_info,
            ];
        } else {
            return $this->decodeList($apiData);
        }
    }
    /**
     * @param $vId
     * @param array $part
     * @return \StdClass
     * @throws \Exception
     */
    public function getVideoInfo($vId, $part = ['id', 'snippet', 'contentDetails', 'player', 'statistics', 'status'])
    {
        $API_URL = $this->getApi('videos.list');
        $params = [
            'id' => is_array($vId) ? implode(',', $vId) : $vId,
            'key' => $this->youtube_key,
            'part' => implode(', ', $part),
        ];
        $apiData = $this->api_get($API_URL, $params);
        if (is_array($vId)) {
            return $this->decodeMultiple($apiData);
        }
        return $this->decodeSingle($apiData);
    }
    /**
     * Gets popular videos for a specific region (ISO 3166-1 alpha-2)
     *
     * @param $regionCode
     * @param integer $maxResults
     * @param array $part
     * @return array
     */
    public function getPopularVideos($regionCode, $maxResults = 10, $part = ['id', 'snippet', 'contentDetails', 'player', 'statistics', 'status'])
    {
        $API_URL = $this->getApi('videos.list');
        $params = [
            'chart' => 'mostPopular',
            'part' => implode(', ', $part),
            'regionCode' => $regionCode,
            'maxResults' => $maxResults,
        ];
        $apiData = $this->api_get($API_URL, $params);
        return $this->decodeList($apiData);
    }
    /**
     * Simple search interface, this search all stuffs
     * and order by relevance
     *
     * @param $q
     * @param integer $maxResults
     * @param array $part
     * @return array
     */
    public function search($q, $maxResults = 10, $part = ['id', 'snippet'])
    {
        $params = [
            'q' => $q,
            'part' => implode(', ', $part),
            'maxResults' => $maxResults,
        ];
        return $this->searchAdvanced($params);
    }
    /**
     * Search only videos
     *
     * @param  string $q Query
     * @param  integer $maxResults number of results to return
     * @param  string $order Order by
     * @param  array $part
     * @return \StdClass  API results
     */
    public function searchVideos($q, $maxResults = 10, $order = null, $part = ['id'])
    {
        $params = [
            'q' => $q,
            'type' => 'video',
            'part' => implode(', ', $part),
            'maxResults' => $maxResults,
        ];
        if (!empty($order)) {
            $params['order'] = $order;
        }
        return $this->searchAdvanced($params);
    }
    /**
     * Search only videos in the channel
     *
     * @param  string $q
     * @param  string $channelId
     * @param  integer $maxResults
     * @param  string $order
     * @param  array $part
     * @param  $pageInfo
     * @return array
     */
    public function searchChannelVideos($q, $channelId, $maxResults = 10, $order = null, $part = ['id', 'snippet'], $pageInfo = false)
    {
        $params = [
            'q' => $q,
            'type' => 'video',
            'channelId' => $channelId,
            'part' => implode(', ', $part),
            'maxResults' => $maxResults,
        ];
        if (!empty($order)) {
            $params['order'] = $order;
        }
        return $this->searchAdvanced($params, $pageInfo);
    }
    /**
     * List videos in the channel
     *
     * @param  string $channelId
     * @param  integer $maxResults
     * @param  string $order
     * @param  array $part
     * @param  $pageInfo
     * @return array
     */
    public function listChannelVideos($channelId, $maxResults = 10, $order = null, $part = ['id', 'snippet'], $pageInfo = false)
    {
        $params = [
            'type' => 'video',
            'channelId' => $channelId,
            'part' => implode(', ', $part),
            'maxResults' => $maxResults,
        ];
        if (!empty($order)) {
            $params['order'] = $order;
        }
        return $this->searchAdvanced($params, $pageInfo);
    }
    /**
     * Generic Search interface, use any parameters specified in
     * the API reference
     *
     * @param $params
     * @param $pageInfo
     * @return array
     * @throws \Exception
     */
    public function searchAdvanced($params, $pageInfo = false)
    {
        $API_URL = $this->getApi('search.list');
        if (empty($params) || (!isset($params['q']) && !isset($params['channelId']) && !isset($params['videoCategoryId']))) {
            throw new \InvalidArgumentException('at least the Search query or Channel ID or videoCategoryId must be supplied');
        }
        $apiData = $this->api_get($API_URL, $params);
        if ($pageInfo) {
            return [
                'results' => $this->decodeList($apiData),
                'info' => $this->page_info,
            ];
        } else {
            return $this->decodeList($apiData);
        }
    }
    /**
     * Generic Search Paginator, use any parameters specified in
     * the API reference and pass through nextPageToken as $token if set.
     *
     * @param $params
     * @param $token
     * @return array
     */
    public function paginateResults($params, $token = null)
    {
        if (!is_null($token)) {
            $params['pageToken'] = $token;
        }
        if (!empty($params)) {
            return $this->searchAdvanced($params, true);
        }
    }
    /**
     * @param $username
     * @param $optionalParams
     * @param array $part
     * @return \StdClass
     * @throws \Exception
     */
    public function getChannelByName($username, $optionalParams = false, $part = ['id', 'snippet', 'contentDetails', 'statistics', 'invideoPromotion'])
    {
        $API_URL = $this->getApi('channels.list');
        $params = [
            'forUsername' => $username,
            'part' => implode(', ', $part),
        ];
        if ($optionalParams) {
            $params = array_merge($params, $optionalParams);
        }
        $apiData = $this->api_get($API_URL, $params);
        return $this->decodeSingle($apiData);
    }
    /**
     * @param $id
     * @param $optionalParams
     * @param array $part
     * @return \StdClass
     * @throws \Exception
     */
    public function getChannelById($id, $optionalParams = false, $part = ['id', 'snippet', 'contentDetails', 'statistics', 'invideoPromotion'])
    {
        $API_URL = $this->getApi('channels.list');
        $params = [
            'id' => is_array($id) ? implode(',', $id) : $id,
            'part' => implode(', ', $part),
        ];
        if ($optionalParams) {
            $params = array_merge($params, $optionalParams);
        }
        $apiData = $this->api_get($API_URL, $params);
        if (is_array($id)) {
            return $this->decodeMultiple($apiData);
        }
        return $this->decodeSingle($apiData);
    }
    /**
     * @param string $channelId
     * @param array $optionalParams
     * @param array $part
     * @return array
     * @throws \Exception
     */
    public function getPlaylistsByChannelId($channelId, $optionalParams = [], $part = ['id', 'snippet', 'status'])
    {
        $API_URL = $this->getApi('playlists.list');
        $params = [
            'channelId' => $channelId,
            'part' => implode(', ', $part)
        ];
        if ($optionalParams) {
            $params = array_merge($params, $optionalParams);
        }
        $apiData = $this->api_get($API_URL, $params);
        $result = ['results' => $this->decodeList($apiData)];
        $result['info']['totalResults'] =  (isset($this->page_info['totalResults']) ? $this->page_info['totalResults'] : 0);
        $result['info']['nextPageToken'] = (isset($this->page_info['nextPageToken']) ? $this->page_info['nextPageToken'] : false);
        $result['info']['prevPageToken'] = (isset($this->page_info['prevPageToken']) ? $this->page_info['prevPageToken'] : false);
        return $result;
    }
    /**
     * @param $id
     * @param $part
     * @return \StdClass
     * @throws \Exception
     */
    public function getPlaylistById($id, $part = ['id', 'snippet', 'status'])
    {
        $API_URL = $this->getApi('playlists.list');
        $params = [
            'id' => is_array($id)? implode(',', $id) : $id,
            'part' => implode(', ', $part),
        ];
        $apiData = $this->api_get($API_URL, $params);
        if (is_array($id)) {
            return $this->decodeMultiple($apiData);
        }
        return $this->decodeSingle($apiData);
    }
    /**
     * @param string $playlistId
     * @param string $pageToken
     * @param integer $maxResults
     * @param array $part
     * @return array
     * @throws \Exception
     */
    public function getPlaylistItemsByPlaylistId($playlistId, $pageToken = '', $maxResults = 50, $part = ['id', 'snippet', 'contentDetails', 'status'])
    {
        $API_URL = $this->getApi('playlistItems.list');
        $params = [
            'playlistId' => $playlistId,
            'part' => implode(', ', $part),
            'maxResults' => $maxResults,
        ];
        // Pass page token if it is given, an empty string won't change the api response
        $params['pageToken'] = $pageToken;
        $apiData = $this->api_get($API_URL, $params);
        $result = ['results' => $this->decodeList($apiData)];
        $result['info']['totalResults'] =  (isset($this->page_info['totalResults']) ? $this->page_info['totalResults'] : 0);
        $result['info']['nextPageToken'] = (isset($this->page_info['nextPageToken']) ? $this->page_info['nextPageToken'] : false);
        $result['info']['prevPageToken'] = (isset($this->page_info['prevPageToken']) ? $this->page_info['prevPageToken'] : false);
        return $result;
    }
    /**
     * @param $channelId
     * @param array $part
     * @param integer $maxResults
     * @param $pageInfo
     * @param $pageToken
     * @return array
     * @throws \Exception
     */
    public function getActivitiesByChannelId($channelId, $part = ['id', 'snippet', 'contentDetails'], $maxResults = 5, $pageInfo = false, $pageToken = '')
    {
        if (empty($channelId)) {
            throw new \InvalidArgumentException('ChannelId must be supplied');
        }
        $API_URL = $this->getApi('activities');
        $params = [
            'channelId' => $channelId,
            'part' => implode(', ', $part),
            'maxResults' => $maxResults,
            'pageToken' => $pageToken,
        ];
        $apiData = $this->api_get($API_URL, $params);
        if ($pageInfo) {
            return [
                'results' => $this->decodeList($apiData),
                'info' => $this->page_info,
            ];
        } else {
            return $this->decodeList($apiData);
        }
    }
    /**
     * @param  string $videoId
     * @param  integer $maxResults
     * @param  array $part
     * @return array
     * @throws \Exception
     */
    public function getRelatedVideos($videoId, $maxResults = 5, $part = ['id', 'snippet'])
    {
        if (empty($videoId)) {
            throw new \InvalidArgumentException('A video id must be supplied');
        }
        $API_URL = $this->getApi('search.list');
        $params = [
            'type' => 'video',
            'relatedToVideoId' => $videoId,
            'part' => implode(', ', $part),
            'maxResults' => $maxResults,
        ];
        $apiData = $this->api_get($API_URL, $params);
        return $this->decodeList($apiData);
    }
    /**
     * Parse a youtube URL to get the youtube Vid.
     * Support both full URL (www.youtube.com) and short URL (youtu.be)
     *
     * @param  string $youtube_url
     * @throws \Exception
     * @return string Video Id
     */
    public static function parseVIdFromURL($youtube_url)
    {
        if (strpos($youtube_url, 'youtube.com')) {
            if (strpos($youtube_url, 'embed')) {
                $path = static::_parse_url_path($youtube_url);
                $vid = substr($path, 7);
                return $vid;
            } else {
                $params = static::_parse_url_query($youtube_url);
                return $params['v'];
            }
        } else if (strpos($youtube_url, 'youtu.be')) {
            $path = static::_parse_url_path($youtube_url);
            $vid = substr($path, 1);
            return $vid;
        } else {
            throw new \Exception('The supplied URL does not look like a Youtube URL');
        }
    }
    /**
     * Get the channel object by supplying the URL of the channel page
     *
     * @param  string $youtube_url
     * @throws \Exception
     * @return object Channel object
     */
    public function getChannelFromURL($youtube_url)
    {
        if (strpos($youtube_url, 'youtube.com') === false) {
            throw new \Exception('The supplied URL does not look like a Youtube URL');
        }
        $path = static::_parse_url_path($youtube_url);
        if (strpos($path, '/channel') === 0) {
            $segments = explode('/', $path);
            $channelId = $segments[count($segments) - 1];
            $channel = $this->getChannelById($channelId);
        } else if (strpos($path, '/user') === 0) {
            $segments = explode('/', $path);
            $username = $segments[count($segments) - 1];
            $channel = $this->getChannelByName($username);
        } else {
            throw new \Exception('The supplied URL does not look like a Youtube Channel URL');
        }
        return $channel;
    }
    /*
     *  Internally used Methods, set visibility to public to enable more flexibility
     */
    /**
     * @param $name
     * @return mixed
     */
    public function getApi($name)
    {
        return $this->APIs[$name];
    }
    /**
     * Decode the response from youtube, extract the single resource object.
     * (Don't use this to decode the response containing list of objects)
     *
     * @param  string $apiData the api response from youtube
     * @throws \Exception
     * @return \StdClass  an Youtube resource object
     */
    public function decodeSingle(&$apiData)
    {
        $resObj = json_decode($apiData);
        if (isset($resObj->error)) {
            $msg = "Error " . $resObj->error->code . " " . $resObj->error->message;
            if (isset($resObj->error->errors[0])) {
                $msg .= " : " . $resObj->error->errors[0]->reason;
            }
            throw new \Exception($msg);
        } else {
            $itemsArray = $resObj->items;
            if (!is_array($itemsArray) || count($itemsArray) == 0) {
                return false;
            } else {
                return $itemsArray[0];
            }
        }
    }
    /**
     * Decode the response from youtube, extract the multiple resource object.
     *
     * @param  string $apiData the api response from youtube
     * @throws \Exception
     * @return \StdClass  an Youtube resource object
     */
    public function decodeMultiple(&$apiData)
    {
        $resObj = json_decode($apiData);
        if (isset($resObj->error)) {
            $msg = "Error " . $resObj->error->code . " " . $resObj->error->message;
            if (isset($resObj->error->errors[0])) {
                $msg .= " : " . $resObj->error->errors[0]->reason;
            }
            throw new \Exception($msg);
        } else {
            $itemsArray = $resObj->items;
            if (!is_array($itemsArray)) {
                return false;
            } else {
                return $itemsArray;
            }
        }
    }
    /**
     * Decode the response from youtube, extract the list of resource objects
     *
     * @param  string $apiData response string from youtube
     * @throws \Exception
     * @return array Array of StdClass objects
     */
    public function decodeList(&$apiData)
    {
        $resObj = json_decode($apiData);
        if (isset($resObj->error)) {
            $msg = "Error " . $resObj->error->code . " " . $resObj->error->message;
            if (isset($resObj->error->errors[0])) {
                $msg .= " : " . $resObj->error->errors[0]->reason;
            }
            throw new \Exception($msg);
        } else {
            $this->page_info = [
                'resultsPerPage' => $resObj->pageInfo->resultsPerPage,
                'totalResults' => $resObj->pageInfo->totalResults,
                'kind' => $resObj->kind,
                'etag' => $resObj->etag,
                'prevPageToken' => null,
                'nextPageToken' => null,
            ];
            if (isset($resObj->prevPageToken)) {
                $this->page_info['prevPageToken'] = $resObj->prevPageToken;
            }
            if (isset($resObj->nextPageToken)) {
                $this->page_info['nextPageToken'] = $resObj->nextPageToken;
            }
            $itemsArray = $resObj->items;
            if (!is_array($itemsArray) || count($itemsArray) == 0) {
                return false;
            } else {
                return $itemsArray;
            }
        }
    }
    /**
     * Using CURL to issue a GET request
     *
     * @param $url
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    public function api_get($url, $params)
    {
        //set the youtube key
        $params['key'] = $this->youtube_key;
        //boilerplates for CURL
        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url . (strpos($url, '?') === false ? '?' : '') . http_build_query($params));
        if (strpos($url, 'https') === false) {
            curl_setopt($tuCurl, CURLOPT_PORT, 80);
        } else {
            curl_setopt($tuCurl, CURLOPT_PORT, 443);
        }
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        $tuData = curl_exec($tuCurl);
        if (curl_errno($tuCurl)) {
            throw new \Exception('Curl Error : ' . curl_error($tuCurl));
        }
        return $tuData;
    }
    /**
     * Parse the input url string and return just the path part
     *
     * @param  string $url the URL
     * @return string      the path string
     */
    public static function _parse_url_path($url)
    {
        $array = parse_url($url);
        return $array['path'];
    }
    /**
     * Parse the input url string and return an array of query params
     *
     * @param  string $url the URL
     * @return array      array of query params
     */
    public static function _parse_url_query($url)
    {
        $array = parse_url($url);
        $query = $array['query'];
        $queryParts = explode('&', $query);
        $params = [];
        foreach ($queryParts as $param) {
            $item = explode('=', $param);
            $params[$item[0]] = empty($item[1]) ? '' : $item[1];
        }
        return $params;
    }
}
