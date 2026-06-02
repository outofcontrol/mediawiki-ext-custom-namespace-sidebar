<?php
// SPDX-FileCopyrightText: 2026 Out of Control, Inc.
// SPDX-License-Identifier: Apache-2.0

/**
 * Standalone PHPUnit bootstrap for the CustomNameSpaceSidebar unit tests.
 *
 * This lets the unit tests run without a MediaWiki core checkout. It registers
 * a minimal PSR-4 autoloader for the extension's src/ classes and loads a
 * Composer autoloader if one is available (local vendor/ or the global one).
 *
 * In Wikimedia CI the tests run via MediaWiki core's own PHPUnit entry point,
 * which provides the autoloader and the MediaWikiUnitTestCase base class; this
 * bootstrap is only used for standalone runs.
 */

// Load a Composer autoloader so PHPUnit's classes are available, if PHPUnit is
// not already loaded by the calling runner.
if ( !class_exists( \PHPUnit\Framework\TestCase::class ) ) {
    $candidates = [
        __DIR__ . '/../vendor/autoload.php',
        getenv( 'HOME' ) . '/.composer/vendor/autoload.php',
        getenv( 'HOME' ) . '/.config/composer/vendor/autoload.php',
    ];
    foreach ( $candidates as $candidate ) {
        if ( is_file( $candidate ) ) {
            require_once $candidate;
            break;
        }
    }
}

// Minimal PSR-4 autoloader for the extension source, so we do not depend on a
// generated Composer autoload map being present.
spl_autoload_register( static function ( string $class ): void {
    $prefix = 'MediaWiki\\Extension\\CustomNameSpaceSidebar\\';
    if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );

    // Test classes live under tests/phpunit, production classes under src/.
    if ( strncmp( $relative, 'Tests\\', 6 ) === 0 ) {
        $relative = substr( $relative, strlen( 'Tests\\' ) );
        $base = __DIR__ . '/phpunit/';
        // Tests\Unit\Foo -> phpunit/unit/Foo.php
        $relative = preg_replace_callback(
            '/^([A-Za-z]+)\\\\/',
            static fn ( array $m ): string => strtolower( $m[1] ) . '/',
            $relative
        );
    } else {
        $base = __DIR__ . '/../src/';
    }

    $path = $base . str_replace( '\\', '/', $relative ) . '.php';
    if ( is_file( $path ) ) {
        require_once $path;
    }
} );
