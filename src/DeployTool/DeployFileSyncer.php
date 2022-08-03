<?php
namespace DeployTool;

use phpseclib3\Net\SFTP;

class DeployFileSyncer
{
	const HASHFILESTRUCT_CMD="hashfilestruct.php";
	const DEPLOYTOOL_PHP_FILE="deploytool.php";

	/** @var array $deployConfig */
	private $deployConfig;

	/** @var SFTP $sftp */
	private $sftp;

	/**
	 * @param SFTP $sftp
	 * @param array $deployConfig
	 */
	public function __construct($sftp, $deployConfig)
	{
		$this->deployConfig=$deployConfig;
		$this->sftp=$sftp;
	}

	/**
	 * @return string
	 */
	public function getRemoteFileList()
	{
		$basePath=(isset($this->deployConfig["basepath"]) ? $this->deployConfig["basepath"] : "./");

		$skipPathsRemote=array_merge($this->deployConfig["skippaths"]["app"], $this->deployConfig["skippaths"]["remote"], $this->deployConfig["skippaths"]["hosting"], [self::HASHFILESTRUCT_CMD]);

		$res=$this->sftp->put($basePath.self::HASHFILESTRUCT_CMD, realpath(dirname(__FILE__))."/../".self::HASHFILESTRUCT_CMD, SFTP::SOURCE_LOCAL_FILE);
		$this->sftp->chmod(0744, $basePath.self::HASHFILESTRUCT_CMD);
		if($res===false) die(DeployOutput::error("SFTP: Fout bij put!"));

		return $this->sftp->exec("cd ".$basePath." && ./".self::HASHFILESTRUCT_CMD." ".implode(" ", $skipPathsRemote));
	}

	/**
	 * @param string $deployInfoPath
	 * @return string
	 */
	public function getLocalFileList($deployInfoPath)
	{
		$skipPathsLocal=array_merge($this->deployConfig["skippaths"]["app"], $this->deployConfig["skippaths"]["local"], [self::HASHFILESTRUCT_CMD, $deployInfoPath, self::DEPLOYTOOL_PHP_FILE]);

		return shell_exec(realpath(dirname(__FILE__))."/../".self::HASHFILESTRUCT_CMD." ".implode(" ", $skipPathsLocal));
	}

	/**
	 * @param string $list
	 * @return array
	 */
	private static function convertToArray($list)
	{
		$files=[];
		foreach(explode("\n", $list) as $row)
		{
			if(empty($row)) continue;
			$cols=explode("|", $row);
			$files[$cols[0]]=["size"=>intval($cols[1]), "hash"=>$cols[2]];
		}
		return $files;
	}

	/**
	 * @param string $localFile
	 * @return string
	 */
	private function relocateLocalPath($localFile)
	{
		if(!isset($this->deployConfig["relocatepaths"])) return $localFile;
		foreach($this->deployConfig["relocatepaths"] as $relocatePathLocal=>$relocatePathRemote)
		{
			if(strpos($localFile, $relocatePathLocal)!==0) continue;
			$localFile=$relocatePathRemote.substr($localFile, strlen($relocatePathLocal));
		}
		return $localFile;
	}

	/**
	 * @param string $remoteFile
	 * @return string
	 */
	private function relocateRemotePath($remoteFile)
	{
		if(!isset($this->deployConfig["relocatepaths"])) return $remoteFile;
		foreach($this->deployConfig["relocatepaths"] as $relocatePathLocal=>$relocatePathRemote)
		{
			if(strpos($remoteFile, $relocatePathRemote)!==0) continue;
			$remoteFile=$relocatePathLocal.substr($remoteFile, strlen($relocatePathRemote));
		}
		return $remoteFile;
	}

	/**
	 * @param string $deployInfoPath
	 * @return array
	 */
	public function diffLocalAndRemoteFiles($deployInfoPath)
	{
		$remoteFiles=static::convertToArray($this->getRemoteFileList());
		$localFiles=static::convertToArray($this->getLocalFileList($deployInfoPath));

		// check missing remote files
		$missingRemoteFiles=[];
		foreach(array_keys($localFiles) as $localFile)
		{
			if(!isset($remoteFiles[$this->relocateLocalPath($localFile)])) $missingRemoteFiles[]=$localFile;
		}

		// check missing local files
		$missingLocalFiles=[];
		foreach(array_keys($remoteFiles) as $remoteFile)
		{
			if(!isset($localFiles[$this->relocateRemotePath($remoteFile)])) $missingLocalFiles[]=$remoteFile;
		}

		// check changed files
		$changedFiles=[];
		foreach($localFiles as $localFile=>$localFileInfo)
		{
			$remoteFile=$this->relocateLocalPath($localFile);
			if(!isset($remoteFiles[$remoteFile])) continue;
			$remoteFileInfo=$remoteFiles[$remoteFile];

			if($localFileInfo["size"]!==$remoteFileInfo["size"] || $localFileInfo["hash"]!==$remoteFileInfo["hash"])
			{
				$changedFiles[]=$localFile;    //["local"=>$localFile, "remote"=>$remoteFile, "localinfo"=>$localFileInfo, "remoteinfo"=>$remoteFileInfo];
			}
		}

		return ["missingremote"=>$missingRemoteFiles, "missinglocal"=>$missingLocalFiles, "changed"=>$changedFiles];
	}

	/**
	 * @return array
	 */
	public function checkRequiredRemotePathsExist()
	{
		if(!isset($this->deployConfig["requiredremotepaths"])) return [];

		$basePath=(isset($this->deployConfig["basepath"]) ? $this->deployConfig["basepath"] : "./");
		$missingPaths=[];
		foreach($this->deployConfig["requiredremotepaths"] as $requiredRemotePath)
		{
			if(!$this->sftp->file_exists($basePath.$requiredRemotePath))
				$missingPaths[]=$requiredRemotePath;
		}

		return $missingPaths;
	}
}
