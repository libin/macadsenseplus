#!/usr/bin/php
<?php
	/*
	 *
	 * Copyright (C) 2007 Kai 'Oswald' Seidler, http://oswaldism.de
	 * Copyright (C) 2007 Janos Rusiczki, http://www.rusiczki.net
	 *
	 * This program is free software; you can redistribute it and/or modify it
	 * under the terms of the GNU General Public License as published by the Free
	 * Software Foundation; either version 2 of the License, or (at your option)
	 * any later version.
	 * 
	 * This program is distributed in the hope that it will be useful, but WITHOUT
	 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
	 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
	 * more details.
	 * 
	 * You should have received a copy of the GNU General Public License along with
	 * this program; if not, write to the Free Software Foundation, Inc., 675 Mass
	 * Ave, Cambridge, MA 02139, USA. 
	 * 
	 */
	
	$debug_mode = 1;
	$debug_text = '';

	include("keychain.php");
	$keychain="MacAdSense";

	if($argv[1]!="getdata" && $argv[1]!="setcredentials")
	{
		echo "Usage: {$argv[0]} getdata\n";
		echo "       {$argv[0]} setcredentials\n";
		exit(1);
	}

	if($argv[1]=="setcredentials")
	{
		$fp = fopen("php://stdin", "r") or die("can't read stdin");
		$username = trim(fgets($fp));
		$password = trim(fgets($fp));
		fclose($fp);

		kc_createKeyChain($keychain);
		kc_addPassword($keychain, $username, $password);
		exit;

	}

	$fp = fopen("php://stdin", "r") or die("can't read stdin");
	$username = trim(fgets($fp));
	$start_of_week = trim(fgets($fp));
	fclose($fp);

	$start_of_week = (int)$start_of_week;

	$password = kc_getPassword($keychain, $username);

	// The following line were based on code snippets from http://www.webmasterworld.com/forum89/4877.htm 

	$postdata="Email=".urlencode($username)."&Passwd=%09".urlencode($password)."&service=adsense&ifr=true&rmShown=1&null=Sign+in";
	$agent="User-Agent: Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)";

	$cookie = tempnam(dirname(__FILE__), "cookie");

	$end_year = date('Y');
	$start_year = $end_year;
	$end_month = date('n');
	$start_month = $end_month - 1;
	if($start_month == 0)
	{
		$start_month = 12;
		$start_year--;
	}
	$start_day = 1;
	$end_day = date('t');

	$chain = array(
		'https://www.google.com/accounts/ServiceLoginBoxAuth',
		'https://www.google.com/accounts/CheckCookie?continue=https%3A%2F%2Fwww.google.com%2Fadsense%2Flogin-box-gaiaauth&followup=https%3A%2F%2Fwww.google.com%2Fadsense%2Flogin-box-gaiaauth&service=adsense&hl=en_US&chtml=LoginDoneHtml',
		'variable',
		'https://www.google.com/adsense/report/aggregate?product=afc&dateRange.dateRangeType=custom&dateRange.customDate.start.month='.$start_month.'&dateRange.customDate.start.day='.$start_day.'&dateRange.customDate.start.year='.$start_year.'&dateRange.customDate.end.month='.$end_month.'&dateRange.customDate.end.day='.$end_day.'&dateRange.customDate.end.year='.$end_year.'&reportType=property&groupByPref=date&outputFormat=TSV_EXCEL&unitPref=page'
	);


	$ch = curl_init();
	foreach ($chain as $url)
	{
		if($url == "variable") $url = $variable;
		curl_setopt ($ch, CURLOPT_URL,$url);
		curl_setopt ($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt ($ch, CURLOPT_TIMEOUT, 35);
		curl_setopt ($ch, CURLOPT_FOLLOWLOCATION,1);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie);
		curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookie);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt ($ch, CURLOPT_POST, 1);
		$result = curl_exec ($ch);
		if(ereg("refresh",$result))
		{
			$result = str_replace("\n","",$result);
			$result=ereg_replace("^.*url=&#39;","",$result);
			$result=ereg_replace("&#39;.*$","",$result);
			$result=ereg_replace("&amp;","&",$result);
			$result=ereg_replace("google.de","google.com",$result);
			$variable=$result;
		}
	}
	curl_close($ch);

	// poor man's UTF-8 to Latin-1 recode (because Apple's PHP is missing iconv and recode extensions)
	$result = str_replace("\x00", "", $result);
	
	if($debug_mode)
	{
		$debug_text .= "Generated on: ".strftime("%D %T")."\n\n";
		$debug_text .= "Results (phase 1)\n\n-------------------------------------------------------------------\n";
		$debug_text .= $result;
		$debug_text .= "-------------------------------------------------------------------\n\n";
	}

	// remove surrounding quotes
	$result = str_replace("\"", "", $result);

	// replacing the decimal separator "," with "." (this is useful if you're using a German AdSense account)
	$result = str_replace(",", ".", $result);

	$result = explode("\n", $result);

	$filtered_results = array();
	
	if($debug_mode)
	{
		$debug_text .= "Results (phase 2)\n\n-------------------------------------------------------------------\n";
	}

	foreach ($result as $line) {
	    $line_values = explode("\t", $line);

		$date = $line_values[0];
		$date_values = explode('-', $date);
	
		$date_year = $date_values[0];
		$date_month = $date_values[1];
		$date_day = $date_values[2];
	
		if(checkdate((int)$date_month, (int)$date_day, (int)$date_year))
		{
			// we need to do some processing for earnings to be sure to turn 1.234.56 into 1234.56
			$earnings = $line_values[5];
			if(ereg('\..*\.', $earnings)) $earnings = preg_replace('/\./', '', $earnings, 1);
		
			$filtered_results[] = array(
				'date' => $line_values[0],
				'date_year' => $date_year,
				'date_month' => $date_month,
				'date_day' => $date_day,
				'impressions' => $line_values[1],
				'clicks' => $line_values[2],
				'ctr' => $line_values[3],
				'ecpm' => $line_values[4],
				'earnings' => $earnings
			);
			if($debug_mode)
			{
				$debug_text .= $date_year.'-'.$date_month.'-'.$date_day.' / i = '.$line_values[1].' - c = '.$line_values[2].' - e = $'.$earnings."\n";
			}
		}
	}
	
	$debug_text .= "-------------------------------------------------------------------\n";

	$today = $filtered_results[sizeof($filtered_results) - 1];
	$yesterday = $filtered_results[sizeof($filtered_results) - 2];

	$start_month_day_count = date('t', mktime(0, 0, 0, $start_month, 1, $start_year));

	$day_of_week = date('w', mktime(0, 0, 0, $today['date_month'], $today['date_day'], $today['date_year']));
	$day_of_week -= $start_of_week;
	if($day_of_week <= -1) {
		$day_of_week = 7 + $day_of_week;
	}
	$this_week['start_day'] = $today['date_day'] - $day_of_week;

	if($this_week['start_day'] <= 0) {
		$this_week['start_day'] = $start_month_day_count + $this_week['start_day'];
		$this_week['start_month'] = $start_month;
		$this_week['start_year'] = $start_year;
	} else {
		$this_week['start_month'] = $end_month;
		$this_week['start_year'] = $end_year;
	}

	$last_week['end_day'] = $this_week['start_day'] - 1;

	if($last_week['end_day'] == 0) {
		$last_week['end_day'] = $start_month_day_count;
		$last_week['end_month'] = $start_month;
		$last_week['end_year'] = $start_year;
	
		$last_week['start_day'] = $last_week['end_day'] - 6;
		$last_week['start_month'] = $last_week['end_month'];
		$last_week['start_year'] = $last_week['end_year'];
	} else {
		$last_week['end_month'] = $this_week['start_month'];
		$last_week['end_year'] = $this_week['start_year'];
	
		$last_week['start_day'] = $last_week['end_day'] - 6;
	
		if($last_week['start_day'] <= 0) {
			$last_week['start_day'] = $start_month_day_count + $last_week['start_day'];
			$last_week['start_month'] = $start_month;
			$last_week['start_year'] = $start_year;
		} else {
			$last_week['start_month'] = $last_week['end_month'];
			$last_week['start_year'] = $last_week['end_year'];
		}
	}

	$last_week['impressions'] = 0;
	$last_week['clicks'] = 0;
	$last_week['earnings'] = 0;

	$this_week['impressions'] = 0;
	$this_week['clicks'] = 0;
	$this_week['earnings'] = 0;

	$last_month['impressions'] = 0;
	$last_month['clicks'] = 0;
	$last_month['earnings'] = 0;

	$this_month['impressions'] = 0;
	$this_month['clicks'] = 0;
	$this_month['earnings'] = 0;

	foreach($filtered_results as $line) {
		$date_year = $line['date_year'];
		$date_month = $line['date_month'];
		$date_day = $line['date_day'];
		$impressions = $line['impressions'];
		$clicks = $line['clicks'];
		$ctr = $line['ctr'];
		$ecpm = $line['ecpm'];
		$earnings = $line['earnings'];
	
		if(($date_year == $start_year) && ($date_month == $start_month)) {
			$last_month['impressions'] += $impressions;
			$last_month['clicks'] += $clicks;
			$last_month['earnings'] += $earnings;			
		} else {
			$this_month['impressions'] += $impressions;
			$this_month['clicks'] += $clicks;
			$this_month['earnings'] += $earnings;						
		}
	
		$current_timestamp = mktime(12, 0, 0, $date_month, $date_day, $date_year);
		$last_week_start_timestamp = mktime(0, 0, 0, $last_week['start_month'], $last_week['start_day'], $last_week['start_year']);
		$last_week_end_timestamp = mktime(23, 59, 59, $last_week['end_month'], $last_week['end_day'], $last_week['end_year']);
		$this_week_start_timestamp = mktime(0, 0, 0, $this_week['start_month'], $this_week['start_day'], $this_week['start_year']);
			
		if($current_timestamp >= $last_week_start_timestamp && $current_timestamp <= $last_week_end_timestamp) {
			$last_week['impressions'] += $impressions;
			$last_week['clicks'] += $clicks;
			$last_week['earnings'] += $earnings;
		}
	
		if($current_timestamp >= $this_week_start_timestamp) {
			$this_week['impressions'] += $impressions;
			$this_week['clicks'] += $clicks;
			$this_week['earnings'] += $earnings;
		}	
	}
	
	if($debug_mode)
	{
		$debug_text .= "\nLast week\n";
		$debug_text .= "Last week - Start: ".$last_week['start_year'].'-'.$last_week['start_month'].'-'.$last_week['start_day']."\n";
		$debug_text .= "Last week - End: ".$last_week['end_year'].'-'.$last_week['end_month'].'-'.$last_week['end_day']."\n";

		$debug_text .= "\nThis week\n";
		$debug_text .= "This week - Start: ".$this_week['start_year'].'-'.$this_week['start_month'].'-'.$this_week['start_day']."\n";
	}

	unlink($cookie);
	
	if($debug_mode)
	{
		$debug_text .= "\nFinal results\n";
		$debug_text .= "-------------------------------------------------------------------\n";
		$debug_text .= "When       - Impressions / Clicks / Earnings\n";
		$debug_text .= "Today      - ".$today['impressions'].' / '.$today['clicks'].' / '.$today['earnings']."\n";
		$debug_text .= "Yesterday  - ".$yesterday['impressions'].' / '.$yesterday['clicks'].' / '.$yesterday['earnings']."\n";
		$debug_text .= "Last week  - ".$last_week['impressions'].' / '.$last_week['clicks'].' / '.$last_week['earnings']."\n";
		$debug_text .= "This week  - ".$this_week['impressions'].' / '.$this_week['clicks'].' / '.$this_week['earnings']."\n";
		$debug_text .= "Last month - ".$last_month['impressions'].' / '.$last_month['clicks'].' / '.$last_month['earnings']."\n";
		$debug_text .= "This month - ".$this_month['impressions'].' / '.$this_month['clicks'].' / '.$this_month['earnings'];
		
		$filename = 'MacAdSensePlusDebug.txt';
		if(is_writable($filename)) {
    		if($handle = fopen($filename, 'w')) {
				fwrite($handle, $debug_text);
			    fclose($handle);
		    }
		}		
	}

	// Output format: TIME # TODAY CLICKS # TODAY EARNINGS # YESTERDAY CLICKS # YESTERDAY EARNINGS # LAST WEEK CLICKS # LAST WEEK EARNINGS # THIS WEEK CLICKS # THIS WEEK EARNINGS # LAST MONTH CLICKS # LAST MONTH EARNINGS # THIS MONTH CLICKS # THIS MONTH EARNINGS
	
	echo(
		strftime("%d.%m %H:%M")."#".
		$today['clicks'].'#'.number_format($today['earnings'], 2).'#'.
		$yesterday['clicks'].'#'.number_format($yesterday['earnings'], 2).'#'.
		$last_week['clicks'].'#'.number_format($last_week['earnings'], 2).'#'.
		$this_week['clicks'].'#'.number_format($this_week['earnings'], 2).'#'.
		$last_month['clicks'].'#'.number_format($last_month['earnings'], 2).'#'.
		$this_month['clicks'].'#'.number_format($this_month['earnings'], 2)
	);
?>