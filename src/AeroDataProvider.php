<?php

namespace F2re\Aero;
use Illuminate\Support\Facades\Cache;

/**
 * Класс, который будет загружать данные по станции
 */
class AeroDataProvider
{
  /**
   * Stantion array
   * @var array
   */
  private $_stantion=Null;

  /**
   * Date to request data
   * @var null
   */
  private $_date = Null;

  /**
   * array of data
   * @var array
   */
  private $_data = [];

  /**
   * current URL 
   * @var string
   */
  private $_url = 'http://www.rap.ucar.edu/weather/upper/current.rawins';

  /**
   * initialize Stantions class
   */
  public function __construct(){
    $curdate   = date('Y-m-d');

    $opts = array(
      'http'=>array(
        'method'=>"GET",
        'header'=>"Accept-language: en\r\n" .
                  "Cookie: foo=bar\r\n"
      )
    );

    $context = stream_context_create($opts);


    if (Cache::has('f2reaero_cachedate') && Cache::get('f2reaero_cachedate')==$curdate ) {
      $prepared = Cache::get('f2reaero_data');
    }else{
      $content = file_get_contents($this->_url, false,  $context);
      $content = explode("\x01", $content);

      $prepared = [];
      for ($i=0; $i < count($content); $i++) { 
        if ( strlen($content[$i])>133 && mb_strstr($content[$i], "TTAA") ){
          if (preg_match_all('/(?=TTAA|TTBB).*?(?==)/s', $content[$i], $match) ) {
            for ($j=0; $j < count($match[0]); $j++) { 
              if ( strlen($match[0][$j])<50 ){
                continue;
              }
              $TT = preg_replace('!\s+!', ' ',$match[0][$j]);
              $dt = substr($TT, 5,4);
              $st = substr($TT, 11,5);
              if ( (int)$dt[0]>3 ){
                $dt[0] = $dt[0]-5;
              }
              $prepared[$st][ $dt ] = ( isset($prepared[$st])&&isset($prepared[$st][$dt])?$prepared[$st][$dt]:'' ).$TT.'= ';
            }
          }
        }
      }

      Cache::forever('f2reaero_cachedate', $curdate);
      Cache::forever('f2reaero_data', $prepared);
    }

    $this->_data = $prepared;

  }

  /**
   * get data for stantion
   * @param  [type] $stantion [description]
   * @return [type]           [description]
   */
  public function get_data($stantion){
    if( isset( $this->_data[$stantion] ) ){
      $keys = array_keys($this->_data[$stantion]);
      $key = array_shift($keys);
      return $this->_data[$stantion][$key];
    }
  }

  /**
   * get avalilable stantions
   * @return [type] [description]
   */
  public function get_available(){
    return array_keys($this->_data);
  }

}