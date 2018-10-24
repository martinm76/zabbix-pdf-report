<?php
//////////
// 
// (c) Travis Mathis - travisdmathis@gmail.com
// Zabbix Report Generator v0.9
//
// 2015-03 : mmo@pwc.dk - Added trigger data and acknowledgements
// 2015-03 : mmo@pwc.dk - Added option to selectively include or exclude graphs and triggers
//
// INCLUDES

include("config.inc.php");

if ( $user_login == 1 ) {
session_start();
//print_r($_SESSION);
if ( $allow_localhost == 1 ) {
	if ( $_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
		$z_user=$_SESSION['username'];
		$z_pass=$_SESSION['password'];
	}
} else {
	$z_user=$_SESSION['username'];
	$z_pass=$_SESSION['password'];
}

/*
if ( $z_user == "" ) {
  print_r($_SERVER);
  print_r($_POST);
  print_r($_GET);
  exit(0);
  $z_user=$_POST['username'];
  $z_pass=$_POST['password'];
}
*/
if ( $z_user == "" ) {
  header("Location: index.php");
}

$z_login_data	= "name=" .$z_user ."&password=" .$z_pass ."&autologin=1&enter=Sign+in";
}

global $z_user, $z_pass, $z_login_data;

require_once("inc/ZabbixAPI.class.php");
include("inc/createpdf.functions.php");
include ("inc/class.ezpdf.php");
include ("inc/pdf.functions.php");

// ERROR REPORTING
error_reporting(E_ALL);
set_time_limit(1800);

// Process GET variables
if (isset($_GET['GraphsOn'])) { $GraphsOn="yes"; }
if (isset($_GET['ItemGraphsOn'])) { $ItemGraphsOn="yes"; }
if (isset($_GET['TriggersOn'])) { $TriggersOn="yes"; }
if (isset($_GET['ItemsOn'])) { $ItemsOn="yes"; }
if (isset($_GET['TrendsOn'])) { $TrendsOn="yes"; }

if (isset($_GET['debug']))	 { $debug	= true; } 
else				 { $debug	= false; }
if (isset($_GET['HostID']))	 { $hostid	= filter_input(INPUT_GET,'HostID', FILTER_SANITIZE_STRING); }
if (isset($_GET['GroupID']))	 { $groupid	= filter_input(INPUT_GET,'GroupID', FILTER_SANITIZE_STRING); }
if (isset($_GET['ReportType']))  { $reporttype	= filter_input(INPUT_GET,'ReportType', FILTER_SANITIZE_STRING); }
if (isset($_GET['ReportRange'])) {
	if ($_GET['ReportRange'] == "last") {
		$timeperiod		= filter_input(INPUT_GET,'timePeriod', FILTER_SANITIZE_STRING);
		// Format $timeperiod into seconds 
		if    ($timeperiod == 'Hour')		{ $timeperiod = '3600';     }
		elseif($timeperiod == 'Day')		{ $timeperiod = '86400';    }
		elseif($timeperiod == 'PrevDay')	{ $timeperiod = '86400';    }
		elseif($timeperiod == 'Week')		{ $timeperiod = '604800';   }
		elseif($timeperiod == 'PrevWeek')	{ $timeperiod = '604800';   }
		elseif($timeperiod == 'Month')		{ $timeperiod = '2678400';  }
		elseif($timeperiod == 'PrevMonth')	{ $timeperiod = '2678400';  }
		elseif($timeperiod == 'Year')		{ $timeperiod = '31536000'; }
		$starttime = time() - $timeperiod;
		$stime      = date('YmdHis',$starttime);
		$endtime   = time();
	}
	elseif ($_GET['ReportRange'] == "custom") {
		//var_dump($_GET);
		// TODO: Check if start/end is empty
		if (isset($_GET['startdate'])) {
			if ($_GET['startdate'] == "") { 
				echo "<font color=\"red\"><h1>Startdate is missing!</h1></font></br>\n";
				echo "When setting custom report period, startdate is required</br>\n";
				exit;
			}
		}
		$starttime  = strtotime($_GET['startdate'] . " " . $_GET['starttime']);
		$stime      = date('YmdHis',$starttime);
		$endtime    = strtotime($_GET['enddate'] . " " . $_GET['endtime']);
		$timeperiod = $endtime - $starttime;
		if ($starttime > $endtime) { 
			echo "<font color=\"red\"><h1>Startdate need to be before tomorrow or end date!</h1></font></br>\n"; 
			exit;
		} elseif ($endtime - $starttime < 3600) {
			echo "<font color=\"red\"><h1>Time frame need to be minimum 1 hour!</h1></font></br>\n"; 
			exit;
		}
	}
	else {
		echo "Unknown time report range!\n";
		exit;
	}
}
if (isset($_GET['mygraphs2'])) { $mygraphs=$_GET['mygraphs2']; } // Use the manually specified values for what graphs to show
if (isset($_GET['myitems2'])) { $myitemgraphs=$_GET['myitems2']; } // Use the manually specified values for what graphs to show

// Calculate report starttime and endtime
$report_start	= date('Y.m.d H:i',$starttime);
$report_end	= date('Y.m.d H:i',$endtime);

# print <<< ENDHTML
#</br>
#Starttime : $starttime</br>
#Timeperiod: $timeperiod</br>
#STime     : $stime</br>
#Start     : $report_start</br>
#End       : $report_end</br>
#ENDHTML;

// Setup temporary file/directory names
$z_tmpimg_path	= tempdir($z_tmp_path);
$tmp_pdf_data	= tempnam($z_tmp_path,"zabbix_report");
// Set Timezone
date_default_timezone_set("$timezone");

// Print Header if debug is on
if ($debug) {
	header( 'Content-type: text/html; charset=utf-8' );
	if (isset($hostid)) { echo "<b>HostID: </b>" .$hostid ."</br>\n"; }
	if (isset($groupid)) { echo "<b>GroupID: </b>" .$groupid ."</br>\n"; }
	if (isset($reporttype)) { echo "<b>Report Type: </b>" .$reporttype ."</br>\n"; }
	if (isset($timeperiod)) { echo "<b>Time Period: </b>" .$timeperiod ."</br>\n"; }
	echo "<b>Temp image path: </b>" .$z_tmpimg_path ."</br>\n";
	echo "</br>\n";
	flush();
	ob_flush();
}
// get graphids
// Login to Zabbix API using ZabbixAPI.class.php
ZabbixAPI::debugEnabled(TRUE);
ZabbixAPI::login($z_server,$z_user,$z_pass)
	or die('Unable to login: '.print_r(ZabbixAPI::getLastError(),true));

#save graphs to directory for selected host
$fh = fopen($tmp_pdf_data, 'w');
$stringData = "1<Introduction>\n\n";
fwrite($fh, $stringData);
$stringData = "This is an automatically generated PDF file containing data gathered from Zabbix Monitoring System\n";
fwrite($fh, $stringData);
$stringData = "#NP\n";
fwrite($fh, $stringData);
#$stringData = "1<Graphs>\n";
#fwrite($fh, $stringData);
fclose($fh);

if ($reporttype == 'host') {
	if (!is_numeric($hostid)) { echo "ERROR: Need hostid for host report!</br>\n"; exit; }
	$hosts  = ZabbixAPI::fetch_array('host','get',array('output'=>array('hostid','name'),'with_graphs'=>'true','hostids'=>$hostid))
		or die('Unable to get hosts: '.print_r(ZabbixAPI::getLastError(),true));
	//var_dump($hosts);
	// Get name to be used in PLACEHOLDER-part of filename
	$name = $hosts[0]['name'];
	$reportname=str_replace(" ", "_",$name);
	CreatePDF($hosts);
}
elseif ($reporttype == 'hostgroup') {
	if (!is_numeric($groupid)) { echo "ERROR: Need groupid for group report!</br>\n"; exit; }
	$hosts  = ZabbixAPI::fetch_array('host','get',array('output'=>array('hostid','name'),'with_graphs'=>'true','groupids'=>$groupid))
		or die('Unable to get hosts: '.print_r(ZabbixAPI::getLastError(),true));
	//var_dump($hosts);
	$hostgroupname = ZabbixAPI::fetch_array('hostgroup','get',array('output'=>array('name'),'groupids'=>$groupid))
		or die('Unable to get hostgroup: '.print_r(ZabbixAPI::getLastError(),true));
	//var_dump($hostgroupname);
	$name = $hostgroupname[0]['name'];
	$reportname=str_replace(" ", "_",$name);
	$reportname=str_replace("/", "--",$name);
	CreatePDF($hosts);
}
else {
	echo "Report type not selected!\n";
	exit;
}

//
// Create PDF
//
if (!file_exists($tmp_pdf_data)) {
	echo "Report $tmp_pdf_data not found! Cannot continue to create PDF.";
	exit;
}

$pdf_filename	= "$reportname.pdf";

//$pdf = new Cezpdf('a4','portrait');
$pdf = new Creport("$paper_format","$paper_orientation");

$pdf -> ezSetMargins(35,50,50,50);

// put a line top and bottom on all the pages
$all = $pdf->openObject();
$pdf->saveState();
$pdf->setStrokeColor(0,0,0,1);
$pdf->line(20,40,578,40);
$pdf->line(20,822,578,822);
$pdf->addText(50,34,6,'Generated by Zabbix Monitoring Dynamic Report v' . $version);
$pdf->restoreState();
$pdf->closeObject();
// note that object can be told to appear on just odd or even pages by changing 'all' to 'odd'
// or 'even'.
$pdf->addObject($all,'all');

$pdf->ezSetDy(-100);

//$mainFont = './fonts/Helvetica.afm';
$mainFont = './fonts/Times-Roman.afm';
$codeFont = './fonts/Courier.afm';
$images = '';

// select a font
$pdf->selectFont($mainFont);

$pdf->ezText("$company_name Zabbix Report",(40-strlen($company_name)),array('justification'=>'centre'));
$pdf->ezText("",14,array('justification'=>'centre'));
$pdf->ezText("for",16,array('justification'=>'centre'));
$pdf->ezText("",14,array('justification'=>'centre'));
$pdf->ezText("$name",40,array('justification'=>'centre'));
$pdf->ezText("",14,array('justification'=>'centre'));
$pdf->ezText("generated on",14,array('justification'=>'centre'));
$pdf->ezText("",14,array('justification'=>'centre'));
$pdf->ezText(date('l jS \of F Y \a\t H:i'),19,array('justification'=>'centre'));
//$pdf->ezText("(Version " . $version . ")",14,array('justification'=>'centre'));

$pdf->openHere('Fit');

company_logo($pdf,150,$pdf->y-80,80,150,200);

$pdf->ezSetDy(-400);
$pdf->ezText("Report start  : $report_start",14,array('justification'=>'right'));
$pdf->ezText("Report end   : $report_end",14,array('justification'=>'right'));

$pdf->selectFont($mainFont);

// modified to use the local file if it can
if (file_exists($pdf_logo)){
  //$pdf->addPngFromFile($pdf_logo,199,$pdf->y-375,200,0);
  $pdf->addPngFromFile($pdf_logo,50,$pdf->y,200,0);
  if ($debug) { 
    echo "$pdf_logo written to PDF-file ...<br/>";
  }
} else if ($debug) {
    echo "$pdf_logo was not found for inclusion ...<br/>";
}
//-----------------------------------------------------------
// load up the document content
$data=file($tmp_pdf_data);
unlink($tmp_pdf_data);

$pdf->ezNewPage();

$pdf->ezStartPageNumbers(500,28,10,'','',1);

$size=12;
$height = $pdf->getFontHeight($size);
$textOptions = array('justification'=>'full');
$collecting=0;
$code='';

foreach ($data as $key => $line){
  // go through each line, showing it as required, if it is surrounded by '<>' then 
  // assume that it is a title
  $line=chop($line);
  if (strlen($line) && $line[0]=='#'){
    // comment, or new page request
    switch($line){
      case '#NP':
        $pdf->ezNewPage();
        break;
      case '#C':
        $pdf->selectFont($codeFont);
        $textOptions = array('justification'=>'left','left'=>20,'right'=>20);
        $size=8;
        break;
      case '#c':
        $pdf->selectFont($mainFont);
        $textOptions = array('justification'=>'full');
        $size=10;
        break;
      case '#X':
        $collecting=1;
        break;
      case '#x':
        $pdf->saveState();
        eval($code);
        $pdf->restoreState();
        $pdf->selectFont($mainFont);
        $code='';
        $collecting=0;
        break;
    }
  } else if ($collecting){
    $code.=$line;
//  } else if (((strlen($line)>1 && $line[1]=='<') || (strlen($line) && $line[0]=='<')) && $line[strlen($line)-1]=='>') {
  } else if (((strlen($line)>1 && $line[1]=='<') ) && $line[strlen($line)-1]=='>') {
    // then this is a title
    switch($line[0]){
      case '1':
		$tmp = substr($line,2,strlen($line)-3);
        $tmp2 = $tmp.'<C:rf:1'.rawurlencode($tmp).'>';
        $pdf->ezText($tmp2,26,array('justification'=>'centre'));
        break;
      default:
        $tmp = substr($line,2,strlen($line)-3);
        // add a grey bar, highlighting the change
        $tmp2 = $tmp.'<C:rf:2'.rawurlencode($tmp).'>';
        $pdf->transaction('start');
        $ok=0;
        while (!$ok){
          $thisPageNum = $pdf->ezPageCount;
          $pdf->saveState();
          $pdf->setColor(0.9,0.9,0.9);
          $pdf->filledRectangle($pdf->ez['leftMargin'],$pdf->y-$pdf->getFontHeight(18)+$pdf->getFontDecender(18),$pdf->ez['pageWidth']-$pdf->ez['leftMargin']-$pdf->ez['rightMargin'],$pdf->getFontHeight(18));
          $pdf->restoreState();
          $pdf->ezText($tmp2,18,array('justification'=>'centre'));
          if ($pdf->ezPageCount==$thisPageNum){
            $pdf->transaction('commit');
            $ok=1;
          } else {
            // then we have moved onto a new page, bad bad, as the background colour will be on the old one
            $pdf->transaction('rewind');
           #$pdf->ezNewPage();
          }
        }
        break;
    }
  } else if (((strlen($line)>1 && $line[0]=='[') ) && $line[strlen($line)-1]==']') {
  		$var = str_replace("[","",$line);
		$image = str_replace("]","",$var);
		$pdf->EzImage($image, '28', '470', 'none', 'centre');
  } else {
    // then this is just text
    // the ezpdf function will take care of all of the wrapping etc.
    $pdf->ezText($line,$size,$textOptions);
  }
  
}

$pdf->ezStopPageNumbers(1,1);

// now add the table of contents, including internal links
$pdf->ezInsertMode(1,1,'after');
$pdf->ezNewPage();
$pdf->ezText("Contents\n",26,array('justification'=>'centre'));
$xpos = 520;
$contents = $pdf->reportContents;
foreach($contents as $k=>$v){
  switch ($v[2]){
    case '1':
      $y=$pdf->ezText('<c:ilink:toc'.$k.'>'.$v[0].'</c:ilink><C:dots:1'.$v[1].'>',16,array('aright'=>$xpos));
//      $y=$pdf->ezText($v[0].'<C:dots:1'.$v[1].'>',16,array('aright'=>$xpos));
      break;
    case '2':
//      $pdf->ezText('<c:ilink:toc'.$k.'>'.$v[0].'</c:ilink><C:dots:2'.$v[1].'>',12,array('left'=>50,'aright'=>$xpos));
      $pdf->ezText('<c:ilink:toc'.$k.'>'.$v[0].'</c:ilink><C:dots:1'.$v[1].'>',16,array('left'=>50,'aright'=>$xpos));
      break;
  }
}

$pdfcode = $pdf->ezOutput(1);
$fh = fopen("$pdf_report_dir/$pdf_filename", 'w');
fwrite($fh, $pdfcode);
fclose($fh);

// Clean up temp images
array_map('unlink', glob("$z_tmpimg_path/*"));
rmdir("$z_tmpimg_path");

if ($debug) { echo "Report ready - available as: <A HREF=\"$pdf_report_url/$pdf_filename\">$pdf_report_url/$pdf_filename</A></br>\n"; }
else { header("Location: $pdf_report_url/$pdf_filename"); }
?>
