<?php
// **************************************
// Project: ChinaNet Dialer
// Version: 0.2.4 [PHP5]
// Date:    2014年8月14日
// Modify:	2014年9月16日
// Design:  Pekaikon Norckon
// Website: http://www.fcsys.us/
// **************************************

$__VERSION__  = '0.2.4';
$__FILENAME__ = $_SERVER['PHP_SELF'];

// ************* CONFIG *****************
// Telecom Dialer Server IP Address
$DialServer = '61.186.95.108';
// Network Detector Cycle [msec]
$NetDetectCycle = 1000;
$NetSleepTime = 100;
// NCSI Server
$NCSIServer = "http://www.msftncsi.com/ncsi.txt";
$NCSIBackup = "http://ru.fcsys.tk/ncsi.txt";
// IP Address Requestion Server
$IPServer = "http://www.fcsys.us/webapp/ip.php";
// **************************************

// Initizing PHP Settings
date_default_timezone_set("PRC");
set_time_limit(0);
error_reporting(0);
$isDetectedNoNetwork = 0;
$redialedTime = 0;

// CURL Function
function FCurl($url) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT,10);
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}

// Get IP Address
function GetIP() {
	global $IPServer;
	$ipdoc = FCurl("$IPServer");
	return $ipdoc;
}

// Dial-up Funciton
function DialUp($username, $rsakey) {
	global $DialServer;
	// Output account information
	FLog("Preparing for dial-up ...", 0);
	FLog("Username=$username and RSAKey=".substr($rsakey,0,4)."...".substr($rsakey,-5,-1), 0);
	// Send requestion to server
	FLog("Dialing, Please wait ...", 0);
	$dialStatus = FCurl("http://$DialServer/portal4HN/PhoneUserLogin?phoneNumber=$username&phonePassword=$rsakey&checkCode=&basIp=&checkCode=&intranetIp=&time=".time());
	// Result check
	if(strpos($dialStatus, "0#-1#0#") > -1) {
		FLog("Success! Connected to Internet now", 0);
		$ipaddr = GetIP();
		FLog("Internet IPv4 Address: $ipaddr", 0);
		// When dialed success, waiting for DNS work
		sleep(180);
	}
	else if(strpos($dialStatus, "1#-1#0#") > -1) {
		FLog("Wrong username or RSA Key!", 3);
	}
	else if(strpos($dialStatus, "2#-1#0#") > -1) {
		FLog("You has been connected to Internet", 2);
		// If really connected to Internet waiting 70 sec.
		sleep(70);
	}
	else if(strpos($dialStatus, "-10") > -1) {
		FLog("Your account has a problem", 3);
	}
	else {
		FLog("Network problem, please check your network!",4);
	}
}

// Disconnect
function Disconnect() {
	global $DialServer;
	// Print Header
	FVer(0);
	// Get Current IP
	$ipaddr = GetIP();
	// Send requestion to Server
	FCurl("http://$DialServer/portal4HN/CloseNet?userIp=$ipaddr&basIp=&intranetIp=");
	FLog("Disconnected from ChinaNet! Byebye!", 0);
	die();
}

// Watchdog
function Watchdog() {
	global $NCSIServer,$DialServer,$NetDetectCycle,$isDetectedNoNetwork,$redialedTime;
	while(true) {
		$i_c = 0;
		$c_c = 0;
		for($i=0; $i<5; $i++) {
			$c_status = FCurl("http://$DialServer");
			$i_status = FCurl($NCSIServer);
			if(strpos($c_status, "portal4HN") > -1)      $c_c++;
			if(strpos($i_status, "Microsoft NCSI") > -1) $i_c++;
			// wait 
			usleep($NetSleepTime*1000);
		}
		// If Main NCSI Server Failed then use Backup NCSI server
		if($i_c==0) {
			for($i=0; $i<3; $i++) {
				$b_status = FCurl($NCSIBackup);
				if(strpos($b_status, "FC-System NCSI") > -1) $i_c++;
			}
		}
		
		// not logged in
		if($i_c==0&&$c_c>=1)  {
			FLog("You are not logged in, Redialing ...", 3);
			// Redialed 5 times then relay 10 sec
			if($redialedTime >= 5) sleep(10);
			// Redialed 5 times then relay 20 sec
			if($redialedTime >= 8) sleep(10);
			// Redialed 10 times then relay more 100 sec, total 120 sec
			if($redialedTime >= 10) sleep(100);
			// Redialed 15 times then exit program
			if($redialedTime > 15) {
				FLog("Your account has a problem", 4);
				die();
			}
			// Redial Time plus
			$redialedTime++;
			break;
		}
		// no network access
		else if($i_c==0&&$c_c==0)  {
			if($isDetectedNoNetwork == 0) {
				FLog("No Network Access! waiting ...", 4);
				// Set donot log next time
				$isDetectedNoNetwork = 1;
			}
		}
		// internet
		else if($i_c>=1) {
			// Reset Flags
			$isDetectedNoNetwork = 0;
			$redialedTime = 0;
		}
		
		// Another Cycle
		usleep($NetDetectCycle*1000);
	}
}

// Output Log
function FLog($text, $class) {
	switch($class) {
		case 0:
			$status = "INF";
			break;
		case 1:
			$status = "DBG";
			break;
		case 2:
			$status = "WRN";
			break;
		case 3:
			$status = "ERR";
			break;
		case 4:
			$status = "FAL";
			break;
	}
	$msg = "[".date("y-m-d H:i:s")." $status] $text\n";
	echo $msg;
	// For Linux or Windows
	if(file_exists("/proc/cpuinfo")) {
		// Linux
		//  If log filesize greater than 512k then rewrite.
		if(filesize("/tmp/chinanet.log")>524288) {
			$fp_log = fopen("/tmp/chinanet.log","w");
		} else {
			$fp_log = fopen("/tmp/chinanet.log","a+");
		}
		fputs($fp_log, $msg);
		fclose($fp_log);
	} else {
		// Windows
		//  If log filesize greater than 512k then rewrite.
		if(filesize("/tmp/chinanet.log")>524288) {
			$fp_log = fopen(dirname(__FILE__)."/chinanet.log","w");
		} else {
			$fp_log = fopen(dirname(__FILE__)."/chinanet.log","a+");
		}
		fputs($fp_log, $msg);
		fclose($fp_log);
	}
}

// Print Usage / Version
function FVer($mode) {
	global $__FILENAME__,$__VERSION__;
	$usage  = "\n";
	$usage .= "ChinaNet Dialer (version:$__VERSION__)\n";
	$usage .= "by Pekaikon Norckon [website:http://www.fcsys.us]\n";
	$usage .= "\n";
	if($mode==1) {
		$usage .= "Usage: php $__FILENAME__ [-u username] [-r rsakey] [-d] [-v] [-h]\n";
		$usage .= "\n";
		$usage .= "Option:\n";
		$usage .= "    -u      Your ChinaNet Username\n";
		$usage .= "    -r      Your ChinaNet RSA Key\n";
		$usage .= "    -d      Disconnect from ChinaNet\n";
		$usage .= "    -v      Show version information\n";
		$usage .= "    -h      Show this information\n";
		$usage .= "\n";
		$usage .= "Example:\n";
		$usage .= "         Dial:   php $__FILENAME__ -u 18912345678 -r 2d8e...039d\n";
		$usage .= "    Disconnet:   php $__FILENAME__ -d\n";
		$usage .= "\n";
		$usage .= "RSA Key Generate:\n";
		$usage .= "    Use Firefox or Chrome to open pw2rsa.htm\n";
		$usage .= "\n";
		$usage .= "\n";
	}
	echo $usage;
}

// Main Proccess
function FMain() {
	global $argc,$argv,$__VERSION__,$__FILENAME__;
	// If run program without any parameters show usage
	if($argc === 1) {
		FVer(1);
		die();
	} else { //ELSE
		// Initize
		$arrpos_u = 0;
		$arrpos_r = 0;
		$arrpos_d = 0;
		$arrpos_v = 0;
		$arrpos_h = 0;
		// Found parameters
		for($i=1; $i<$argc; $i++) {	
			if($argv[$i] == "-u") $arrpos_u = $i;
			if($argv[$i] == "-r") $arrpos_r = $i;
			if($argv[$i] == "-d") $arrpos_d = $i;
			if($argv[$i] == "-v") $arrpos_v = $i;
			if($argv[$i] == "-h") $arrpos_h = $i;
		}
		// Show version and help first
		if($arrpos_h > 0) {
			FVer(1);
			die();
		}
		if($arrpos_v > 0) {
			FVer(0);
			die();
		}
		// Check parameters
		if($arrpos_d == 0) {
			if($arrpos_u == 0 || $arrpos_r == 0) {
				FVer(1);
				die();
			}
		} else {
			if($arrpos_u == 1 || $arrpos_r == 1) {
				FVer(1);
				die();
			}
		}
		// Disconnect First
		if($arrpos_d > 0) {
			Disconnect();
			die();
		}
		// Get Username and Password
		$username = $argv[$arrpos_u+1];
		$rsakey = $argv[$arrpos_r+1];
		
		// Print Version
		FVer(0);
		// Dialing and Watchdog
		while(true) {
			// Start Dial
			DialUp($username, $rsakey);
			// Watchdog
			Watchdog();
		}
	}
}

FMain();
?>
