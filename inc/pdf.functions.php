<?php
// define a clas extension to allow the use of a callback to get the table of contents, and to put the dots in the toc
class Creport extends Cezpdf {
  var $reportContents = array();

  function Creport($p,$o){
    $this->Cezpdf($p,$o);
  }

  function rf($info){
    // this callback records all of the table of contents entries, it also places a destination marker there
    // so that it can be linked too
    $tmp = $info['p'];
    $lvl = $tmp[0];
    $lbl = rawurldecode(substr($tmp,1));
    $num=$this->ezWhatPageNumber($this->ezGetCurrentPageNumber());
    $this->reportContents[] = array($lbl,$num,$lvl );
    $this->addDestination('toc'.(count($this->reportContents)-1),'FitH',$info['y']+$info['height']);
  }

  function dots($info){
    // draw a dotted line over to the right and put on a page number
    $tmp = $info['p'];
    $lvl = $tmp[0];
    $lbl = substr($tmp,1);
    $xpos = 520;

    switch($lvl){
      case '1':
        $size=16;
        $thick=1;
        break;
      case '2':
        $size=12;
        $thick=0.5;
        break;
    }
    $this->saveState();
    $this->setLineStyle($thick,'round','',array(0,10));
    $this->line($xpos,$info['y'],$info['x']+5,$info['y']);
    $this->restoreState();
    $this->addText($xpos+5,$info['y'],$size,$lbl);
  }
}

function company_logo(&$pdf,$x,$y,$height,$wl=0,$wr=0){
  global $company_name;
  $pdf->saveState();
  $h=100;
  $scaler=strlen($company_name)/10; 
  if ( $scaler < 1 ) {
    $scaler = 1;
  }
  $factor = $height/($h*$scaler);
  $pdf->selectFont('./fonts/Helvetica-Bold.afm');
  $text = $company_name;
  $ts=100*$factor;
  $th = $pdf->getFontHeight($ts);
  $td = $pdf->getFontDecender($ts);
  $tw = $pdf->getTextWidth($ts,$text);
  $pdf->setColor(212/255,0/255,0/255);
  $z = 0.86;
  $pdf->filledRectangle($x-$wl,$y-$z*$h*$factor,$tw*1.2+$wr+$wl,$h*$factor*$z);
  $pdf->setColor(255/255,255/255,255/255);
  $pdf->addText($x,$y-$th*0.85-$td,$ts,$text);
  $pdf->setColor(212/255,0/255,0/255);
  $pdf->addText($x,$y-$th-$td,$ts*0.1,'');
  $pdf->restoreState();
  return $height;
}
?>
