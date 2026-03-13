<?php

class TwitterAPI {
    private $apiKey;
    private $apiSecret;
    private $accessToken;
    private $accessTokenSecret;

    public function __construct($apiKey, $apiSecret, $accessToken, $accessTokenSecret) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->accessToken = $accessToken;
        $this->accessTokenSecret = $accessTokenSecret;
    }

    public function getUser($username) {
        // API call to get user details
    }

    public function getFollowers($userId) {
        // API call to get followers
    }

    public function followUser($userId) {
        // API call to follow a user
    }

    public function unfollowUser($userId) {
        // API call to unfollow a user
    }
}

?>