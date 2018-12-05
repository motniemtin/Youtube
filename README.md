This package port from https://github.com/alaouy/Youtube, and https://github.com/JoeDawson/youtube 

# **Installation**

Run in console below command to download package to your project:
`composer require motniemtin/youtube`
# **Configuration**

In /config/app.php add YoutubeServiceProvider:

`Motniemtin\Youtube\YoutubeServiceProvider::class,`

Do not forget to add also Youtube facade there:

`'Youtube' => Motniemtin\Youtube\Facades\Youtube::class,`

Publish config settings:

`$ php artisan vendor:publish --provider="Motniemtin\Youtube\YoutubeServiceProvider"`

Create youtube table

`php artisan migrate`

# **Upload a Video**

```php
$video = Youtube::upload($fullPathToVideo, [
    'title'       => 'My Awesome Video',
    'description' => 'You can also specify your video description here.',
    'tags'	      => ['foo', 'bar', 'baz'],
    'category_id' => 10
]);

return $video->getVideoId();
```
# **Custom Thumbnail**

If you would like to set a custom thumbnail for for upload, you can use the withThumbnail() method via chaining.

```php
$fullpathToImage = storage_path('app/public/thumbnail.jpg');

$video = Youtube::upload($fullPathToVideo, $params)->withThumbnail($fullpathToImage);

return $youtube->getThumbnailUrl();
```

# Updating a Video

```php
$video = Youtube::update($videoId, [
    'title'       => 'My Awesome Video',
    'description' => 'You can also specify your video description here.',
    'tags'	      => ['foo', 'bar', 'baz'],
    'category_id' => 10
], $privacy);

return $video->getVideoId();
```
# Deleting a Video

```php
Youtube::delete($videoId);
```

# Usage Youtube API v3

```php
// use Motniemtin\Youtube\Facades\Youtube;


// Return an STD PHP object
$video = Youtube::getVideoInfo('rie-hPVJ7Sw');

// Get multiple videos info from an array
$videoList = Youtube::getVideoInfo(['rie-hPVJ7Sw','iKHTawgyKWQ']);

// Get multiple videos related to a video
$relatedVideos = Youtube::getRelatedVideos('iKHTawgyKWQ');

// Get comment threads by videoId
$commentThreads = Youtube::getCommentThreadsByVideoId('zwiUB_Lh3iA');

// Get popular videos in a country, return an array of PHP objects
$videoList = Youtube::getPopularVideos('us');

// Search playlists, channels and videos. return an array of PHP objects
$results = Youtube::search('Android');

// Only search videos, return an array of PHP objects
$videoList = Youtube::searchVideos('Android');

// Search only videos in a given channel, return an array of PHP objects
$videoList = Youtube::searchChannelVideos('keyword', 'UCk1SpWNzOs4MYmr0uICEntg', 40);

// List videos in a given channel, return an array of PHP objects
$videoList = Youtube::listChannelVideos('UCk1SpWNzOs4MYmr0uICEntg', 40);

$results = Youtube::searchAdvanced([ /* params */ ]);

// Get channel data by channel name, return an STD PHP object
$channel = Youtube::getChannelByName('xdadevelopers');

// Get channel data by channel ID, return an STD PHP object
$channel = Youtube::getChannelById('UCk1SpWNzOs4MYmr0uICEntg');

// Get playlist by ID, return an STD PHP object
$playlist = Youtube::getPlaylistById('PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs');

// Get playlists by multiple ID's, return an array of STD PHP objects
$playlists = Youtube::getPlaylistById(['PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs', 'PL590L5WQmH8cUsRyHkk1cPGxW0j5kmhm0']);

// Get playlist by channel ID, return an array of PHP objects
$playlists = Youtube::getPlaylistsByChannelId('UCk1SpWNzOs4MYmr0uICEntg');

// Get items in a playlist by playlist ID, return an array of PHP objects
$playlistItems = Youtube::getPlaylistItemsByPlaylistId('PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs');

// Get channel activities by channel ID, return an array of PHP objects
$activities = Youtube::getActivitiesByChannelId('UCk1SpWNzOs4MYmr0uICEntg');

// Retrieve video ID from original YouTube URL
$videoId = Youtube::parseVidFromURL('https://www.youtube.com/watch?v=moSFlvxnbgk');
// result: moSFlvxnbgk
```

**Basic Search Pagination**
```php
// Set default parameters
$params = [
    'q'             => 'Android',
    'type'          => 'video',
    'part'          => 'id, snippet',
    'maxResults'    => 50
];

// Make intial call. with second argument to reveal page info such as page tokens
$search = Youtube::searchAdvanced($params, true);

// Check if we have a pageToken
if (isset($search['info']['nextPageToken'])) {
    $params['pageToken'] = $search['info']['nextPageToken'];
}

// Make another call and repeat
$search = Youtube::searchAdvanced($params, true);

// Add results key with info parameter set
print_r($search['results']);

/* Alternative approach with new built-in paginateResults function */

// Same params as before
$params = [
    'q'             => 'Android',
    'type'          => 'video',
    'part'          => 'id, snippet',
    'maxResults'    => 50
];

// An array to store page tokens so we can go back and forth
$pageTokens = [];

// Make inital search
$search = Youtube::paginateResults($params, null);

// Store token
$pageTokens[] = $search['info']['nextPageToken'];

// Go to next page in result
$search = Youtube::paginateResults($params, $pageTokens[0]);

// Store token
$pageTokens[] = $search['info']['nextPageToken'];

// Go to next page in result
$search = Youtube::paginateResults($params, $pageTokens[1]);

// Store token
$pageTokens[] = $search['info']['nextPageToken'];

// Go back a page
$search = Youtube::paginateResults($params, $pageTokens[0]);

// Add results key with info parameter set
print_r($search['results']);
```