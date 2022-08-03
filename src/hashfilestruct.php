#!/usr/bin/env php
<?php
const FILE_HASH_ALGO="sha256";

set_time_limit(300);

/**
 * @param string $filePath
 * @return string
 */
function hashFileContents($filePath)
{
	$size=filesize($filePath);
	$hash=hash_file(FILE_HASH_ALGO, $filePath);
	return $filePath."|".$size."|".$hash;
}

/**
 * @param string $basePath
 * @param string $path
 * @param array $skipPaths
 * @return string
 */
function hashDirContents($basePath, $path, $skipPaths)
{
	$rows=[];

	$dir=new DirectoryIterator($basePath.$path);
	foreach($dir as $fileInfo)
	{
		$filePath=$fileInfo->getPathname();
		if(strpos($filePath, $basePath)===0) $filePath=substr($filePath, strlen($basePath));
		if($fileInfo->isDot()) continue;
		if(in_array($filePath, $skipPaths)) continue;
		if($fileInfo->isDir())
		{
			$row=hashDirContents($basePath, $filePath, $skipPaths);
			if($row!=="") $rows[$filePath]=$row;
		}
		else
		{
			$rows[$filePath]=hashFileContents($filePath);
		}
	}

	if(count($rows)===0) return "";
	ksort($rows);
	return implode("\n", array_values($rows));
}

$skipPaths=array_slice($argv, 1);

$output=hashDirContents("./", "", $skipPaths)."\n";
echo($output);
