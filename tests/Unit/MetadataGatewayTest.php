<?php
/**
 * Tests for metadata cache and approval orchestration.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Cache\CacheStore;
use TurnierplanEU\WordPress\Cache\MetadataCache;
use TurnierplanEU\WordPress\Config\EmbedUrlBuilder;
use TurnierplanEU\WordPress\Config\ServiceConfiguration;
use TurnierplanEU\WordPress\Remote\HttpResponse;
use TurnierplanEU\WordPress\Remote\HttpTransport;
use TurnierplanEU\WordPress\Remote\MetadataClient;
use TurnierplanEU\WordPress\Remote\MetadataGateway;
use TurnierplanEU\WordPress\Remote\MetadataResponseValidator;
use TurnierplanEU\WordPress\Remote\MetadataResult;
use TurnierplanEU\WordPress\Remote\TrustedMetadataUrl;
use TurnierplanEU\WordPress\Remote\TransportFailure;
use TurnierplanEU\WordPress\Settings\ServiceApproval;

/**
 * In-memory store used to observe exact cache-policy effects.
 */
final class MetadataGatewayTestStore implements CacheStore {

	/**
	 * Stored test values.
	 *
	 * @var array<string,mixed>
	 */
	public array $values = array();

	/** Returns one stored value. */
	public function get( string $key ): mixed {
		return $this->values[ $key ] ?? false;
	}

	/** Stores one value; expiry duration is irrelevant to this policy test. */
	public function set( string $key, mixed $value, int $ttl ): void {
		unset( $ttl );
		$this->values[ $key ] = $value;
	}

	/** Deletes one value. */
	public function delete( string $key ): void {
		unset( $this->values[ $key ] );
	}

	/** Deletes every value. */
	public function clear(): void {
		$this->values = array();
	}

	/** Removes current entries while retaining stale successes. */
	public function expire_fresh(): void {
		foreach ( array_keys( $this->values ) as $key ) {
			if ( str_starts_with( $key, 'fresh_' ) ) {
				unset( $this->values[ $key ] );
			}
		}
	}
}

/**
 * Queue-backed transport for gateway tests.
 */
final class MetadataGatewayTestTransport implements HttpTransport {

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
	 * Creates the gateway test transport.
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
 * Fixed approval state for isolated gateway tests.
 */
final class MetadataGatewayTestApproval implements ServiceApproval {

	/** Creates one approval state. */
	public function __construct( private readonly bool $enabled ) {
	}

	/** Returns the configured approval state. */
	public function is_service_enabled(): bool {
		return $this->enabled;
	}
}

/**
 * Verifies no-request approval, ETags, stale fallback, and authoritative 404s.
 */
final class MetadataGatewayTest extends TestCase {

	/** Disabled service approval prevents all transport activity. */
	public function test_approval_is_required_before_any_request(): void {
		$transport = new MetadataGatewayTestTransport( array() );
		$gateway   = $this->gateway( false, new MetadataGatewayTestStore(), $transport );

		$this->assertSame( 'service_not_enabled', $gateway->get( '123' )->get_code() );
		$this->assertCount( 0, $transport->requests );
	}

	/** A successful response is served from cache on the next request. */
	public function test_success_is_cached_without_second_remote_request(): void {
		$transport = new MetadataGatewayTestTransport(
			array(
				new HttpResponse(
					200,
					array(
						'content-type' => 'application/json',
						'etag'         => '"one"',
					),
					$this->metadata_json()
				),
			)
		);
		$gateway   = $this->gateway( true, new MetadataGatewayTestStore(), $transport );

		$this->assertSame( 'remote', $gateway->get( '123', 'de' )->get_source() );
		$this->assertSame( 'cache', $gateway->get( '123', 'de' )->get_source() );
		$this->assertCount( 1, $transport->requests );
	}

	/** A 304 renews the last validated response rather than returning empty data. */
	public function test_not_modified_revalidates_stale_success(): void {
		$store     = new MetadataGatewayTestStore();
		$transport = new MetadataGatewayTestTransport(
			array(
				new HttpResponse(
					200,
					array(
						'content-type' => 'application/json',
						'etag'         => '"one"',
					),
					$this->metadata_json()
				),
				new HttpResponse( 304, array(), '' ),
			)
		);
		$gateway   = $this->gateway( true, $store, $transport );

		$gateway->get( '123', 'de' );
		$store->expire_fresh();
		$result = $gateway->get( '123', 'de' );

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 'cache', $result->get_source() );
		$this->assertSame( '"one"', $transport->requests[1]['args']['headers']['If-None-Match'] );
	}

	/** Transport and 503 failures may use the last success with an explicit stale state. */
	public function test_temporary_failure_uses_stale_success(): void {
		$store     = new MetadataGatewayTestStore();
		$transport = new MetadataGatewayTestTransport(
			array(
				new HttpResponse( 200, array( 'content-type' => 'application/json' ), $this->metadata_json() ),
				new HttpResponse( 503, array(), '{}' ),
			)
		);
		$gateway   = $this->gateway( true, $store, $transport );

		$gateway->get( '123', 'de' );
		$store->expire_fresh();

		$this->assertSame( 'stale', $gateway->get( '123', 'de' )->get_source() );
	}

	/** An authoritative 404 removes stale success and is negatively cached. */
	public function test_not_found_never_falls_back_to_old_success(): void {
		$store     = new MetadataGatewayTestStore();
		$transport = new MetadataGatewayTestTransport(
			array(
				new HttpResponse( 200, array( 'content-type' => 'application/json' ), $this->metadata_json() ),
				new HttpResponse( 404, array( 'content-type' => 'application/json' ), '{}' ),
			)
		);
		$gateway   = $this->gateway( true, $store, $transport );

		$gateway->get( '123', 'de' );
		$result = $gateway->get( '123', 'de', true );

		$this->assertSame( 'tournament_not_found', $result->get_code() );
		$this->assertSame( 'tournament_not_found', $gateway->get( '123', 'de' )->get_code() );
		$this->assertCount( 2, $transport->requests );

		foreach ( array_keys( $store->values ) as $key ) {
			$this->assertFalse( str_starts_with( $key, 'stale_' ) );
		}
	}

	/** Revocation cache clearing removes every exactly tracked entry. */
	public function test_cache_can_be_cleared_on_revocation(): void {
		$store     = new MetadataGatewayTestStore();
		$validator = new MetadataResponseValidator();
		$cache     = new MetadataCache( $store, $validator );
		$metadata  = json_decode( $this->metadata_json(), true, 64, JSON_THROW_ON_ERROR );

		$this->assertIsArray( $metadata );
		$cache->save_success( '123', 'de', MetadataResult::success( $metadata, null ) );
		$this->assertNotEmpty( $store->values );

		$cache->clear();
		$this->assertSame( array(), $store->values );
	}

	/** Creates a complete gateway with fake approval, storage, and transport. */
	private function gateway( bool $enabled, MetadataGatewayTestStore $store, MetadataGatewayTestTransport $transport ): MetadataGateway {
		$service   = ServiceConfiguration::production();
		$validator = new MetadataResponseValidator();
		$cache     = new MetadataCache( $store, $validator );
		$client    = new MetadataClient(
			new EmbedUrlBuilder( $service ),
			new TrustedMetadataUrl( $service ),
			$transport,
			$validator,
			'0.1.0-test'
		);

		return new MetadataGateway( new MetadataGatewayTestApproval( $enabled ), $cache, $client );
	}

	/** Returns a valid synthetic contract response. */
	private function metadata_json(): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local synthetic fixture, not a remote request.
		$json = file_get_contents( dirname( __DIR__, 2 ) . '/docs/contracts/v1/examples/valid/metadata-success.json' );

		$this->assertIsString( $json );

		return $json;
	}
}
