<?php

/**
 * Library Requirements
 *
 * 1. Install composer (https://getcomposer.org)
 * 2. On the command line, change to this directory (api-samples/php)
 * 3. Require the google/apiclient library
 *    $ composer require google/apiclient:~2.0
 */
if (!file_exists($file = __DIR__ . '/vendor/autoload.php')) {
  throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ .'"');
}
require_once __DIR__ . '/vendor/autoload.php';
session_start();

/*
 * Set $DEVELOPER_KEY to the "API key" value from the "Access" tab of the
 * Google Developers Console: https://console.developers.google.com/
 * Please ensure that you have enabled the YouTube Data API for your project.
 */
define('CREDENTIALS_PATH', '/var/www/ssl/php-yt-oauth2.json');

function getClient() {
  $client = new Google_Client();
  $client->setApplicationName('API Samples');
  $client->setScopes('https://www.googleapis.com/auth/youtube.force-ssl');
  // Set to name/location of your client_secrets.json file.
  $client->setAuthConfig('client_secrets.json');
  $client->setAccessType('offline');

  // Load previously authorized credentials from a file.
  $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
  if (file_exists($credentialsPath)) {
    $accessToken = json_decode(file_get_contents($credentialsPath), true);
  } else {
    // Request authorization from the user.
    $authUrl = $client->createAuthUrl();
    printf("Open the following link in your browser:\n%s\n", $authUrl);
    print 'Enter verification code: ';
    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for an access token.
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

    // Store the credentials to disk.
    if(!file_exists(dirname($credentialsPath))) {
      mkdir(dirname($credentialsPath), 0700, true);
    }
    file_put_contents($credentialsPath, json_encode($accessToken));
    printf("Credentials saved to %s\n", $credentialsPath);
  }
  $client->setAccessToken($accessToken);

  // Refresh the token if it's expired.
  if ($client->isAccessTokenExpired()) {
    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
  }
  return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
  $homeDirectory = getenv('HOME');
  if (empty($homeDirectory)) {
    $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
  }
  return str_replace('~', realpath($homeDirectory), $path);
}

// Define an object that will be used to make all API requests.
$client = getClient();
$service = new Google_Service_YouTube($client);

if (isset($_GET['code'])) {
  if (strval($_SESSION['state']) !== strval($_GET['state'])) {
    die('The session state did not match.');
  }

  $client->authenticate($_GET['code']);
  $_SESSION['token'] = $client->getAccessToken();
  header('Location: ' . $redirect);
}

if (isset($_SESSION['token'])) {
  $client->setAccessToken($_SESSION['token']);
}

if (!$client->getAccessToken()) {
  print("no access token, whaawhaaa");
  exit;
}

// Add a property to the resource.
function addPropertyToResource(&$ref, $property, $value) {
    $keys = explode(".", $property);
    $is_array = false;
    foreach ($keys as $key) {
        // For properties that have array values, convert a name like
        // "snippet.tags[]" to snippet.tags, and set a flag to handle
        // the value as an array.
        if (substr($key, -2) == "[]") {
            $key = substr($key, 0, -2);
            $is_array = true;
        }
        $ref = &$ref[$key];
    }

    // Set the property value. Make sure array values are handled properly.
    if ($is_array && $value) {
        $ref = $value;
        $ref = explode(",", $value);
    } elseif ($is_array) {
        $ref = array();
    } else {
        $ref = $value;
    }
}

// Build a resource based on a list of properties given as key-value pairs.
function createResource($properties) {
    $resource = array();
    foreach ($properties as $prop => $value) {
        if ($value) {
            addPropertyToResource($resource, $prop, $value);
        }
    }
    return $resource;
}

function uploadMedia($client, $request, $filePath, $mimeType) {
    // Specify the size of each chunk of data, in bytes. Set a higher value for
    // reliable connection as fewer chunks lead to faster uploads. Set a lower
    // value for better recovery on less reliable connections.
    $chunkSizeBytes = 1 * 1024 * 1024;

    // Create a MediaFileUpload object for resumable uploads.
    // Parameters to MediaFileUpload are:
    // client, request, mimeType, data, resumable, chunksize.
    $media = new Google_Http_MediaFileUpload(
        $client,
        $request,
        $mimeType,
        null,
        true,
        $chunkSizeBytes
    );
    $media->setFileSize(filesize($filePath));


    // Read the media file and upload it chunk by chunk.
    $status = false;
    $handle = fopen($filePath, "rb");
    while (!$status && !feof($handle)) {
      $chunk = fread($handle, $chunkSizeBytes);
      $status = $media->nextChunk($chunk);
    }

    fclose($handle);
    return $status;
}

/***** END BOILERPLATE CODE *****/

// Sample php code for search.list

function searchListMine($service, $part, $params)
{
  $params = array_filter($params);
  $response = $service->search->listSearch(
    $part,
    $params
  );

  echo '<ul>';
  foreach($response->items as $key=>$value)
  {
    // print_r($value);
    echo '<li><a href="https://www.youtube.com/watch?v=' . $value->id->videoId . '">' . $value->snippet->title . '</a> ('. date("m/d/Y", strtotime($value->snippet->publishedAt)) .') </li>';
  }
  echo '</ul>';

  /*
  echo '<pre>';
  print_r($response->items);
  echo '</pre>';
  */
}
?>

<!DOCTYPE html>
<html class="ui-mobile">
<head>
	<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
	<base href=".">
	<meta content="width=device-width, initial-scale=1" name="viewport">
	<title>QR Code Editor</title>
	<link href="css/pure-min.css" rel="stylesheet" type="text/css">
	<link href="css/styles.css" rel="stylesheet" type="text/css">
</head>
<body class="ui-mobile-viewport ui-overlay-a" style="">
	<div class="ui-page ui-page-theme-a ui-page-active" data-role="page" style="" tabindex="0">
		<div class="container">
      <h1>Feed of latest videos for LevyLab Research</h1>
			<div class="content">
				<?php
          searchListMine($service,
              'snippet',
              array('maxResults' => 25, 'forMine' => true, 'q' => 'Lab Meeting', 'type' => 'video'));
        ?>
			</div>
		</div>
	</div>
</body>
</html>
