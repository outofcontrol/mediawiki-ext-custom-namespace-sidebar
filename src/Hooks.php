<?php

namespace MediaWiki\Extension\CustomNameSpaceSidebar;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;

class Hooks implements \MediaWiki\Hook\BeforePageDisplayHook
{
    /** @var string Name of page to use for sidebar */
    private $WebLeftBar = 'WebLeftBar';

    /**
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
     * @param \OutputPage $out
     * @param \Skin $skin
     */
    public function onBeforePageDisplay($out, $skin): void
    {
        global $wgCustomNameSpaceSidebar;

        if (!isset($wgCustomNameSpaceSidebar) || !$wgCustomNameSpaceSidebar) {
            return;
        }

        if (!in_array($skin->getSkinName(), ['vector','vector-2022'])) {
            return;
        }

        $services = MediaWikiServices::getInstance();
        $namespaceInfo = $services->getNamespaceInfo();

        $context = $skin->getContext();
        $currentTitle = $context->getTitle();
        $namespace = $currentTitle->getNamespace();
        $fullTitle = $currentTitle->getFullText();

        $webLeftBarTitle = $this->determineWebLeftBarTitle($fullTitle, $namespace, $namespaceInfo);

        $sidebarContent = $this->loadWebLeftBarContent($webLeftBarTitle);

        if ($sidebarContent) {
            $out->addModules('ext.customnamespace.sidebar');
            $out->addHTML('<div id="MenuSidebar" class="ext-customnamespace-sidebar">' . $sidebarContent . '</div>');
        }
    }

    /**
     * Determine the correct WebLeftBar page title
     * @param string $fullTitle
     * @param int $namespace
     * @param NamespaceInfo $namespaceInfo
     * @return string
     */
    private function determineWebLeftBarTitle(string $fullTitle, int $namespace, NamespaceInfo $namespaceInfo): string
    {
        if (strpos($fullTitle, ':') !== false) {
            $parts = explode(':', $fullTitle, 2);
            if (count($parts) === 2) {
                $namespacePart = $parts[0];
                return $namespacePart . ':' . $this->WebLeftBar;
            }
        }

        $possibleWebLeftBar = $fullTitle . ':' . $this->WebLeftBar;
        [$services, $testTitle] = $this->getTitle($possibleWebLeftBar);

        if ($testTitle && $testTitle->exists()) {
            return $possibleWebLeftBar;
        }

        return $this->WebLeftBar;
    }

    /**
     * Load and parse WebLeftBar page content
     * @param string $pageTitle
     * @return string|null
     */
    private function loadWebLeftBarContent(string $pageTitle): ?string
    {
        [$services, $title] = $this->getTitle($pageTitle);
        if (!$title || !$title->exists()) {
            return null;
        }

        $wikiPage = $services->getWikiPageFactory()->newFromTitle($title);
        $content = $wikiPage->getContent();

        if (!$content) {
            return null;
        }

        $wikitext = $content->getText();

        $parsed = $this->parseWebLeftBarContent($wikitext);

        return $parsed;
    }

    private function getTitle(string $pageTitle)
    {
        $services = MediaWikiServices::getInstance();
        $titleFactory = $services->getTitleFactory();

        return [$services, $titleFactory->newFromText($pageTitle)];
    }

    /**
     * Parse wikitext content and convert to collapsible HTML
     * @param string $wikitext
     * @return string
     */
    private function parseWebLeftBarContent(string $wikitext): string
    {
        $lines = explode("\n", trim($wikitext));
        $html = '<div class="webleftbar-content">';
        $currentSection = null;
        $currentList = [];


        foreach ($lines as $lineNum => $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (preg_match('/^\*\*(.+)\*\*$/', $line, $matches)) {
                if ($currentSection !== null) {
                    $html .= $this->buildCollapsibleSection($currentSection, $currentList);
                    $currentList = [];
                }
                $currentSection = trim($matches[1]);
            } elseif (preg_match('/^\*\s*(.+)$/', $line, $matches)) {
                $listItem = $this->parseWikiLink($matches[1]);
                $currentList[] = $listItem;
            }
        }

        if ($currentSection !== null) {
            $html .= $this->buildCollapsibleSection($currentSection, $currentList);
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Build a collapsible section with details/summary
     * @param string $heading
     * @param array $items
     * @return string
     */
    private function buildCollapsibleSection(string $heading, array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $escapedHeading = htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);

        $listItems = '<li>' . implode('</li><li>', $items) . '</li>';

        $template = <<<'HTML'
<details class="webleftbar-section">
  <summary class="webleftbar-heading">%s</summary>
  <ul class="webleftbar-list">
    %s
  </ul>
</details>
HTML;

        return sprintf($template, $escapedHeading, $listItems);
    }

    /**
     * Parse wiki links and convert to HTML
     * @param string $text
     * @return string
     */
    private function parseWikiLink(string $text): string
    {
        $pattern = '/\[\[([^|\]]+)(?:\|([^\]]+))?\]\]/';
        return preg_replace_callback($pattern, function ($matches) {
            $page = $matches[1];
            $display = isset($matches[2]) ? $matches[2] : $page;

            [$services, $title] = $this->getTitle($page);

            if ($title) {
                $url = $title->getLocalURL();
                return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($display) . '</a>';
            }

            return htmlspecialchars($display);
        }, $text);
    }
}
