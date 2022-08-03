<?php
namespace DeployTool;

use phpseclib3\Net\SFTP;

class DeployTool
{
	/** @var string $deployInfoPath */
	private $deployInfoPath;

	/** @var SFTP $sftp */
	private $sftp;

	/** @var DeployFileSyncer $deployFileSyncer */
	private $deployFileSyncer;

	/** @var DeployRemoteConsole $remoteConsole */
	private $remoteConsole;

	/**
	 * @param string $deployInfoPath
	 * @param string $profile
	 */
	public function __construct($deployInfoPath, $profile="default")
	{
		echo(DeployOutput::success("Profile: ".$profile)."\n");

		// keep params
		$this->deployInfoPath=$deployInfoPath;

		// load deployconfig file
		$deployConfigFull=require_once($this->deployInfoPath."deployconfig.php");

		// get & combine profile config
		if($profile==="default")
		{
			$deployConfig=$deployConfigFull[$profile];
		}
		else
		{
			if(!isset($deployConfigFull[$profile])) die(DeployOutput::error("Onbekend profiel '".$profile."'!\n"));
			$deployConfig=array_replace_recursive($deployConfigFull["default"], $deployConfigFull[$profile]);
		}

		// open SFTP connection
		$this->sftp=new SFTP($deployConfig["sftp"]["server"]);
		$res=$this->sftp->login($deployConfig["sftp"]["username"], $deployConfig["sftp"]["password"]);
		if($res===false) die(DeployOutput::error("SFTP: Fout bij inloggen!"));

		// create file syncer
		$this->deployFileSyncer=new DeployFileSyncer($this->sftp, $deployConfig);

		// create remote console
		$this->remoteConsole=new DeployRemoteConsole($this->sftp,
			$deployConfig["sftp"]["username"]."@".$deployConfig["sftp"]["server"]);
	}

	public function __destruct()
	{
		$this->sftp->disconnect();
	}

	public function diffLocalAndRemoteFiles()
	{
		$diff=$this->deployFileSyncer->diffLocalAndRemoteFiles($this->deployInfoPath);

		foreach($diff["missingremote"] as $fileToUpload)
			echo(DeployOutput::success("UPLOAD: ".$fileToUpload)."\n");

		foreach($diff["missinglocal"] as $fileToDelete)
			echo(DeployOutput::error("DELETE: ".$fileToDelete)."\n");

		foreach($diff["changed"] as $fileToUpload)
			echo(DeployOutput::info("UPLOAD: ".$fileToUpload)."\n");

		foreach($this->deployFileSyncer->checkRequiredRemotePathsExist() as $missingPath)
			echo(DeployOutput::success("MKDIR: ".$missingPath)."\n");
	}

	public function remoteConsole()
	{
		$this->remoteConsole->remoteConsole();
	}
}
