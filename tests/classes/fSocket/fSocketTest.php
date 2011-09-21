<?php
require_once('./support/init.php');
 
class fSocketTest extends PHPUnit_Framework_TestCase
{
	public function setUp()
	{
	}
	
	public function testCreateDestroy()
	{
		$sock = new fSocket( 'www.google.com' , 80 );
		$connected = $sock->isConnected();
		if( $connected )
			throw new Exception( 'Socket should NOT be connected.' );
		unset($sock);
	}
 
	public function testCreateConnectDestroy()
	{
		$sock = new fSocket( 'www.google.com' , 80 );
		$sock->connect();
		$connected = $sock->isConnected();
		if( !$connected )
			throw new Exception( 'Socket should be connected.' );
		unset($sock);
	}

	public function testCreateConnectClose()
	{
		$sock = new fSocket( 'www.google.com' , 80 );
		$sock->connect();
		$sock->close();
		$connected = $sock->isConnected();
		if( $connected )
			throw new Exception( 'Socket should NOT be connected.' );
	}

	public function testCreateConnectCloseDestroy()
	{
		$sock = new fSocket( 'www.google.com' , 80 );
		$sock->connect();
		$sock->close();
		unset($sock);
	}

	public function testCreateConnectMultiCloseDestroy()
	{
		$sock = new fSocket( 'www.google.com' , 80 );
		$sock->connect();
		$sock->close();
		$sock->close();
		$sock->close();
		unset($sock);
	}

	public function testNonSecureHTTP()
	{
		//fSocket::setStrictlySecure( FALSE );

		$sock   = new fSocket( 'www.google.com', 80 );
		$header = "GET /\r\n";
		$expect = strlen( $header );
		$sent   = $sock->connect()->write( $header );
		$this->assertEquals( $expect, $sent );
		//echo "Expecting $expect bytes to be sent. Actually sent $sent bytes.\n";


		$page = $sock->read(105);
		$this->assertNotEquals( $page, '' );
//		echo join("\n",$page),"\n";

		unset( $sock );
	}
 	
	public function tearDown()
	{
	}
}
