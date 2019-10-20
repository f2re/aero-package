<?php

namespace F2re\Aero;

use Illuminate\Support\Facades\Cache;

/**
 * Класс, который будет возвращать станции 
 * парсить файл со станциями
 */
class Stantions
{

  /**
   * Stantions path to file
   * @var null
   */
  private $_file = __DIR__.'/stantions/stantions.csv';

  /**
   * Stantions array
   * @var array
   */
  private $_stantions=[];


  /**
   * initialize Stantions class
   */
  public function __construct(){

    if (Cache::has('f2reaero_stantions')) {
      $this->_stantions = Cache::get('f2reaero_stantions');
    }else{
      // read stantions from file
      $stantions_tmp = $this->readCSV( $this->_file, ['delimiter'=>';'] );
      $retstantions  = [];
      for ($i=count($stantions_tmp)-1; $i >0 ; $i--) { 
        $a = $stantions_tmp[$i];
        if (trim($a[0])==''){
          continue;
        }
        $retstantions[ $a[0] ] = [  'id'          => $a[0],
                                    'name'        => $a[1],
                                    'name_en'     => $a[7],
                                    'country'     => $a[2],
                                    'lat'         => $a[3],
                                    'lon'         => $a[4]
                                    ];
      }
      // save to instanse
      $this->_stantions = $retstantions;
      // save to cache
      Cache::forever('f2reaero_stantions', $retstantions);
    }
  }

  /**
   * Get stantion by stantion id
   * @param  [type] $id [description]
   * @return [type]     [description]
   */
  public function get_stantion($id){
    if ( count($this->_stantions)==0 ){
      return false;
    }
    if ( isset($this->_stantions[$id]) ){
      return $this->_stantions[$id];
    }else{
      return false;
    }
  }

  /**
   * read csv file with assigned delimiter
   * @param  [type] $csvFile [description]
   * @param  [type] $array   [description]
   * @return [type]          [description]
   */
  public function readCSV($csvFile, $array)
  {
    $file_handle = fopen($csvFile, 'r');
    while (!feof($file_handle)) {
        $line_of_text[] = fgetcsv($file_handle, 0, $array['delimiter']);
    }
    fclose($file_handle);
    return $line_of_text;
  }

}