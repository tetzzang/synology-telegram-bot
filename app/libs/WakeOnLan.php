<?php

define('BROADCAST_ADDRESS', '192.168.1.255');
define('WOL_PORT', 9);

/***************************************************************************
 * 사용법
 * $wol = new WakeOnLan();
 * $mac = $wol->checkMac($macstr);
 * if ($mac !== false) 
 *     $result = $wol->wake($mac);
 ***************************************************************************/
class WakeOnLan {

	private function isValidMac($mac) {
		// 01:23:45:67:89:ab
		if (preg_match('/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/', $mac))
			return true;
		// 01-23-45-67-89-ab
		if (preg_match('/^([a-fA-F0-9]{2}\-){5}[a-fA-F0-9]{2}$/', $mac))
			return true;
		// 0123456789ab
		else if (preg_match('/^[a-fA-F0-9]{12}$/', $mac))
			return true;
		// 0123.4567.89ab
		else if (preg_match('/^([a-fA-F0-9]{4}\.){2}[a-fA-F0-9]{4}$/', $mac))
			return true;
		else
			return false;
	}

	private function normalizeMac($mac) {
		// remove any dots
		$mac =  str_replace(".", "", $mac);

		// replace dashes with colons
		$mac =  str_replace("-", ":", $mac);

		// counting colons
		$colon_count = substr_count($mac , ":");

		// insert enough colons if none exist
		if ($colon_count == 0) {
			$mac =  substr_replace($mac, ":", 2, 0);
			$mac =  substr_replace($mac, ":", 5, 0);
			$mac =  substr_replace($mac, ":", 8, 0);
			$mac =  substr_replace($mac, ":", 11, 0);
			$mac =  substr_replace($mac, ":", 14, 0);
		}

		// uppercase
		$mac = strtoupper($mac);

		// DE:AD:BE:EF:10:24
		return $mac;
	}

	public function checkMac($mac) {
		if($this->isValidMac($mac))
			return $this->normalizeMac($mac);
		return false;
	}

	public function wake($mac) {
		if (!$this->checkMac($mac))
			return 'Mac address가 올바르지 않습니다.';

		$macHex = str_replace(':', '', $mac);
		$macBin = pack('H12', $macHex);
		$magicPacket = str_repeat(chr(0xff), 6).str_repeat($macBin, 16);

		$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if (!$socket) {
			$errno = socket_last_error();
			$errstr = socket_strerror($errno);
			return sprintf('Error create socket: %d %s', $errno, $errostr);
		}

		if (!socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, true)) {
			$errno = socket_last_error();
			$errstr = socket_strerror($errno);
			socket_close($socket);
			return sprintf('Error set option socket: %d %s', $errno, $errostr);
		}

		if (!socket_sendto($socket, $magicPacket, strlen($magicPacket), 0, BROADCAST_ADDRESS, WOL_PORT)) {
			$errno = socket_last_error();
			$errstr = socket_strerror($errno);
			socket_close($socket);
			return sprintf('Error send socket: %d %s', $errno, $errostr);
		}

		socket_close($socket);
		return true;
	}
}

?>
