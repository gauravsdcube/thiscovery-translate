<?php

namespace humhub\modules\thiscoveryTranslate\tests\unit;

use humhub\modules\thiscoveryTranslate\services\ContentProtector;
use PHPUnit\Framework\TestCase;

class ContentProtectorTest extends TestCase
{
    public function testProtectsUrlsEmailsAndPlaceholders(): void
    {
        $p = new ContentProtector();
        $src = 'Hello https://example.com a@b.co {name} 550e8400-e29b-41d4-a716-446655440000';
        $protected = $p->protect($src);
        $this->assertStringNotContainsString('https://example.com', $protected);
        $this->assertStringNotContainsString('a@b.co', $protected);
        $this->assertStringNotContainsString('{name}', $protected);
        $this->assertStringNotContainsString('550e8400-e29b-41d4-a716-446655440000', $protected);
        $this->assertStringContainsString('Hello', $protected);
        $this->assertStringContainsString('data-tth=', $protected);
        $restored = $p->restore($protected);
        $this->assertSame($src, $restored);
    }

    public function testProtectsHtmlTags(): void
    {
        $p = new ContentProtector();
        $src = '<p class="te-p">Hello <strong>world</strong></p>';
        $protected = $p->protect($src);
        $this->assertStringNotContainsString('<p', $protected);
        $this->assertSame($src, $p->restore($protected));
    }

    public function testLooksLeaked(): void
    {
        $this->assertTrue(ContentProtector::looksLeaked('ZTT1ZZZZT1Z hello'));
        $this->assertTrue(ContentProtector::looksLeaked('ZZTT0ZZ'));
        $this->assertTrue(ContentProtector::looksLeaked('<span translate="no" data-tth="0"></span>'));
        $this->assertFalse(ContentProtector::looksLeaked('Normal Hindi पाठ'));
    }
}
