<?php

namespace MediaWiki\Extension\CustomNameSpaceSidebar;

/**
 * Converts WebLeftBar wikitext into the collapsible sidebar HTML.
 *
 * This class holds the pure presentation logic and has no dependency on
 * MediaWiki services, so it can be unit tested in isolation. The only
 * MediaWiki specific concern (resolving an internal page title to a URL)
 * is injected as a callable.
 */
class WebLeftBarParser
{
    /**
     * @var callable A function that takes an internal page title (string) and
     *               returns its local URL (string), or null if it cannot be
     *               resolved. When null, the link text is rendered as plain text.
     */
    private $linkResolver;

    /**
     * @param callable|null $linkResolver fn(string $page): ?string
     *        Resolves an internal wiki page title to a local URL. If omitted,
     *        internal links are rendered as plain (unlinked) text.
     */
    public function __construct(?callable $linkResolver = null)
    {
        $this->linkResolver = $linkResolver ?? static fn (string $page): ?string => null;
    }

    /**
     * Parse wikitext content and convert to collapsible HTML.
     *
     * Sections are introduced with `**Heading**`. List items begin with `* `.
     * Items that appear before any heading are rendered as a leading,
     * headingless block in their original position.
     *
     * @param string $wikitext
     * @return string
     */
    public function parse(string $wikitext): string
    {
        $lines = explode("\n", trim($wikitext));
        $html = '<div class="webleftbar-content">';
        $currentSection = null;
        $currentList = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^\*\*(.+)\*\*$/', $line, $matches)) {
                if ($currentSection !== null || !empty($currentList)) {
                    $html .= $this->buildCollapsibleSection($currentSection, $currentList);
                    $currentList = [];
                }
                $currentSection = trim($matches[1]);
            } elseif (preg_match('/^\*\s*(.+)$/', $line, $matches)) {
                $listItem = $this->parseWikiLink($matches[1]);
                $listItem = $this->parseExternalLink($listItem);

                $currentList[] = $listItem;
            }
        }

        if ($currentSection !== null || !empty($currentList)) {
            $html .= $this->buildCollapsibleSection($currentSection, $currentList);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build a collapsible section with details/summary.
     * When $heading is null, the items are rendered as a headingless block.
     *
     * @param string|null $heading
     * @param string[] $items
     * @return string
     */
    public function buildCollapsibleSection(?string $heading, array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $listItems = '<li>' . implode('</li><li>', $items) . '</li>';

        if ($heading === null) {
            $template = <<<'HTML'
<div class="webleftbar-section webleftbar-section-headingless">
  <ul class="webleftbar-list">
    %s
  </ul>
</div>
HTML;

            return sprintf($template, $listItems);
        }

        $escapedHeading = htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);

        $template = <<<'HTML'
<details class="webleftbar-section" open>
  <summary class="webleftbar-heading">%s</summary>
  <ul class="webleftbar-list">
    %s
  </ul>
</details>
HTML;

        return sprintf($template, $escapedHeading, $listItems);
    }

    /**
     * Parse internal wiki links ([[Page]] or [[Page|Label]]) into anchors.
     *
     * @param string $text
     * @return string
     */
    public function parseWikiLink(string $text): string
    {
        $pattern = '/\[\[([^|\]]+)(?:\|([^\]]+))?\]\]/';

        return preg_replace_callback($pattern, function ($matches) {
            $page = $matches[1];
            $display = isset($matches[2]) ? $matches[2] : $page;

            $url = ($this->linkResolver)($page);

            if ($url !== null) {
                return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($display) . '</a>';
            }

            return htmlspecialchars($display);
        }, $text);
    }

    /**
     * Parse external links ([https://example.com Label]) into anchors.
     *
     * @param string $text
     * @return string
     */
    public function parseExternalLink(string $text): string
    {
        $pattern = '/\[([a-z][a-z0-9+.\-]*:\/\/[^\s\]]+)\s+([^\]]+)\]/';

        return preg_replace_callback($pattern, function ($matches) {
            $url = $matches[1];
            $display = $matches[2];

            return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . htmlspecialchars($display) . '</a>';
        }, $text);
    }
}
