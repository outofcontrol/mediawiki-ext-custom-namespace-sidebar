<?php

namespace MediaWiki\Extension\CustomNameSpaceSidebar;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\NamespaceInfo;

class Hooks
{
    /** @var string Name of page to use for sidebar */
    private $WebLeftBar = 'WebLeftBar';

    /**
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinAfterPortlet
     * @param \Skin $skin
     * @param string $portletName
     * @param string &$html
     */
    public function onSkinAfterPortlet( $skin, $portletName, &$html ): void
    {
        global $wgCustomNameSpaceSidebar;

        if ( !isset( $wgCustomNameSpaceSidebar ) || !$wgCustomNameSpaceSidebar ) {
            return;
        }

        if ( $portletName !== 'custommenu' ) {
            return;
        }

        if ( !in_array( $skin->getSkinName(), [ 'vector', 'vector-2022' ] ) ) {
            return;
        }

        $services = MediaWikiServices::getInstance();
        $namespaceInfo = $services->getNamespaceInfo();

        $context = $skin->getContext();
        $currentTitle = $context->getTitle();
        $namespace = $currentTitle->getNamespace();
        $fullTitle = $currentTitle->getFullText();

        $webLeftBarTitle = $this->determineWebLeftBarTitle( $fullTitle, $namespace, $namespaceInfo );

        $sidebarContent = $this->loadWebLeftBarContent( $webLeftBarTitle );

        if ( $sidebarContent ) {
            $skin->getOutput()->addModuleStyles( 'ext.customnamespace.sidebar' );

            $html .= '<div id="MenuSidebar" class="ext-customnamespace-sidebar">' . $sidebarContent . '</div>';
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

        return $this->makeParser()->parse($wikitext);
    }

    private function getTitle(string $pageTitle)
    {
        $services = MediaWikiServices::getInstance();
        $titleFactory = $services->getTitleFactory();

        return [$services, $titleFactory->newFromText($pageTitle)];
    }

    /**
     * Build a parser whose internal link resolution is backed by MediaWiki.
     * @return WebLeftBarParser
     */
    private function makeParser(): WebLeftBarParser
    {
        return new WebLeftBarParser(function (string $page): ?string {
            [, $title] = $this->getTitle($page);

            return $title ? $title->getLocalURL() : null;
        });
    }
}
