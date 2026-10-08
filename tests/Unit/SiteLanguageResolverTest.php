<?php
/**
 * Site output language resolution tests.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Localization\SiteLanguageResolver;

/** Verifies site locale normalization without using a dashboard user locale. */
final class SiteLanguageResolverTest extends TestCase {

	/**
	 * Supplies locale normalization examples.
	 *
	 * @return iterable<string,array{mixed,string}> Locale examples.
	 */
	public static function locales(): iterable {
		yield 'German region' => array( 'de_DE', 'de' );
		yield 'English region with hyphen' => array( 'en-GB', 'en' );
		yield 'three-letter language' => array( 'dsb_DE', 'dsb' );
		yield 'blank' => array( '', 'auto' );
		yield 'malformed' => array( 'deutsch_DE', 'auto' );
		yield 'non-string' => array( null, 'auto' );
	}

	/** Verifies one locale normalization example. */
	#[DataProvider( 'locales' )]
	public function test_primary_language_normalization( mixed $locale, string $expected ): void {
		self::assertSame( $expected, SiteLanguageResolver::primary_from_locale( $locale ) );
	}
}
