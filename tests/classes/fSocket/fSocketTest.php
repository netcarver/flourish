<?php
require_once('./support/init.php');
 
class fSocketTest extends PHPUnit_Framework_TestCase
{
	public function setUp()
	{
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
		echo join("\n",$page),"\n";

		unset( $sock );
	}
	
	public function tearDown()
	{
	}
}
