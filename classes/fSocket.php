<?php
/**
 * An abstraction for socket connections implemented using PHPs network functions.
 *
 * @copyright  Copyright (c) 2010-2011 Will Bond, netcarver
 * @author     Will Bond [wb] 
 * @author     netcarver [n] <fContrib@netcarving.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fSocket
 * 
 * @version    1.0.0b
 * @changes    1.0.0b    The initial implementation [n, 2011-09-20] based on fSMTP by Will Bond.
 */
class fSocket
{
	/**
	 * Possible requireXYZ() candidates for fCore?
	 *
	 * These all check a condition and throw an fProgrammerException exception on condition failure.
	 **/
	static public function requireBool( $param, $name )
	{
		if( !is_bool($param) ) {
			throw new fProgrammerException( "Parameter \$$name must be a bool." );
		}
	}

	static public function requireInt( $param, $name )
	{
		if( !is_int($param) ) {
			throw new fProgrammerException( "Parameter \$$name must be an integer." );
		}
	}

	static public function requireNumeric( $param, $name )
	{
		if( !is_numeric($param) ) {
			throw new fProgrammerException( "Parameter \$$name must be numeric." );
		}
	}

	static public function requireNonEmptyString( $param, $name )
	{
		if( !is_string($param) || empty($param) ) {
			throw new fProgrammerException( "Parameter \$$name must be a non-empty string." );
		}
	}

	static public function requireNotFalse( $var, $msg )
	{
		if( !$var ) {
			throw new fProgrammerException( $msg );
		}
	}




	/**
	 * Strictly secure flag. Determines behavior if secure socket requested but unsupported. 
	 * Set this to true and an fConnectivityException will be thrown if 
	 * secure sockets are requested but unsupported.
	 */
	static $strictly_secure = FALSE;

	static public function setStrictlySecure( $strict )
	{
		self::requireBool( $strict, 'strict' );
		self::$strictly_secure = $strict;
	}



	/**
	 * The connection.
	 *
	 * @var Resource
	 */
	protected $connection = FALSE;

	/**
	 * The port the server is on
	 * 
	 * @var integer
	 */
	private $port;
	
	/**
	 * If the connection to the server is secure
	 * 
	 * @var boolean
	 */
	private $secure;
	
	/**
	 * The timeout for the connection
	 * 
	 * @var integer
	 */
	private $timeout;

	/**
	 * The hostname or IP of the server
	 * 
	 * @var string
	 */
	private $host;
	


	/**
	 * Instance constructor. Creates an unconnected fSocket to the given host,port 
	 */
	public function __construct( $host, $port, $secure = FALSE, $timeout = NULL )
	{
		self::requireNonEmptyString( $host, 'host' );
		$this->host    = $host;

		self::requireNumeric( $port, 'port' );
		$this->port    = (int)$port;

		if ($timeout === NULL) {
			$timeout = ini_get('default_socket_timeout');
		}
		$this->timeout = $timeout;

		self::requireBool( $secure, 'secure' );
		if( $secure && self::$strictly_secure && !extension_loaded('openssl') ) {
			throw new fConnectivityException( 'Cannot honour strictly secure connection requests.' );
		}
		$secure = $secure && extension_loaded('openssl');
		$this->secure  = $secure;
	}


	public function __destruct()
	{
		$this->close();
	}


	/**
	 * Sets the cryptogrphic state of the socket
	 **/
	public function setCrypto( $state , $flags )
	{
		self::requireNotFalse( $this->connection, 'Call connect() on the socket first.' );

		$res = stream_socket_enable_crypto($this->connection, $state, $flags );
		return $res;
	}

	/**
	 * Get the connection's secure flag.
	 *
	 * @return Bool True if the connection was started securely. False otherwise. 
	 */
	public function getSecure()
	{
		self::requireNotFalse( $this->connection, 'Call connect() on the socket first.' );
		return $this->secure;
	}

	public function getHost()
	{
		self::requireNotFalse( $this->connection, 'Call connect() on the socket first.' );
		return $this->host;
	}

	public function getPort()
	{
		self::requireNotFalse( $this->connection, 'Call connect() on the socket first.' );
		return $this->port;
	}

	public function getTimeout()
	{
		self::requireNotFalse( $this->connection, 'Call connect() on the socket first.' );
		return $this->timeout;
	}

	public function isConnected()
	{
		return (FALSE !== $this->connection);
	}

	/**
	 * Opens a socket connection.
	 */
	public function connect()
	{
//		echo __METHOD__,"\n";
		if( !$this->connection ) {
			fCore::startErrorCapture(E_WARNING);

			$this->connection = fsockopen(
				$this->secure ? 'tls://' . $this->host : $this->host,
				$this->port,
				$error_number,
				$error_string,
				$this->timeout
			);

			foreach (fCore::stopErrorCapture('#ssl#i') as $error) {
				throw new fConnectivityException('There was an error connecting the socket. A secure connection was requested, but was not available. Try a non-secure connection instead.');
			}

			if (!$this->connection) {
				throw new fConnectivityException('There was an error connecting the socket. Error number ['.$error_number.'], message ['.$error_string.'].' );
			}

			stream_set_timeout($this->connection, $this->timeout);
		}

		return $this;
	}



	/**
	 * Performs a "fixed" stream_select() for the connection
	 * 
	 * @param integer $timeout   The number of seconds in the timeout
	 * @param integer $utimeout  The number of microseconds in the timeout
	 * @return boolean|string  TRUE (or a character) is the connection is ready to be read from, FALSE if not
	 */
	private function select($timeout, $utimeout)
	{
		$read     = array($this->connection);
		$write    = NULL;
		$except   = NULL;
		
		// PHP 5.2.0 to 5.2.5 had a bug on amd64 linux where stream_select()
		// fails, so we have to fake it - http://bugs.php.net/bug.php?id=42682
		static $broken_select = NULL;
		if ($broken_select === NULL) {
			$broken_select = strpos(php_uname('m'), '64') !== FALSE && fCore::checkVersion('5.2.0') && !fCore::checkVersion('5.2.6');
		}
		
		// Fixes an issue with stream_select throwing a warning on PHP 5.3 on Windows
		if (fCore::checkOS('windows') && fCore::checkVersion('5.3.0')) {
			$select = @stream_select($read, $write, $except, $timeout, $utimeout);
		
		} elseif ($broken_select) {
			$broken_select_buffer = NULL;
			$start_time = microtime(TRUE);
			$i = 0;
			do {
				if ($i) {
					usleep(50000);
				}
				$char = fgetc($this->connection);
				if ($char != "\x00" && $char !== FALSE) {
					$broken_select_buffer = $char;
				}
				$i++;
				if ($i > 2) {
					break;
				}
			} while ($broken_select_buffer === NULL && microtime(TRUE) - $start_time < ($timeout + ($utimeout/1000000)));
			$select = $broken_select_buffer === NULL ? FALSE : $broken_select_buffer;
			
		} else {
			$select = stream_select($read, $write, $except, $timeout, $utimeout);
		}
		
		return $select;
	}



	// TODO make this non-line oriented?? -- individual protocols may expect EOL as \r\n etc but this underlying abstraction should probably treat as a blob.
	public function read( $expect )
	{
		self::requireNotFalse( $this->connection, 'Call connect() on the socket first.' );

		$response = array();
		if ($result = $this->select($this->timeout, 0)) {
			while (!feof($this->connection)) {
				$line = fgets($this->connection);
				if ($line === FALSE) {
					break;
				}
				$line = substr($line, 0, -2);
				if (is_string($result)) {
					$line = $result . $line;
				}
				$response[] = $line;
				if ($expect !== NULL) {
					$result = NULL;
					$matched_number = is_int($expect) && sizeof($response) == $expect;
					$matched_regex  = is_string($expect) && preg_match($expect, $response[sizeof($response)-1]);
					if ($matched_number || $matched_regex) {
						break;
					}
				} elseif (!($result = $this->select(0, 200000))) {
					break;
				}
			}
		}
		$this->debug = FALSE;
		if (fCore::getDebug($this->debug)) {
			fCore::debug("Received:\n" . join("\r\n", $response), $this->debug);
		}
		return $response;
	}



	/**
	 *	Writes the given data to the socket. 
	 *
	 * @return The number of bytes written.
	 */
	public function write( $data )
	{
		self::requireNotFalse( $this->connection, 'Call connect() on the socket first.' );
		self::requireNonEmptyString( $data, 'data' );

		$remaining = strlen(utf8_decode($data));
		$total     = 0;

		do {
			$sent = fwrite($this->connection, substr($data, $total));
			if ($sent === FALSE || $sent === 0) {
				throw new fConnectivityException(
					'Unable to write data to server at %2$s on port %3$s',
					$this->host,
					$this->port
				);
			}
			$remaining -= $sent;
			$total     += $sent;
		} while( $remaining > 0 );

		return $total;
	}


	
	public function close()
	{
		if (FALSE !== $this->connection) {
			fclose( $this->connection );
			$this->connection = FALSE;
		}
	}
}

/**
 * Copyright (c) 2010-2011 Will Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
