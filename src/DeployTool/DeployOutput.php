<?php
namespace DeployTool;

class DeployOutput
{
	/**
	 * @param string $msg
	 * @return string
	 */
	public static function error($msg)
	{
		return "\033[31m$msg \033[0m";
	}

	/**
	 * @param string $msg
	 * @return string
	 */
	public static function warning($msg)
	{
		return "\033[33m$msg \033[0m";
	}

	/**
	 * @param string $msg
	 * @return string
	 */
	public static function success($msg)
	{
		return "\033[32m$msg \033[0m";
	}

	/**
	 * @param string $msg
	 * @return string
	 */
	public static function info($msg)
	{
		return "\033[36m$msg \033[0m";
	}
}
