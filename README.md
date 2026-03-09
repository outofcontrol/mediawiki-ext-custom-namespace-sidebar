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

## License

MIT