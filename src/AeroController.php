<?php

namespace F2re\Aero\Controllers;

use App\Http\Controllers\Controller;

use F2re\Aero\AeroDecoder;
use F2re\Aero\AeroDrawer;

class AeroController extends Controller
{
  public $raw = 'TTAA 29111 01001 99014 02617 32509 85420 01341 35517 70947 07517 35025 50550 21500 34537 40712 30957 35037 30909 46557 35041 25028 55357 35040 20168 59559 35031 15351 53172 33517 10611 56581 35516 88218 60956 35539 77237 35042 40110 31313 47708 81111==

TTBB 29118 01001 00014 02617 11999 01819 22991 01224 33860 01157 44850 01341 55825 03126 66769 05714 77760 04737 88728 06300 99712 06717 11661 09356 22635 10902 33610 12324 44511 20500 55484 21900 66400 30957 77362 36557 88347 37966 99285 49156 11267 52356 22218 60956 33183 55368 44150 53172 55101 56980 21212 00014 32509 11952 01511 22748 34522 33485 35039 44133 34012 55115 35516 66108 34510 77101 35016 31313 47708 81111==';

  public function index()
  {
    
    $this->getchart($this->raw);
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
    $st = [ 
      'id'      => '27612' ,
      'name'    => 'Москва',
      'name_en'  => 'Moscow' ,
      'country' => '3472' ,
      'lat'     => 55.83 ,
      'lon'     => 37.61 ,
      'high'    => 147  ];

    $drawer = new AeroDrawer( $decoder, $st );
    if ($drawer->checkData()){
      $drawer->init()
             ->drawInversions()
             ->drawTemp()
             ->drawIsoterm()
             ->saveImage();
    }

  }


}