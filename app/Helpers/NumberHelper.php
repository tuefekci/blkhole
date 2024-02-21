<?php


namespace App\Helpers;

class NumberHelper
{
    public static function humanReadableBytes($bytes, $decimals = 2)
	{
		$size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
	}

	/**
	 * Convert UNIX timestamp to human-readable date and time format.
	 *
	 * @param int $timestamp
	 * @param string $format (Optional)
	 * @return string
	 */
	public static function humanReadableTimestamp($timestamp, $format = 'Y-m-d H:i:s')
	{
		return date($format, $timestamp);
	}

	/**
	 * Convert server uptime timestamp to human-readable format.
	 *
	 * @param int $uptimeTimestamp
	 * @return string
	 */
	public static function humanReadableUptime($uptimeTimestamp)
	{
		$restartSeconds = floor($uptimeTimestamp / 1000);

		// Calculate the duration between current time and uptime timestamp
		$uptimeSeconds = time() - $restartSeconds;

		// Define time units and their corresponding seconds
		$units = [
			'year' => 31536000,
			'month' => 2592000,
			'week' => 604800,
			'day' => 86400,
			'hour' => 3600,
			'minute' => 60,
			'second' => 1,
		];

		// Initialize an empty array to store the time components
		$timeComponents = [];

		// Loop through each time unit and calculate its count
		foreach ($units as $unit => $value) {
			if ($uptimeSeconds >= $value) {
				$count = floor($uptimeSeconds / $value);
				$timeComponents[] = $count . ' ' . ($count == 1 ? $unit : $unit . 's');
				$uptimeSeconds %= $value;
			}
		}

		// Convert the time components array to a human-readable string
		$uptimeString = implode(', ', $timeComponents);

		// Add some formatting for a nicer output
		if (empty($uptimeString)) {
			$uptimeString = 'just now';
		} else {
			$uptimeString .= '';
		}

		return $uptimeString;
	}
}