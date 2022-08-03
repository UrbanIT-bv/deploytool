<?php
namespace DeployTool;

use phpseclib3\Net\SFTP;

class DeployRemoteConsole
{
	/** @var SFTP $sftp */
	private $sftp;

	/** @var string $username */
	private $username;

	/**
	 * @param SFTP $sftp
	 * @param string $username
	 */
	public function __construct($sftp, $username)
	{
		$this->sftp=$sftp;
		$this->username=$username;
	}

	public function remoteConsole()
	{
		//$this->sftp->enablePTY();
		while(true)
		{
			$cmd=trim(readline($this->username."# "));
			if($cmd=="") continue;
			if($cmd=="exit") break;
			$out=$this->sftp->exec($cmd);
			echo($out);
		}
	}
}
