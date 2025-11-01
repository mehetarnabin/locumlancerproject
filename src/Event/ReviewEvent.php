<?php

namespace App\Event;

use App\Entity\Review;

class ReviewEvent
{
    public const PROVIDER_REVIEWED = 'application.provider_reviewed';
    public const EMPLOYER_REVIEWED = 'application.employer_reviewed';

    public function __construct(private Review $review){}

    public function getReview(): Review
    {
        return $this->review;
    }
}