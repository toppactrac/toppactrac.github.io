<?php
session_start();

$initial_token = ""; // Your Initial Access Token

function cnvs_refresh_token( $token, $token_file ) {
	$instagram_token = curl_init();
	curl_setopt( $instagram_token, CURLOPT_URL, "https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=" . $token );
	curl_setopt( $instagram_token, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $instagram_token, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $instagram_token, CURLOPT_HTTPGET, true );
	$token_response = curl_exec( $instagram_token );

	$token_code = json_decode( $token_response, true );
	$cached_token = serialize( $token_code );

	if ( !empty( $token_response ) ) {
		$token_cache = fopen( $token_file, 'wb' );
		fwrite( $token_cache, $cached_token );
		fclose( $token_cache );
	}

	return $cached_token;
}

$token_file = dirname( __FILE__ ) . '/cache/token';

$token_available = true;

if( file_exists( $token_file ) ) {
	$get_cached_token = @unserialize( file_get_contents( $token_file ) );
	$last_token = filemtime($token_file);
	$now_token = time();

	if( !isset( $get_cached_token['access_token'] ) ) {
		$token_available = false;
	} else {
		$final_token = $get_cached_token['access_token'];

		if( ( $get_cached_token['expires_in'] - ( $now_token - $last_token ) ) < 86400 ) {
			$token_available = false;
		}
	}
} else {
	$token_available = false;
}

if ( !$token_available ) {
	$retrieve_token = @unserialize( file_get_contents( $token_file ) );

	cnvs_refresh_token( $initial_token, $token_file );
	$get_cached_token = @unserialize( file_get_contents( $token_file ) );
	$final_token = $get_cached_token['access_token'];
}

$interval = 3600;

$cache_file = dirname( __FILE__ ) . '/cache/instagram';

if ( file_exists( $cache_file ) ) {
	$last = filemtime($cache_file);
} else { $last = false; }

$now = time();

if ( !$last || (( $now - $last ) > $interval ) ) {

	$instagram_api = curl_init();
	curl_setopt( $instagram_api, CURLOPT_URL, "https://graph.instagram.com/me/media?fields=id&access_token=" . $final_token );
	curl_setopt( $instagram_api, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $instagram_api, CURLOPT_RETURNTRANSFER, true );
	$instagram_response = curl_exec( $instagram_api );

	$images = json_decode( $instagram_response, true );
	$images = $images['data'];

	$image_list = array();

	if( is_array( $images ) && ! empty( $images ) ) {
		foreach( $images as $image ) {

			$insta_image = curl_init();
			curl_setopt( $insta_image, CURLOPT_URL, "https://graph.instagram.com/" . $image['id'] . "?fields=media_url,permalink,media_type,thumbnail_url&access_token=" . $final_token );
			curl_setopt( $insta_image, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $insta_image, CURLOPT_RETURNTRANSFER, true );
			$insta_image_resp = curl_exec( $insta_image );

			$image_list[] = json_decode( $insta_image_resp, true );

		}
	}

	$cached_shots = serialize( $image_list );

	if ( !empty( $cached_shots ) ) {
		$cache_static = fopen( $cache_file, 'wb' );
		fwrite( $cache_static, $cached_shots );
		fclose( $cache_static );
	}

	$shots = @unserialize( file_get_contents( $cache_file ) );
} else {
	$shots = @unserialize( file_get_contents( $cache_file ) );
}

echo json_encode( $shots );

?>