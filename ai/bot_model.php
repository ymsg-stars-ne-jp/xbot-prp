<?php

// Enhanced Bot Detection Algorithm

class BotDetection {
    private $followers;
    private $following;
    private $tweetCount;
    private $profileDetails;
    private $accountAge;

    public function __construct($followers, $following, $tweetCount, $profileDetails, $accountAge) {
        $this->followers = $followers;
        $this->following = $following;
        $this->tweetCount = $tweetCount;
        $this->profileDetails = $profileDetails;
        $this->accountAge = $accountAge;
    }

    public function analyze() {
        $score = 0;

        // Analyze follower to following ratio
        $ratio = $this->followers / max($this->following, 1);
        if ($ratio < 1) {
            $score--;
        } else if ($ratio > 2) {
            $score++;
        }

        // Analyze tweet count
        if ($this->tweetCount < 50) {
            $score--;
        } else if ($this->tweetCount > 1000) {
            $score++;
        }

        // Analyze profile details
        if (empty($this->profileDetails['bio']) || strlen($this->profileDetails['bio']) < 20) {
            $score--;
        }

        // Analyze account age
        if ($this->accountAge < 1) {
            $score--;
        } else if ($this->accountAge > 5) {
            $score++;
        }

        return $score >= 0 ? 'Likely Human' : 'Likely Bot';
    }
}

?>