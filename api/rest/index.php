<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A webservice interface to Mantis Bug Tracker
 *
 * @package MantisBT
 * @copyright Copyright 2004  Victor Boctor - vboctor@users.sourceforge.net
 * @copyright Copyright 2005  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 */

use Mantis\Exceptions\StateException;
use Slim\App;
use Slim\Container;

# Bypass default Mantis headers
$g_bypass_headers = true;
$g_bypass_error_handler = true;

require_once( __DIR__ . '/../../vendor/autoload.php' );
require_once( __DIR__ . '/../../core.php' );
require_once( __DIR__ . '/../soap/mc_core.php' );

$t_restcore_dir = __DIR__ . '/restcore/';

require_once( $t_restcore_dir . 'ApiEnabledMiddleware.php' );
require_once( $t_restcore_dir . 'AuthMiddleware.php' );
require_once( $t_restcore_dir . 'CacheMiddleware.php' );
require_once( $t_restcore_dir . 'OfflineMiddleware.php' );
require_once( $t_restcore_dir . 'VersionMiddleware.php' );

# Hint to re-used mantisconnect code that it is being executed from REST rather than SOAP.
# For example, this will disable logic like encoding dates with XSD meta-data.
ApiObjectFactory::$soap = false;

$t_config = array();

# Show SLIM detailed errors according to Mantis settings
if( ON == config_get_global( 'show_detailed_errors' ) ) {
	$t_config['settings'] = array( 'displayErrorDetails' => true );
}

# For debugging purposes, uncomment this line to avoid truncated error messages
# $t_config['settings']['addContentLengthHeader'] = false;

# Disable E_DEPRECATED warnings for PHP 8.4
# Necessary to catch errors in method signatures triggered before our custom
# error handler is in place.
if( version_compare( PHP_VERSION, '8.4', '>=' ) ) {
	$t_old_error_reporting = error_reporting( error_reporting() & ~E_DEPRECATED );
}

if( version_compare( Slim\App::VERSION, '4.0', '<' )
	&& version_compare( PHP_VERSION, '8.1', '>=' )
) {
	/**
	 * Error Handler to process Slim 3.x deprecated warnings on PHP 8.1+.
	 *
	 * Some components required by Slim Framework 3.x throw deprecation warnings
	 * on PHP 8.1 and later. Depending on PHP error_reporting() settings, these
	 * cause the REST API to fail, while still returning an HTTP 200 status code
	 * (the response body contains HTML with details about the errors).
	 *
	 * To properly fix this, we need to upgrade to Slim 4.x (no small undertaking,
	 * covered in #31699), or maintain our own fork of the offending components
	 * (which is a pain).
	 *
	 * So as a workaround, we define a custom error handler selectively squelch the
	 * known deprecation notices, and throw an exception for any other warning.
	 *
	 * It's worth noting that Slim's documented way of handling PHP errors
	 * {@see https://www.slimframework.com/docs/v3/handlers/php-error.html}
	 * is not able to catch these warnings, as some of them occur before the error
	 * handling container becomes active (while it's being initialized, in fact).
	 *
	 * @throws StateException
	 */
	function deprecated_errors_handler(
		int    $p_type,
		string $p_error,
		string $p_file,
		int    $p_line
	): bool {
		if( is_windows_server() ) {
			# Convert to Unix-style path
			$p_file = str_replace( '\\', '/', $p_file );
		}

		if( preg_match( '~/vendor/(?:\w+/){2}(.*)$~', $p_file, $t_matches ) ) {
			# Selectively handle deprecation warnings
			switch( $t_matches[1] ) {
				case 'src/Pimple/Container.php':
				case 'Slim/Collection.php':
					if( strpos( $p_error, '#[\ReturnTypeWillChange]' ) ) {
						return true;
					}
					break;

				# Passing null to parameter of type ... is deprecated
				case 'Slim/Http/Request.php':
				case 'Slim/Http/Uri.php':

				# Implicitly marking parameter ... as nullable is deprecated
				case 'Slim/DeferredCallable.php':
				case 'Slim/Router.php':
				case 'Slim/RouteGroup.php':
				case 'Slim/Http/Response.php':
					return true;
			}
		}

		# For any other, unknown warnings, throw an exception
		throw new StateException(
			sprintf( "Unhandled deprecation warning in %s line %d: '%s'",
				$p_file,
				$p_line,
				$p_error
			),
			ERROR_GENERIC
		);
	}

	set_error_handler( 'deprecated_errors_handler', E_DEPRECATED );
}

$t_container = new Container( $t_config );
$t_container['errorHandler'] = function( $p_container ) {
	return function( $p_request, $p_response, $p_exception ) use ( $p_container ) {
		$t_data = array(
			'message' => $p_exception->getMessage(),
		);

		if( is_a( $p_exception, 'Mantis\Exceptions\MantisException' ) ) {
			global $g_error_parameters;
			$g_error_parameters =  $p_exception->getParams();
			$t_data['code'] = $p_exception->getCode();
			$t_data['localized'] = error_string( $p_exception->getCode() );

			$t_result = ApiObjectFactory::faultFromException( $p_exception );
			return $p_response->withStatus( $t_result->status_code, $t_result->fault_string )->withJson( $t_data );
		}

		if( is_a( $p_exception, 'Mantis\Exceptions\LegacyApiFaultException' ) ) {
			return $p_response->withStatus( $p_exception->getCode(), $p_exception->getMessage() )->withJson( $t_data );
		}

		$t_stack_as_string = error_stack_trace_as_string( $p_exception );
		$t_error_to_log =  $p_exception->getMessage() . "\n" . $t_stack_as_string;
		error_log( $t_error_to_log );

		$t_settings = $p_container->get('settings');
		if( $t_settings['displayErrorDetails'] ) {
			$p_response = $p_response->withJson( $t_data );
		}

		return $p_response->withStatus( HTTP_STATUS_INTERNAL_SERVER_ERROR );
	};
};


# Wrap the whole API initialization and execution in a try/catch block, to
# ensure we capture every error that could be occurring.
try {
	$g_app = new App( $t_container );

	# Add middleware - executed in reverse order of appearing here.
	$g_app->add( new ApiEnabledMiddleware() );
	$g_app->add( new AuthMiddleware() );
	$g_app->add( new VersionMiddleware() );
	$g_app->add( new OfflineMiddleware() );
	$g_app->add( new CacheMiddleware() );

	require_once( $t_restcore_dir . 'config_rest.php' );
	require_once( $t_restcore_dir . 'filters_rest.php' );
	require_once( $t_restcore_dir . 'internal_rest.php' );
	require_once( $t_restcore_dir . 'issues_rest.php' );
	require_once( $t_restcore_dir . 'lang_rest.php' );
	require_once( $t_restcore_dir . 'projects_rest.php' );
	require_once( $t_restcore_dir . 'users_rest.php' );
	require_once( $t_restcore_dir . 'pages_rest.php' );

	event_signal( 'EVENT_REST_API_ROUTES', array( array( 'app' => $g_app ) ) );

	# Restore error reporting to its original state before executing the request
	if( isset( $t_old_error_reporting ) ) {
		error_reporting( $t_old_error_reporting );
	}
	
	$g_app->run();
}
catch( Throwable $e ) {
	header( 'Content-type: text/plain');
	http_response_code( HTTP_STATUS_INTERNAL_SERVER_ERROR );
	echo $e;
}
