<?php

namespace F2re\Aero\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;

use F2re\Aero\AeroDecoder;
use F2re\Aero\AeroDataProvider;
use F2re\Aero\AeroDrawer;
use F2re\Aero\Stantions;

class AeroController extends Controller
{
  public $raw = '';

  /**
   * return available stantions
   * @return [type] [description]
   */
  public function index()
  {
    $provider  = new AeroDataProvider;
    $available = $provider->get_available();
    $stantions = new Stantions();

    // get all stantions and filter them by available
    $allstantions = $stantions->get_all_stantions();

    $allavailable = [];
    foreach ($available as $st) {
      if ( isset($allstantions[$st]) ){
        $allavailable[$st] = $allstantions[$st];
      }else{
        $allavailable[$st] = [  'id'          => $st,
                                'name'        => $st,
                                'name_en'     => $st,
                                'country'     => '',
                                'lat'         => '',
                                'lon'         => ''
                                ];
      }
    }

    

    return response()->json([
        'stantions' => $allavailable
    ]);
  }

  /**
   * рисуем диаграмму по станции
   * @param  [type] $stantion [description]
   * @return [type]           [description]
   */
  public function bystantion($stantion)
  {
    $provider  = new AeroDataProvider;
    $this->raw = $provider->get_data($stantion);
    $path =  $this->getchart($this->raw);
    return response()->json([
        'path' => $path
    ]);
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
    $stantions = new Stantions();
    $st = $stantions->get_stantion( $decoder->get_stantion_id() );
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
      $path = $drawer->getImagePath();
      return $path;
    }
  }

  /**
   * get chart from post raw
   * @return [type] [description]
   */
  public function getchart_post(Request $request){
    $path = false;
    $raw  = false;
    if ( Input::has('raw') ){
      $raw = Input::get('raw');  
      $path = $this->getchart($raw);
    }

    return response()->json([
        'path' =>  $path,
        'raw' => $raw,
    ]);
  }


}