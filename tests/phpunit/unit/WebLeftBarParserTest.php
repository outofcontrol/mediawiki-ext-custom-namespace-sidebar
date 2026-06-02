<?php
// SPDX-FileCopyrightText: 2026 Out of Control, Inc.
// SPDX-License-Identifier: Apache-2.0

namespace MediaWiki\Extension\CustomNameSpaceSidebar\Tests\Unit;

use MediaWiki\Extension\CustomNameSpaceSidebar\WebLeftBarParser;

// Run as a MediaWiki unit test in CI (against a core checkout) when the base
// class is available, otherwise fall back to a plain PHPUnit test case so the
// suite is runnable standalone without a MediaWiki install.
if ( class_exists( \MediaWikiUnitTestCase::class ) ) {
    abstract class WebLeftBarParserTestBase extends \MediaWikiUnitTestCase {
    }
} else {
    abstract class WebLeftBarParserTestBase extends \PHPUnit\Framework\TestCase {
    }
}

/**
 * @covers \MediaWiki\Extension\CustomNameSpaceSidebar\WebLeftBarParser
 */
class WebLeftBarParserTest extends WebLeftBarParserTestBase
{
    /**
     * A parser whose link resolver maps any page to /wiki/<Page_With_Underscores>,
     * mimicking MediaWiki's local URL shape closely enough for assertions.
     */
    private function parser(): WebLeftBarParser
    {
        return new WebLeftBarParser(static function (string $page): ?string {
            return '/wiki/' . str_replace(' ', '_', $page);
        });
    }

    /** A parser that cannot resolve any link (resolver returns null). */
    private function parserWithoutLinks(): WebLeftBarParser
    {
        return new WebLeftBarParser(static fn (string $page): ?string => null);
    }

    public function testEmptyInputProducesEmptyContainer(): void
    {
        $this->assertSame(
            '<div class="webleftbar-content"></div>',
            $this->parser()->parse('')
        );
    }

    public function testWhitespaceOnlyInputProducesEmptyContainer(): void
    {
        $this->assertSame(
            '<div class="webleftbar-content"></div>',
            $this->parser()->parse("\n   \n\t\n")
        );
    }

    public function testSingleSectionWithOneItem(): void
    {
        $html = $this->parser()->parse("**Docs**\n* [[Home]]");

        $this->assertStringContainsString('<details class="webleftbar-section" open>', $html);
        $this->assertStringContainsString('<summary class="webleftbar-heading">Docs</summary>', $html);
        $this->assertStringContainsString('<a href="/wiki/Home">Home</a>', $html);
    }

    public function testHeadingIsHtmlEscaped(): void
    {
        $html = $this->parser()->parse("**A & B <x>**\n* [[Home]]");

        $this->assertStringContainsString('<summary class="webleftbar-heading">A &amp; B &lt;x&gt;</summary>', $html);
    }

    public function testInternalLinkWithCustomLabel(): void
    {
        $html = $this->parser()->parse("**S**\n* [[Some Page|Custom Label]]");

        $this->assertStringContainsString('<a href="/wiki/Some_Page">Custom Label</a>', $html);
    }

    public function testInternalLinkWithoutLabelUsesPageName(): void
    {
        $html = $this->parser()->parse("**S**\n* [[Some Page]]");

        $this->assertStringContainsString('<a href="/wiki/Some_Page">Some Page</a>', $html);
    }

    public function testUnresolvableInternalLinkRendersPlainText(): void
    {
        $html = $this->parserWithoutLinks()->parse("**S**\n* [[Some Page|Label]]");

        $this->assertStringContainsString('<li>Label</li>', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function testExternalLink(): void
    {
        $html = $this->parser()->parse("**S**\n* [https://example.com External Site]");

        $this->assertStringContainsString(
            '<a href="https://example.com" target="_blank" rel="noopener">External Site</a>',
            $html
        );
    }

    public function testExternalLinkLabelIsEscaped(): void
    {
        $html = $this->parser()->parse("**S**\n* [https://example.com <b>x</b>]");

        $this->assertStringContainsString('rel="noopener">&lt;b&gt;x&lt;/b&gt;</a>', $html);
    }

    public function testMultipleSectionsArePreservedInOrder(): void
    {
        $html = $this->parser()->parse(
            "**One**\n* [[A]]\n\n**Two**\n* [[B]]"
        );

        $posOne = strpos($html, 'One');
        $posTwo = strpos($html, 'Two');
        $this->assertNotFalse($posOne);
        $this->assertNotFalse($posTwo);
        $this->assertLessThan($posTwo, $posOne, 'Section One should render before Section Two');
        $this->assertSame(2, substr_count($html, '<details'));
    }

    public function testBlankLinesAreIgnored(): void
    {
        $html = $this->parser()->parse("**S**\n\n\n* [[A]]\n\n* [[B]]\n");

        $this->assertSame(2, substr_count($html, '<li>'));
    }

    public function testItemsBeforeAnyHeadingRenderAsHeadinglessBlock(): void
    {
        $html = $this->parser()->parse("* [[A]]\n* [[B]]");

        $this->assertStringContainsString('webleftbar-section-headingless', $html);
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('<summary', $html);
        $this->assertSame(2, substr_count($html, '<li>'));
    }

    public function testHeadinglessLeadingBlockRendersBeforeFirstSection(): void
    {
        // This is the regression case from the Brno meeting sidebar: three
        // top-level links followed by a "Working Groups" section.
        $wikitext = <<<'WIKI'
* [[2026-06 Brno|Web Home]]

* [[2026-06 Brno:Agenda|Agenda]]
* [[2026-06 Brno:MeetingRooms|Meeting Rooms]]

** Working Groups **
* [[2026-06 Brno:CoreWorkingGroup|Core]]
* [[2026-06 Brno:LibraryWorkingGroup|Library]]
WIKI;

        $html = $this->parser()->parse($wikitext);

        $headinglessPos = strpos($html, 'webleftbar-section-headingless');
        $workingGroupsPos = strpos($html, 'Working Groups');

        $this->assertNotFalse($headinglessPos);
        $this->assertNotFalse($workingGroupsPos);
        $this->assertLessThan(
            $workingGroupsPos,
            $headinglessPos,
            'Leading links must render before the Working Groups section'
        );

        // The three leading links must not be swallowed into the section.
        $this->assertStringContainsString('>Web Home</a>', $html);
        $this->assertStringContainsString('>Agenda</a>', $html);
        $this->assertStringContainsString('>Meeting Rooms</a>', $html);

        // Exactly one collapsible section (Working Groups), plus one headingless block.
        $this->assertSame(1, substr_count($html, '<details'));
        $this->assertSame(1, substr_count($html, 'webleftbar-section-headingless'));
    }

    public function testHeadingWithSurroundingSpacesIsTrimmed(): void
    {
        $html = $this->parser()->parse("** Spaced Heading **\n* [[A]]");

        $this->assertStringContainsString('<summary class="webleftbar-heading">Spaced Heading</summary>', $html);
    }

    public function testEmptySectionWithNoItemsIsOmitted(): void
    {
        // A heading followed by another heading (no items between) drops the
        // empty first section.
        $html = $this->parser()->parse("**Empty**\n**Filled**\n* [[A]]");

        $this->assertStringNotContainsString('>Empty</summary>', $html);
        $this->assertStringContainsString('>Filled</summary>', $html);
        $this->assertSame(1, substr_count($html, '<details'));
    }

    public function testBuildCollapsibleSectionWithNullHeading(): void
    {
        $html = $this->parser()->buildCollapsibleSection(null, ['<a href="/x">X</a>']);

        $this->assertStringContainsString('webleftbar-section-headingless', $html);
        $this->assertStringContainsString('<li><a href="/x">X</a></li>', $html);
    }

    public function testBuildCollapsibleSectionWithNoItemsReturnsEmptyString(): void
    {
        $this->assertSame('', $this->parser()->buildCollapsibleSection('Heading', []));
        $this->assertSame('', $this->parser()->buildCollapsibleSection(null, []));
    }

    public function testMixedInternalAndExternalLinksInOneItem(): void
    {
        $html = $this->parser()->parse("**S**\n* [[Home]] and [https://ex.com Ext]");

        $this->assertStringContainsString('<a href="/wiki/Home">Home</a>', $html);
        $this->assertStringContainsString('rel="noopener">Ext</a>', $html);
    }
}
