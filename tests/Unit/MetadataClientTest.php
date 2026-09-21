<?php
/**
 * Tests for the fixed-origin metadata client.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Config\EmbedUrlBuilder;
use TurnierplanEU\WordPress\Config\ServiceConfiguration;
use TurnierplanEU\WordPress\Remote\HttpResponse;
use TurnierplanEU\WordPress\Remote\HttpTransport;
use TurnierplanEU\WordPress\Remote\MetadataClient;
use TurnierplanEU\WordPress\Remote\MetadataResponseValidator;
use TurnierplanEU\WordPress\Remote\TransportFailure;
use TurnierplanEU\WordPress\Remote\TrustedMetadataUrl;

/**
 * Queue-backed transport for deterministic client tests.
 */
final class MetadataClientTestTransport implements HttpTransport {

	/**
	 * Queued transport results.
	 *
	 * @var list<HttpResponse|TransportFailure>
	 */
	private array $responses;

	/**
	 * Observed requests.
	 *
	 * @var list<array{url:string,args:array<string,mixed>}>
	 */
	public array $requests = array();

	/**
	 * Creates the test transport.
	 *
	 * @param list<HttpResponse|TransportFailure> $responses Queued results.
	 */
	public function __construct( array $responses ) {
		$this->responses = $responses;
	}

	/** Returns the next queued result. */
	public function get( string $url, array $args ): HttpResponse|TransportFailure {
		$this->requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		return array_shift( $this->responses ) ?? new TransportFailure( 'transport_error' );
	}
}

/**
 * Verifies URL trust, response validation, and stable error states.
 */
final class MetadataClientTest extends TestCase {

	/** A valid response is accepted with hardened request arguments. */
	public function test_fetches_valid_metadata_from_fixed_endpoint(): void {
		$transport = new MetadataClientTestTransport(
			array(
				new HttpResponse(
					200,
					array(
						'content-type' => 'application/json',
						'etag'         => '"v1"',
					),
					$this->metadata_json()
				),
			)
		);
		$result    = $this->client( $transport )->fetch( 'Sommer-Cup', 'de', '"old"' );

		$this->assertTrue( $result->is_success() );
		$this->assertSame( '"v1"', $result->get_etag() );
		$this->assertSame( 'https://www.turnierplan.eu/api/embed/v1/tournaments/sommer-cup/metadata?lang=de', $transport->requests[0]['url'] );
		$this->assertSame( 0, $transport->requests[0]['args']['redirection'] );
		$this->assertTrue( $transport->requests[0]['args']['sslverify'] );
		$this->assertTrue( $transport->requests[0]['args']['reject_unsafe_urls'] );
		$this->assertSame( array(), $transport->requests[0]['args']['cookies'] );
		$this->assertSame( '"old"', $transport->requests[0]['args']['headers']['If-None-Match'] );
	}

	/**
	 * Supplies final HTTP outcomes that need stable result codes.
	 *
	 * @return iterable<string,array{HttpResponse|TransportFailure,string,int|null}>
	 */
	public static function status_cases(): iterable {
		yield 'not modified' => array( new HttpResponse( 304, array(), '' ), 'not_modified', null );
		yield 'not found' => array( new HttpResponse( 404, array(), '{}' ), 'tournament_not_found', null );
		yield 'rate limit' => array( new HttpResponse( 429, array( 'retry-after' => '120' ), '{}' ), 'rate_limited', 120 );
		yield 'unavailable' => array( new HttpResponse( 503, array( 'retry-after' => '30' ), '{}' ), 'temporarily_unavailable', 30 );
		yield 'transport timeout' => array( new TransportFailure( 'timeout' ), 'timeout', null );
	}

	/** Maps supported HTTP and transport states without exposing remote text. */
	#[DataProvider( 'status_cases' )]
	public function test_maps_remote_statuses( HttpResponse|TransportFailure $response, string $code, ?int $retry_after ): void {
		$result = $this->client( new MetadataClientTestTransport( array( $response ) ) )->fetch( '123', 'auto' );

		$this->assertSame( $code, $result->get_code() );
		$this->assertSame( $retry_after, $result->get_retry_after() );
	}

	/**
	 * Supplies malformed successful responses.
	 *
	 * @return iterable<string,array{array<string,string>,string,string}>
	 */
	public static function invalid_success_cases(): iterable {
		yield 'wrong content type' => array( array( 'content-type' => 'text/html' ), '{}', 'invalid_content_type' );
		yield 'invalid JSON' => array( array( 'content-type' => 'application/json' ), '{', 'invalid_json' );
		yield 'invalid schema' => array( array( 'content-type' => 'application/json' ), '{}', 'invalid_schema' );
		yield 'too large' => array( array( 'content-type' => 'application/json' ), str_repeat( 'x', 1048577 ), 'response_too_large' );
	}

	/**
	 * Rejects malformed or oversized successful responses.
	 *
	 * @param array<string,string> $headers Response headers.
	 * @param string               $body    Response body.
	 * @param string               $code    Expected result code.
	 */
	#[DataProvider( 'invalid_success_cases' )]
	public function test_rejects_invalid_success_responses( array $headers, string $body, string $code ): void {
		$transport = new MetadataClientTestTransport( array( new HttpResponse( 200, $headers, $body ) ) );

		$this->assertSame( $code, $this->client( $transport )->fetch( '123', 'auto' )->get_code() );
	}

	/** Rejects a redirect before a private or foreign target can be requested. */
	#[DataProvider( 'unsafe_redirects' )]
	public function test_rejects_unsafe_redirects( string $location ): void {
		$transport = new MetadataClientTestTransport(
			array( new HttpResponse( 302, array( 'location' => $location ), '' ) )
		);

		$this->assertSame( 'unsafe_redirect', $this->client( $transport )->fetch( '123', 'auto' )->get_code() );
		$this->assertCount( 1, $transport->requests );
	}

	/**
	 * Provides redirect targets outside the fixed metadata endpoint.
	 *
	 * @return iterable<string,array{string}>
	 */
	public static function unsafe_redirects(): iterable {
		yield 'loopback' => array( 'http://127.0.0.1/private' );
		yield 'foreign host' => array( 'https://example.org/metadata' );
		yield 'service non-API path' => array( 'https://www.turnierplan.eu/privacy.php' );
		yield 'protocol-relative' => array( '//www.turnierplan.eu/api/embed/v1/tournaments/123/metadata?lang=auto' );
	}

	/** Follows at most two separately validated same-origin metadata redirects. */
	public function test_rechecks_every_allowed_redirect(): void {
		$transport = new MetadataClientTestTransport(
			array(
				new HttpResponse( 302, array( 'location' => '/api/embed/v1/tournaments/456/metadata?lang=auto' ), '' ),
				new HttpResponse( 200, array( 'content-type' => 'application/json' ), $this->metadata_json() ),
			)
		);

		$result = $this->client( $transport )->fetch( '123', 'auto' );

		$this->assertTrue( $result->is_success() );
		$this->assertCount( 2, $transport->requests );
		$this->assertSame( 'https://www.turnierplan.eu/api/embed/v1/tournaments/456/metadata?lang=auto', $transport->requests[1]['url'] );
	}

	/** Creates a client around one fake transport. */
	private function client( HttpTransport $transport ): MetadataClient {
		$service = ServiceConfiguration::production();

		return new MetadataClient(
			new EmbedUrlBuilder( $service ),
			new TrustedMetadataUrl( $service ),
			$transport,
			new MetadataResponseValidator(),
			'0.1.0-test'
		);
	}

	/** Returns the synthetic valid public contract response. */
	private function metadata_json(): string {
		$path = dirname( __DIR__, 2 ) . '/docs/contracts/v1/examples/valid/metadata-success.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local synthetic fixture, not a remote request.
		$json = file_get_contents( $path );

		$this->assertIsString( $json );

		return $json;
	}
}
