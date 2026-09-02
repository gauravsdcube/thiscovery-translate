<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\tests\unit;

use humhub\modules\thiscoveryTranslate\services\ContentProtector;
use PHPUnit\Framework\TestCase;

class ContentProtectorTest extends TestCase
{
    public function testProtectsPlaceholdersUrlsEmailsAndUuids(): void
    {
        $p = new ContentProtector();
        $src = 'Hello {name} visit https://example.com contact a@b.co id 550e8400-e29b-41d4-a716-446655440000';
        $protected = $p->protect($src);
        $this->assertStringNotContainsString('https://example.com', $protected);
        $this->assertStringNotContainsString('a@b.co', $protected);
        $this->assertStringNotContainsString('{name}', $protected);
        $this->assertStringNotContainsString('550e8400-e29b-41d4-a716-446655440000', $protected);
        $this->assertStringContainsString('Hello', $protected);
        $restored = $p->restore($protected);
        $this->assertSame($src, $restored);
    }

    public function testProtectsHtmlTags(): void
    {
        $p = new ContentProtector();
        $src = '<p class="x">Hi</p>';
        $protected = $p->protect($src);
        $this->assertStringNotContainsString('<p', $protected);
        $this->assertSame($src, $p->restore($protected));
    }
}
