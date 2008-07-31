<?php
	function kc_createKeychain($keychain)
	{
		$keychain=escapeshellarg($keychain);
		system("security unlock-keychain -p stupid $keychain 2> /dev/null");
		system("security delete-keychain $keychain 2> /dev/null");
		system("security create-keychain -p stupid $keychain 2> /dev/null");
	}

	function kc_addPassword($keychain,$username,$password)
	{
		$keychain=escapeshellarg($keychain);
		$username=escapeshellarg($username);
		$password=escapeshellarg($password);
		system("security add-generic-password -a $username -p $password $keychain");
	}

	function kc_getPassword($keychain,$username)
	{
		$keychain=escapeshellarg($keychain);
		$username=escapeshellarg($username);

		system("security unlock-keychain -p stupid $keychain 2> /dev/null");
		$line=`security find-generic-password -g -a $username $keychain 2>&1 > /dev/null`;
		$line=ereg_replace('^[^"]*"|"$',"",trim($line));
		return $line;
	}
?>
