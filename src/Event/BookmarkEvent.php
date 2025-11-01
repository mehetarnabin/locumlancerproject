<?php

namespace App\Event;

use App\Entity\Bookmark;

class BookmarkEvent
{
    public const BOOKMARK_CREATED = 'bookmark.created';

    public function __construct(private Bookmark $bookmark){}

    public function getBookmark(): Bookmark
    {
        return $this->bookmark;
    }
}