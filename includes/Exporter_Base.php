<?php
/**
 * Abstract Exporter Base class.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */

namespace UDAuditManager\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Exporter_Base
 *
 * Abstract foundation for custom exporter formats such as CSV, JSON, etc.
 *
 * @package UDAuditManager\Includes
 * @since 1.0.0
 */
abstract class Exporter_Base {

	/**
	 * Retrieve exporter name.
	 *
	 * @return string The format name.
	 */
	abstract public function get_name() : string;

	/**
	 * Retrieve exporter content mime type.
	 *
	 * @return string The HTTP mime type.
	 */
	abstract public function get_mime_type() : string;

	/**
	 * Execute export and print/stream download output.
	 *
	 * @param int $run_id Audit run ID.
	 * @return void
	 */
	abstract public function export( int $run_id ) : void;
}
