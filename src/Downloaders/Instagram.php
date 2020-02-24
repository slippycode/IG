<?php

namespace Slippy\Downloaders;

use GuzzleHttp\Client;

class Instagram
{
    public static function guzzle()
    {
        return new Client;
    }

    public static function getInfo($url)
    {
        $curl_content = static::guzzle()->get($url)->getBody();
        $media_info = static::infoData($url);
        $video["title"] = static::get_title($curl_content);
        $video["thumbnail"] = static::get_thumbnail($curl_content);
        $i = 0;
        foreach ($media_info["links"] as $link) {
            switch ($link["type"]) {
                case "video":
                    $video["links"][$i]["url"] = $link["url"];
                    $video["links"][$i]["type"] = "mp4";
                    $video["links"][$i]["size"] = static::get_file_size($video["links"]["0"]["url"]);
                    $video["links"][$i]["quality"] = "HD";
                    $video["links"][$i]["mute"] = "no";
                    $i++;
                    break;
                case "image":
                    $video["links"][$i]["url"] = $link["url"];
                    $video["links"][$i]["type"] = "jpg";
                    $video["links"][$i]["size"] = static::get_file_size($video["links"]["0"]["url"]);
                    $video["links"][$i]["quality"] = "HD";
                    $video["links"][$i]["mute"] = "yes";
                    $i++;
                    break;
                default:
                    break;
            }
        }
        return $video;
    }

    public static function infoData($url)
    {
        $scrape = static::guzzle()->get($url)->getBody();
        preg_match_all('/window._sharedData = (.*);/', $scrape, $matches);
        if (!$matches) {
            return false;
        } else {
            $json = $matches[1][0];
            $data = json_decode($json, true);
            if ($data['entry_data']['PostPage'][0]['graphql']['shortcode_media']['__typename'] == "GraphImage") {
                $imagesdata = $data['entry_data']['PostPage'][0]['graphql']['shortcode_media']['display_resources'];
                $length = count($imagesdata);
                $media_info['links'][0]['type'] = 'image';
                $media_info['links'][0]['url'] = $imagesdata[$length - 1]['src'];
                $media_info['links'][0]['status'] = 'success';
            } else {
                if ($data['entry_data']['PostPage'][0]['graphql']['shortcode_media']['__typename'] == "GraphSidecar") {
                    $counter = 0;
                    $multipledata = $data['entry_data']['PostPage'][0]['graphql']['shortcode_media']['edge_sidecar_to_children']['edges'];
                    foreach ($multipledata as &$media) {
                        if ($media['node']['is_video'] == "true") {
                            $media_info['links'][$counter]["url"] = $media['node']['video_url'];
                            $media_info['links'][$counter]["type"] = 'video';
                        } else {
                            $length = count($media['node']['display_resources']);
                            $media_info['links'][$counter]["url"] = $media['node']['display_resources'][$length - 1]['src'];
                            $media_info['links'][$counter]["type"] = 'image';
                        }
                        $counter++;
                        $media_info['type'] = 'media';
                    }
                    $media_info['status'] = 'success';
                } else {
                    if ($data['entry_data']['PostPage'][0]['graphql']['shortcode_media']['__typename'] == "GraphVideo") {
                        $videolink = $data['entry_data']['PostPage'][0]['graphql']['shortcode_media']['video_url'];
                        $media_info['links'][0]['type'] = 'video';
                        $media_info['links'][0]['url'] = $videolink;
                        $media_info['links'][0]['status'] = 'success';
                    } else {
                        $media_info['links']['status'] = 'fail';
                    }
                }
            }
            $owner = $data['entry_data']['PostPage'][0]['graphql']['shortcode_media']['owner'];
            $media_info['username'] = $owner['username'];
            $media_info['full_name'] = $owner['full_name'];
            $media_info['profile_pic_url'] = $owner['profile_pic_url'];
            return $media_info;
        }
    }
    public static function get_type($curl_content)
    {
        if (preg_match_all('@<meta property="og:type" content="(.*?)" />@si', $curl_content, $match)) {
            return $match[1][0];
        }
    }

    public static function get_image($curl_content)
    {
        if (preg_match_all('@<meta property="og:image" content="(.*?)" />@si', $curl_content, $match)) {
            return $match[1][0];
        }
    }

    public static function get_video($curl_content)
    {

        if (preg_match_all('@<meta property="og:video" content="(.*?)" />@si', $curl_content, $match)) {
            return $match[1][0];
        }
    }

    public static function get_thumbnail($curl_content)
    {
        if (preg_match_all('@<meta property="og:image" content="(.*?)" />@si', $curl_content, $match)) {
            return $match[1][0];
        }
    }

    public static function get_title($curl_content)
    {
        if (preg_match_all('@<title>(.*?)</title>@si', $curl_content, $match)) {
            return trim($match[1][0]);
        }
    }
    public static function get_file_size($url, $format = true)
    {
        $result = -1;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $headers = curl_exec($curl);
        if (curl_errno($curl) == 0) {
            $result = (int) curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        }
        curl_close($curl);
        if ($result > 100) {
            switch ($format) {
                case true:
                    return static::format_size($result);
                    break;
                case false:
                    return $result;
                    break;
                default:
                    return static::format_size($result);
                    break;
            }
        } else {
            return null;
        }
    }

    public static function format_size($bytes)
    {
        switch ($bytes) {
            case $bytes < 1024:
                $size = $bytes . " B";
                break;
            case $bytes < 1048576:
                $size = round($bytes / 1024, 2) . " KB";
                break;
            case $bytes < 1073741824:
                $size = round($bytes / 1048576, 2) . " MB";
                break;
            case $bytes < 1099511627776:
                $size = round($bytes / 1073741824, 2) . " GB";
                break;
        }
        if (!empty($size)) {
            return $size;
        } else {
            return null;
        }
    }
}
