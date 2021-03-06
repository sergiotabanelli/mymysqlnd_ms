--TEST--
lazy connections and autocommit
--SKIPIF--
<?php
require_once('skipif.inc');
require_once("connect.inc");

if (version_compare(PHP_VERSION, '5.3.99') < 0)
	die(sprintf("SKIP Requires PHP > 5.4.0, using " . PHP_VERSION));

_skipif_check_extensions(array("mysqli"));
_skipif_connect($master_host_only, $user, $passwd, $db, $master_port, $master_socket);
_skipif_connect($slave_host_only, $user, $passwd, $db, $slave_port, $slave_socket);

$settings = array(
	"myapp" => array(
		'master' => array(
			"master1" => array(
				'host' 		=> $master_host_only,
				'port' 		=> (int)$master_port,
				'socket' 	=> $master_socket,
			),
		),

		'slave' => array(
			"slave1" => array(
				'host' 	=> $slave_host_only,
				'port' 	=> (int)$slave_port,
				'socket' => $slave_socket,
			),
		 ),

		'lazy_connections' => 1,
		'filters' => array(
			"random" => array('sticky' => '1'),
		),
	),

);
if ($error = mst_create_config("test_mysqlnd_ms_lazy_autocommit.ini", $settings))
	die(sprintf("SKIP %s\n", $error));
?>
--INI--
mysqlnd_ms.enable=1
mysqlnd_ms.config_file=test_mysqlnd_ms_lazy_autocommit.ini
--FILE--
<?php
	require_once("connect.inc");
	require_once("util.inc");

	function get_autocommit_setting($offset, $link, $hint = NULL) {
		$res = mst_mysqli_query($offset, $link, "SELECT @@autocommit AS auto_commit", $hint);
		$row = $res->fetch_assoc();
		return $row['auto_commit'];
	}

	/* intentionally no MS connection - looking for default */
	$link = mst_mysqli_connect($master_host_only, $user, $passwd, $db, $master_port, $master_socket);
	if (mysqli_connect_errno()) {
		printf("[001] [%d] %s\n", mysqli_connect_errno(), mysqli_connect_error());
	}
	$master_default = get_autocommit_setting(2, $link);
	$link->close();

	/* now MS */
	$link = mst_mysqli_connect("myapp", $user, $passwd, $db, $port, $socket);
	if (mysqli_connect_errno()) {
		printf("[001] [%d] %s\n", mysqli_connect_errno(), mysqli_connect_error());
	}

	/* establish slave connection */
	if ($res = mst_mysqli_query(2, $link, "SELECT 1 FROM DUAL"))
		var_dump($res->fetch_assoc());

	if (!mysqli_autocommit($link, !$master_default))
		printf("[003] Failed to change autocommit setting\n");

	if ($master_default == ($tmp = get_autocommit_setting(4, $link)))
		printf("[005] Autocommit should be %d, got %d\n", !$master_default, $tmp);

	if ($master_default == ($tmp = get_autocommit_setting(6, $link, MYSQLND_MS_MASTER_SWITCH)))
		printf("[007] Autocommit should be %d, got %d\n", !$master_default, $tmp);

	$link->close();

	$link = mst_mysqli_connect("myapp", $user, $passwd, $db, $port, $socket);
	if (mysqli_connect_errno()) {
		printf("[008] [%d] %s\n", mysqli_connect_errno(), mysqli_connect_error());
	}

	/* establish master connection */
	if ($res = mst_mysqli_query(9, $link, "SELECT 1 FROM DUAL", MYSQLND_MS_MASTER_SWITCH))
		var_dump($res->fetch_assoc());

	if (!mysqli_autocommit($link, !$master_default))
		printf("[010] Failed to change autocommit setting\n");

	if ($master_default == ($tmp = get_autocommit_setting(11, $link)))
		printf("[012] Autocommit should be %d, got %d\n", !$master_default, $tmp);

	if ($master_default == ($tmp = get_autocommit_setting(13, $link, MYSQLND_MS_MASTER_SWITCH)))
		printf("[014] Autocommit should be %d, got %d\n", !$master_default, $tmp);

	$link->close();


	print "done!";
?>
--CLEAN--
<?php
	require_once("connect.inc");

	if (!unlink("test_mysqlnd_ms_lazy_autocommit.ini"))
	  printf("[clean] Cannot unlink ini file 'test_mysqlnd_ms_lazy_autocommit.ini'.\n");
?>
--EXPECTF--
array(1) {
  [1]=>
  string(1) "1"
}
array(1) {
  [1]=>
  string(1) "1"
}
done!