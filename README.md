<!--
SPDX-FileCopyrightText: 2026 Out of Control, Inc.
SPDX-License-Identifier: CC-BY-4.0
-->

# CustomNameSpaceSidebar

A MediaWiki extension that automatically adds a custom sidebar menu to any namespace by reading a designated wiki page.

## Requirements

- MediaWiki 1.42 or later
- Vector or Vector 2022 skin

## Installation

1. Clone or extract the extension into your `extensions/` directory as `CustomNameSpaceSidebar`.
2. Add the following to `LocalSettings.php`:

```php
wfLoadExtension( 'CustomNameSpaceSidebar' );
$wgCustomNameSpaceSidebar = true;
```

## Configuration

| Variable | Default | Description |
|---|---|---|
| `$wgCustomNameSpaceSidebar` | `false` | Set to `true` to enable the extension |

## Usage

Create a wiki page named `WebLeftBar` in any namespace to define the sidebar for that namespace. For a page in the `Foo:` namespace, create `Foo:WebLeftBar`. For the main namespace, create `WebLeftBar`.

### WebLeftBar Format

Sections are defined with `**Section Title**` and list items with `* `. Both internal and external links are supported.

```
**Section One**
* [[Some Page]]
* [[Some Page|Custom Label]]

**Section Two**
* [https://example.com External Site]
```

Each section renders as a collapsible block in the sidebar.

List items that appear before the first section heading are rendered as a leading block without a heading, in their original position.

## Testing

The wikitext parsing logic lives in `WebLeftBarParser`, which has no MediaWiki dependencies, so its unit tests run standalone (no MediaWiki core checkout required).

```bash
composer install
composer phpunit
```

`composer test` runs the linters, the unit tests, PHP CodeSniffer, and minus-x together.

In Wikimedia CI the same tests are discovered through MediaWiki core's PHPUnit entry point under `tests/phpunit/unit`. The test case extends `MediaWikiUnitTestCase` when that base class is available and falls back to plain PHPUnit otherwise.

The MediaWiki coupled parts (`Hooks`, title resolution, page loading) are intended for integration tests under `tests/phpunit/integration`, which require a MediaWiki core checkout to run.

## License

MIT