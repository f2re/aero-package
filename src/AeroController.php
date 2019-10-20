<?php

namespace F2re\Aero\Controllers;

use App\Http\Controllers\Controller;

use F2re\Aero\AeroDecoder;
use F2re\Aero\AeroDrawer;
use F2re\Aero\Stantions;

class AeroController extends Controller
{
  public $raw = 'TTAA 05231 01010 99023 06637 31008 00186 05034 30012 92817 00111 30011 85486 04907 28510 70005 07981 33514 50555 21911 35522 40717 29948 01026 30916 46132 01530 25034 56344 02535 20172 64339 03040 15351 55978 00519 10608 58379 35515 88204 65131 02042 77204 02042 40617 31313 42408 82304==

TTBB 05238 01010 00023 06637 11899 01900 22831 05901 33822 06517 44819 06961 55802 04971 66787 05760 77757 05577 88673 08980 99656 10167 11562 16775 22539 19362 33534 19757 44530 18710 55526 18330 66483 24302 77474 20960 88473 20959 99431 26341 11351 36558 22300 46132 33236 59340 44217 63317 55204 65131 66188 65947 77186 66156 88180 64365 99172 59371 11156 56977 22142 54580 33100 58379 21212 00023 31008 11967 29512 22874 30511 33836 28510 44815 30512 55662 34515 66597 34018 77494 35522 88476 01523 99441 36026 11251 02535 22204 02042 33189 03533 44183 02025 55179 01026 66133 01511 77126 00510 88123 35012 99114 01513 11106 01512 22100 35515 31313 42408 82304==';

  public function index()
  {
    
    return $this->getchart($this->raw);
  }

  /**
   * Декодируем сырой текст в структуру КН-04
   * @param  [string] $raw [сырой код КН-04]
   * @return [KN04]      [класс KN04]
   */
  public function decode($raw)
  {
    $decoder = new AeroDecoder($raw);
    $decoder->decode();
    return $decoder;
  }

  

  /**
   * Получаем сразу аэрологическую диаграмму из кода КН-04
   * @param  [type] $raw [description]
   * @return [type]      [description]
   */
  public function getchart($raw)
  {
    $decoder = $this->decode( $raw );
   //@stantionInfo Array ( [id] => 27612 [name] => Москва [name_en] => Moscow [country] => 3472 [lat] => 55.83 [lon] => 37.61 [high] => 147 ) 
    $stantions = new Stantions();
    $st = $stantions->get_stantion( $decoder->get_stantion_id() );
    // print_r($stantions->_stantions);
    $drawer = new AeroDrawer( $decoder, $st );
    if ($drawer->checkData()){
      $drawer->init()
                     ->drawInversions()
                     ->drawSost()
                     ->drawTemp()
                     ->drawIsoterm()
                     ->drawWind()
                     ->drawEnegry()
                     ->drawUK()
                     ->drawClouds()
                     ->drawIndexes()
                     ->saveImage();
    }

  }


}