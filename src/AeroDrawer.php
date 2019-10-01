<?php

namespace F2re\Aero;

use CpChart\Data;
use CpChart\Image;
use CpChart\Draw;

/**
 * класс, который будет раскодировать телеграмму (текст)
 */
class AeroDrawer
{
  
  /**
   * KN04 code variable
   * @var null
   */
  private $_kn = Null;

  /**
   * Flatten array by $_kn
   * @var null
   */
  private $_values = Null;

  /**
   * Heights 
   * @var null
   */
  private $_H = Null;

  /**
   * Image data variable
   * @var Data
   */
  private $_data = new Data();

  /**
   * Image variable
   * @var null
   */
  private $_image = Null;

  /**
   * Plot size [0] - X, [1] - Y
   * @var [type]
   */
  private $_plotsize = [1900, 1300];

  /**
   * Stantion info variable
   * @var null
   */
  private $_stinfo = Null;


  /**
   * Image shadow params
   * @var array
   */
  private $_shadow = array("X"=>-1,"Y"=>1,"R"=>1,"G"=>0,"B"=>0,"Alpha"=>20);


  /**
   * Base font size
   * @var integer
   */
  private $_baseSize = 12;

  /**
   * Font path
   * @var string
   */
  private $_fontPath = __DIR__."/fonts/";

  /**
   * Font name
   * @var string
   */
  private $_font = "DejaVuSans-ExtraLight.ttf";

  /**
   * Instantiate a new AeroDrawer instance.
   */
  public function __construct( &$kn, $stantionInfo ){
    // setting the KN04 code to class
    $this->setKn($kn);

    // set up stantion info
    $this->setStantion($stantionInfo);
  }

  /**
   * Set Up KN04 code
   * @param [type] $kn [description]
   */
  public function setKn($kn){

    $this->_kn = $kn;

    $this->_values = $this->_kn->to_array();

    // if data not exists
    if ( !$this->checkData() ){
      return false;
    }

    $this->_H = array();

    foreach ($this->_values['std'] as $P => $val) {
      if ( isset($val['H']) && $val['H']!='' ) {
        array_push($this->_H, $val['H']);
      }
    }

    return true;
  }

  /**
   * Set Up KN04 code
   * @param [type] $kn [description]
   */
  public function setStantion($stantionInfo){
    $this->_stinfo = $stantionInfo;
    return $this;
  }

  /**
   * Checking the data
   * @return [type] [description]
   */
  public function checkData(){
    if ( !is_array($this->_values['std']) ) {
      return false;
    }
    return true;
  }

  /**
   * return plot size X
   * @return [int] [description]
   */
  public function getPlotX(){
    return $this->_plotsize[0];
  }

  /**
   * return plot size Y
   * @return [int] [description]
   */
  public function getPlotY(){
    return $this->_plotsize[1];
  }

  /**
   * рассчитываем координаты для кривой годске (кривая насыщения надо льдом)
   * @param  [type]  $val [description]
   * @param  integer $max [description]
   * @return [type]       [description]
   */
  function godske($val,$max=-80){
    $ret = -8*$val;
    if ( $ret<=-80 ){
        $ret=-80;
    }
    return $ret;
  }


  /**
   * Рисуем диаграмму 
   */
  function init(){
      
    /* Create your dataset object */ 
    $this->_data = new Data(); 

    /* Add data in your dataset */ 
    $tempArray = array();
    for ( $i=-90;$i<=50;$i+=1 ) {
      array_push($tempArray, $i);
    }
    $this->_data->addPoints($tempArray,'Temperature');
    $this->_data->setXAxisUnit("°");
    $this->_data->setAbscissa("Temperature"); 

    /* Create a pChart object and associate your dataset */ 
    $this->_image = new Image($this->getPlotX,$this->getPlotY,$this->_data);

    /* Choose a nice font */
    $this->_image->setFontProperties(array("FontName"=>$this->_fontPath.$this->_font,"FontSize"=>$this->_baseSize));

    /* Define the boundaries of the graph area */
    $this->_image->setGraphArea( (int)( $this->getPlotX*0.05 ),
                                 (int)( $this->getPlotY*0.02 ),
                                 (int)( $this->getPlotY*0.80 ),
                                 (int)( $this->getPlotY*0.95 ));

    /**
     * Инициализируем подложку
     */
    $this->_data->addPoints($this->_H,"StdSurface");
    $this->_data->setSerieDescription("StdSurface",0);
    $this->_data->setAxisDisplay(0,AXIS_FORMAT_DEFAULT);
    $this->_data->setAxisUnit(0,"м");

    /* Draw the scale, keep everything automatic */ 
    $this->_image->drawScale( array("Factors"          => array(1),
                                    "Pos"              => SCALE_POS_LEFTRIGHT,
                                    "LabelSkip"        => 9,
                                    "YMargin"          => 20,
                                    'GridTicks'        => 9,
                                    'GridAlpha'        => 30,
                                    'GridR'            => 100,
                                    'GridG'            => 0,
                                    'GridB'            => 0,
                                    'DrawYLines'       => TRUE, //вертикальная сетка
                                    "LabelingMethod"   => LABELING_ALL,
                                    "Mode"             => SCALE_MODE_START0 ) );

    $this->drawBlank();
    return $this;
  }

  /**
   * Draw blank page
   * @return [type] [description]
   */
  public function drawBlank(){
    /**
     * *                                        * *
     * *            П О Д Л О Ж К А             * *
     * *                                        * *
     */

    //рисуем линии давления
    $this->_image->drawThreshold(0,array(
                                          "R"         => 120,
                                          "G"         => 50,
                                          "B"         => 0,
                                          "Alpha"     => 20,
                                          "Ticks"     => 5,
                                          'NoMargin'  => TRUE));

    foreach ( $this->_values['std'] as $P => $val ){
        if ( isset($val['H'])&&$val['H']!=''&&$P>0 ){
          // http://wiki.pchart.net/doc.doc.draw.threshold.html
          $this->_image->drawThreshold( $val['H'],
            array(  "R"            => 0,
                    "G"            => 0,
                    "B"            => 0,
                    'BoxR'         => 255,
                    'BoxG'         => 255,
                    'BoxB'         => 255,
                    'BoxAlpha'     => 100,
                    'CaptionR'     => 100,
                    'CaptionG'     => 100,
                    'CaptionB'     => 150,
                    "Alpha"        => 50,
                    "Ticks"        => 9,
                    'NoMargin'     => TRUE,
                    'Caption'      => $P,
                    'WriteCaption' => TRUE));

          $this->_image->drawThreshold( $val['H'],
            array( "Alpha"         => 0,
                   'Caption'       => $val['H'].'м',
                   'BoxR'          => 220,
                   'BoxG'          => 220,
                   'BoxB'          => 250,
                   'BoxAlpha'      => 90,
                   'CaptionAlpha'  => 90,
                   'CaptionR'      => 100,
                   'CaptionG'      => 100,
                   'CaptionB'      => 150,
                   'CaptionOffset' => 50,
                   'WriteCaption'  => TRUE));
        }
    }

    /**
     * рисуем изограммы
     */
    $SIcolors = array( "R"     => 150,
                       "G"     => 80,
                       "B"     => 0,
                       "Ticks" => 0,
                       'Alpha' => 20 );

    for ($i=-50; $i <= 40; $i+=10) { 
        $this->_image->drawChartLine( $i, 0, $i-9.8*(abs(9+$i/10)*1.0204), (abs(9+$i/10)*1.0204)*1000,  $SIcolors);
    }

    //влажные адиабаты
    $Acolors = array( "R"     => 20,
                      "G"     => 120,
                      "B"     => 20,
                      "Ticks" => 15,
                      'Alpha' => 30 );
    for ($i=-50; $i<50 ; $i=$i+10) { 
        $points = $this->_kn->getVlagAdiabat($i,35);
        $this->_image->drawChartSpline( $this->_kn->averageSpline2($points,4), $Acolors );    
    }


    //название бланка
    $TextSettingsINDEX = array( "DrawBox"  => FALSE,
                                "R"        => 250,
                                "G"        => 100,
                                "B"        => 100,
                                'Alpha'    => 90,
                                "FontSize" => 15);
    $this->_image->drawText( 
                      $this->_image->toX( -33 ), 
                      55 , 
                      $this->_stinfo['name'].' ('.$this->_stinfo['name_en'].', '.$this->_stinfo['id'].') '.
                      $this->_kn->_date.' '.$this->_kn->_utc.'.00 (UTC)' ,
                      $TextSettingsINDEX);

    return $this;
  }

  /**
   * Draw inversions line
   * @return [type] [description]
   */
  public function drawInversions(){
    /**
     * *                                        * *
     * *         I N V E R S I O N S            * *
     * *                                        * *
     */

    //находим инверсии
    $inversion          = $this->_kn->getAllInversion();
    $step               = 20; //длина линии
    $startT             = 0; //правая часть температуры
    $TextSettings       = array( "DrawBox"=>FALSE, "R"=>0,  "G"=>0, "B"=>0, "Angle"=>0, "FontSize"=>$this->_baseSize, 'Alpha'=>90);
    $TextSettingsT      = array( "DrawBox"=>FALSE, "R"=>110,  "G"=>10,  "B"=>0, "Angle"=>0, "FontSize"=>$this->_baseSize, 'Alpha'=>90);
    $TextSettingsTROPOT = array( "DrawBox"=>FALSE, "R"=>110,  "G"=>10,  "B"=>0, "Angle"=>0, "FontSize"=>$this->_baseSize, 'Alpha'=>90);
    $theight            = 60; //высота надписи
    $TextSettings2      = array( "DrawBox"=>FALSE,"R"=>100,"G"=>100,"B"=>150, 'Alpha'=>90,"FontSize"=>$this->_baseSize);
    $Icolors            = array( "R"=>220,"G"=>220,"B"=>50,"Ticks"=>0 );
    $TROPOcolors        = array( "R"=>110,"G"=>10,"B"=>0,"Ticks"=>0 ); //цвета тропопаузы
    $tropoH             = 5500;//высота выше которой инверсии считаем тропопаузами
    $polygon            = array( "R"=>220,"G"=>220,"B"=>50,"Alpha"=>30,
                                 "Dash"=>TRUE,"DashR"=>170,"DashG"=>220,"DashB"=>190,
                                 "BorderR"=>255, "BorderG"=>255,"BorderB"=>255);

    $polygonTROPO       = array( "R"=>110,"G"=>20,"B"=>20,"Alpha"=>25,
                                 "Dash"=>TRUE,"DashR"=>170,"DashG"=>220,"DashB"=>190,
                                 "BorderR"=>255, "BorderG"=>255,"BorderB"=>255);
    $istropo            = false;

    // 
    // проходимся по всем инверсиям
    // 
    foreach ($inversion as $val) {
        // если это тропопауза - то включаем режим тропопаузы
        if ( $val['tropo'] ){
            $istropo=true;
        }

        $startT = $val['stop']['T']>$val['start']['T'] ? $val['stop']['T'] : $val['start']['T'] ;

        $this->_image->drawChartLine( $val['start']['T'], 
                                      $val['start']['H'], 
                                      $startT+$step,$val['start']['H'],  
                                      $istropo? $TROPOcolors :$Icolors);

        $this->_image->drawChartLine( $val['stop']['T'],
                                      $val['stop']['H'],   
                                      $startT+$step,$val['stop']['H'],  
                                      $istropo? $TROPOcolors :$Icolors);

        $dH = ceil(  $val['stop']['H']-$val['start']['H']    ); //высота инверсионного слоя
        $dT = round( $val['stop']['T']-$val['start']['T'], 1 ); //разность температур

        if ( $istropo ){
            $this->_image->drawText( $this->_image->toX( $startT+($step - 17 )/2 ),       
                                     $this->_image->toY( $val['start']['H']+($dH-$theight*5)/2 ), 
                                     'ТРОПОПАУЗА',
                                     $TextSettingsTROPOT );

            $this->_image->drawText( $this->_image->toX( $startT+($step - 7 )/2 ),
                                     $this->_image->toY( $val['start']['H']+$theight*2*( $dH<700?-1.8:1 ) ), 
                                     'T='.round($val['start']['T'],1).'°',
                                     $TextSettingsT );

            $this->_image->drawText( $this->_image->toX( $startT+$step+1 ), 
                                     $this->_image->toY( $val['start']['H']-$theight ), 
                                     round( $val['start']['H'] ).'м' ,
                                     $TextSettings2);
        }else{
            $this->_image->drawText( $this->_image->toX( $val['stop']['T']+2 ),
                                     $this->_image->toY( $val['start']['H']+$dH/2-$theight ), 
                                     'dT='.$dT.'° dH='.$dH.'м ',
                                     $TextSettings );

            $this->_image->drawText( $this->_image->toX($val['stop']['T']+$step+1 ), 
                                     $this->_image->toY( $val['start']['H']-$theight ), 
                                     round( $val['start']['H'] ).'м' ,
                                     $TextSettings2);
        }

        $Points = array(  );
        //рисуем против часовой стрелки с верхнего левого угла
        //первая точка - левая верхняя
        $Points[] = $this->_image->toX( $val['stop']['T'] );  
        $Points[] = $this->_image->toY( $val['stop']['H'] );
        //проверяем есть ли слева промежуточные точки (чтобы заполнить ломаные кривые)
        if ( isset($val['vals']) && sizeof($val['vals'])>0 ){
            $size = sizeof($val['vals'])-1; //в обратном порядке идем против часовой стрелки сверху вниз
            for ( $i=$size; $i>=0; $i--) {
                $Points[] = $this->_image->toX( $val['vals'][$i]['T'] );  
                $Points[] = $this->_image->toY( $val['vals'][$i]['H'] );   
            }                
        }
        $Points[] = $this->_image->toX( $val['start']['T'] );  
        $Points[] = $this->_image->toY( $val['start']['H'] );
        $Points[] = $this->_image->toX( $startT+$step );  
        $Points[] = $this->_image->toY( $val['start']['H'] );
        //последнняя точка 
        $Points[] = $this->_image->toX( $startT+$step );  
        $Points[] = $this->_image->toY( $val['stop']['H'] );
        
        $this->_image->drawPolygon( $Points, $istropo? $polygonTROPO :$polygon);

        $istropo = false;
    }

    return $this;
  }


  /**
   * Draw tempereature chart
   * @return [type] [description]
   */
  public function drawTemp(){
    //включаем тени
    $this->_image->setShadow(TRUE,$this->_shadow );
    /***                                        ***
     * *                                        * *
     * *       T E M P E R A T U R E            * *
     * *                                        * *
     ***                                        ***/

     /**
      * рисуем график температуры
      * дефицита точки росы
      * и кривую насыщения надо льдом
      */
    $Tcolors   = array( "R"=>255,"G"=>50,"B"=>50,"Ticks"=>0 );
    $Dcolors   = array( "R"=>50,"G"=>180,"B"=>50,"Ticks"=>0 );
    $Gcolors   = array( "R"=>50,"G"=>50,"B"=>255,"Ticks"=>5 );
    $Tprev     = array();
    $allValues = $this->_kn->mergedarray;
    $points    = array();

    foreach ( $allValues as $P => $val ){
      if ( !isset($val['H'])||trim($val['H'])==''){
        $val['H'] = $this->_kn->PtoH($val['P']);
      }

      if ( isset($val['H']) && $val['H']!='' && $P>0 && isset($val['T']) && isset($val['D']) ){
          
        if ( isset($Tprev['T']) && isset($Tprev['H']) && $Tprev['H']!=$val['H'] ){
          $this->_image->drawChartLine( $Tprev['T'],
                                        $Tprev['H'], 
                                        $val['T'],
                                        $val['H'],  
                                        $Tcolors);
          if ( $Tprev['D']!=null && $val['D']!=null ){

            $this->_image->drawChartLine( $Tprev['T']-$Tprev['D'],
                                          $Tprev['H'], 
                                          $val['T']-$val['D'],
                                          $val['H'],  
                                          $Dcolors);
            // заодно нариуем кривую Годске
            if ( $val['H'] <= 3000 ){
              $this->_image->drawChartLine( $this->godske($Tprev['D']) ,
                                            $Tprev['H'], $this->godske($val['D']) ,
                                            $val['H'],  
                                            $Gcolors);
            }
          }
        }
        $Tprev   = array( 'H'=>$val['H'], 'T'=>$val['T'],'D'=>$val['D'] );                
      }
    }
    // print_r($val);
    //рисуем уровень -20
    $T20              = $this->_kn->getHforT(-20);
    $T10              = $this->_kn->getHforT(-10);
    $T0               = $this->_kn->getHforT(0);
    $Tcolors['Alpha'] = 40;
    $step             = 20; //длина линии
    if ((int)$T20>0) {
      $this->_image->drawChartLine( -20,$T20, -20+$step,$T20,  $Tcolors);
    }
    if ((int)$T10>0) {
      $this->_image->drawChartLine( -10,$T10, -10+$step,$T10,  $Tcolors);
    }
    if ((int)$T0>0)  {
      $this->_image->drawChartLine( 0,$T0,      0+$step,$T0,  $Tcolors);
    }

    $this->_image->setShadow(FALSE);

    return $this;
  }


  /**
   * Draw isoterm level lines
   * @return [type] [description]
   */
  public function drawIsoterm(){
    /***                                        ***
     * *                                        * *
     * *             I S O T E R M              * *
     * *                                        * *
     ***                                        ***/

    //-20
    $TextSettings = array("DrawBox"=>FALSE,"R"=>255,"G"=>0,"B"=>0,"Angle"=>0,"FontSize"=>13,'Alpha'=>90);
    $TextSettings2 = array("DrawBox"=>TRUE,"BoxRounded"=>TRUE,"R"=>50,"G"=>50,"B"=>150, 'Alpha'=>100,"FontSize"=>10,'BoxR'=>220,'BoxG'=>220,'BoxB'=>250,'BoxAlpha'=>80,'RoundedRadius'=>3);
    if ((int)$T20>0){
        $this->_image->drawText( $this->_image->toX(-20 + $step +1), $this->_image->toY($T20-85),"T=-20°",$TextSettings);
        $this->_image->drawText( $this->_image->toX(-20 + $step +8 ), $this->_image->toY($T20-45), round($T20).'м' ,$TextSettings2);
    }
    //-10
    if ((int)$T10>0){
        $this->_image->drawText( $this->_image->toX(-10+ $step +1), $this->_image->toY($T10-85), "T=-10°", $TextSettings);
        $this->_image->drawText( $this->_image->toX(-10+ $step +8), $this->_image->toY($T10-45), round($T10).'м' , $TextSettings2);
    }
    //0
    if ((int)$T0>0){
        $this->_image->drawText( $this->_image->toX($step+ 1), $this->_image->toY($T0-85),"T=0°",$TextSettings);
        $this->_image->drawText( $this->_image->toX($step+ 8), $this->_image->toY($T0-45), round($T0).'м' ,$TextSettings2);
    }
    //рисуем уровень конденсации
    $uk = $this->_kn->getUK();

    return $this;
  }
    

  /**
   * Draw wind data
   * @return [type] [description]
   */
  public function drawWind(){

    /***                                        ***
     * *                                        * *
     * *          W I N D S T R E A M           * *
     * *                                        * *
     ***                                        ***/

    $this->_image->setShadow(FALSE );  

    //рисуем струйные течения
    $stream      = array();
    $strBegin    = false;
    $strPrev     = array();
    $polygonWIND = array( "R"=>20,"G"=>20,"B"=>150,"Alpha"=>10,
                          "Dash"=>TRUE,"DashR"=>170,"DashG"=>220,"DashB"=>190,
                          "BorderR"=>255, "BorderG"=>255,"BorderB"=>255);
    $maxWind     = array();

    foreach ($this->_kn->getmergedarray() as $key => $value) {
      if ( isset($value['FFF']) ){
        if ( !isset($value['H'])&&isset($value['P']) ) $value['H']=$kn->PtoH($value['P']);
        
        //максимальный ветер
        if ( !isset($maxWind['FFF']) || $value['FFF']>$maxWind['FFF'] ) {
          $maxWind['FFF'] = $value['FFF'];
          $maxWind['DD']  = $value['DD'];
          $maxWind['H']   = isset($value['H'])?$value['H']:$kn->PtoH($value['P']);
        }

        if ( $strBegin && $value['FFF']<28 
            && ( isset($value['H']) || isset($value['P']) ) 
            && ( isset($strPrev['H']) || isset($strPrev['P']) ) 
           ){
          $strBegin = false;
          if ( $strPrev['FFF']==$value['FFF'] || $strPrev['FFF']==28 ){
            $stream['end'] = round(   $strPrev['H']  ) + 0.01;
          }else{
            $stream['end'] = round(   $strPrev['H'] + ( ($strPrev['FFF'] - 28) *  ( ($value['H'] - $strPrev['H']) / ( $strPrev['FFF']-28 ) ) ) ) + 0.01;    
          }
        }

        if ( !$strBegin && $value['FFF']>=28 
             && (isset($value['H'])||isset($value['P'])) 
             && (isset($strPrev['H']) || isset($strPrev['P']) ) ){
          $strBegin = true;
          if ( $strPrev['FFF'] == $value['FFF'] ){
            $stream['begin'] = round(   $strPrev['H']  ) + 0.01;
          }else{
            $stream['begin'] = round(   $strPrev['H'] + ( ($strPrev['FFF'] - 28) *  ( ($value['H'] - $strPrev['H']) / ($strPrev['FFF']-28) ) ) ) + 0.01;
          }
        }
        
        if ( (isset($value['H'])&&$value['H']>0) || (isset($value['P'])&&$value['P']>0) ){
          $strPrev = $value;
        }
      }
    }

    // рисуем текст максимального ветра
    if ( sizeof($maxWind)>0 ){
      $TextSettingsWIND = array( "DrawBox"=>TRUE,"BoxRounded"=>TRUE,
                                 "R"=>20,"G"=>20,"B"=>200, 'Alpha'=>100,
                                 "FontSize"=>10,'RoundedRadius'=>5,
                                 'BoxR'=>255,'BoxG'=>255,'BoxB'=>255,'BoxAlpha'=>90);
      if ( isset($maxWind['FFF']) ){
        $x = $this->_image->toX(40);
        $y = $this->_image->toY($maxWind['H']);
        $this->_image->drawText( $x, $y-15, 'Max '.(int)$maxWind['H'].'м' ,$TextSettingsWIND);
        $this->_image->drawText( $x, $y+3,  $maxWind['DD'].'° - '.(int)($maxWind['FFF']*3.6) ,$TextSettingsWIND);
      }
    }


    if ( isset($stream['begin']) && isset($stream['end']) ){
        $Points   = array();
        $step     = 15;
        $Points[] = $this->_image->toX(50);
        $Points[] = $this->_image->toY($stream['end']);
        $Points[] = $this->_image->toX(50);
        $Points[] = $this->_image->toY($stream['begin']);
        $Points[] = $this->_image->toX(50+$step);
        $Points[] = $this->_image->toY($stream['begin']);
        $Points[] = $this->_image->toX(50+$step);
        $Points[] = $this->_image->toY($stream['end']);
        $this->_image->drawPolygon( $Points, $polygonWIND );
    }

    //рисуем ветер по высотам
    $x                        = $this->_image->toX(50)+60; //на одной линии вверх рисуем
    $imgHeight                = 14;
    $prev                     = array();
    $TextSettings2['DrawBox'] = FALSE;
    $xOffset                  = 0;

    foreach ($kn->getmergedarray() as $key => $value) {
      if ( isset($value['FFF']) && isset($value['DD']) ){
        if ( !isset($value['H']) ) {
          $value['H']=$kn->PtoH($value['P']);
        }
        if ( isset($prev['H']) && ( $value['H'] - $prev['H'] <200 )   ) {
            continue;
        }
        if ( $value['DD']>30 && $value['DD']<150 ) {
          $xOffset=63;
        }
        $y =  $this->_image->toY( $value['H'] )-$imgHeight;
        $this->_image->drawText( $x+10-$xOffset, $y+5, $value['DD'].'° - '. (int)($value['FFF']*3.6) ,$TextSettings2);
        $this->_image->drawWind( $value['FFF'],$value['DD'],$x,$y );
        
        $prev = $value;
        $xOffset=0;
      }
    }

    return $this;
  }


  /**
   * Draw spline of sostoyan
   * @return [type] [description]
   */
  public function drawSost(){

    /***                                        ***
     * *                                        * *
     * *     К Р И В А Я   С О С Т О Я Н И Я    * *
     * *                                        * *
     ***                                        ***/
    //включаем тени
    $this->_image->setShadow( TRUE, $this->_shadow );  

    //рисуем кривую состояния
    $start     = $this->_values['std']['surface'];
    $inversion = $this->_kn->getAllInversion();

    if ( is_array($inversion)
         && isset($inversion[0]['start']['H'])
         && $inversion[0]['start']['H'] == $thi->_kn->PtoH($values['std']['surface']['P']) ){
        $start = $inversion[0]['stop'];
    }

    if ( (!isset($start['H']) || $start['H']=='' ) && isset($start['P']) ) {
      $start['H']=$this->_kn->PtoH($start['P']);
    }

    $sost     = $this->_kn->getSostSpline($uk,$start); //задаем уровень конденсации
    $points   = $this->_kn->averageSpline2($sost,8);
    $KScolors = array( "R"=>0,"G"=>0,"B"=>0,"Ticks"=>0,'Alpha'=>80 );
    $this->_image->drawChartSpline($points,$KScolors); 

    //рисуем сухую часть кривой состояния
    $point1 = reset($points);
    $this->_image->drawChartLine( ($start['T']), ($start['H']), $point1[0],$point1[1],  $KScolors);

    return $this;
  }

      




      /***                                                  ***
       * *                                                  * *
       * *   Э Н Е Р Г И Я   Н Е У С Т О Й Ч И В О С Т И    * *
       * *                                                  * *
       ***                                                  ***/


      /**
       * закрашиваем регион с неустойчивостью или устойчивостью
       *    
       */   
      $polygonStable = array( "R"=>0,"G"=>100,"B"=>255,"Alpha"=>20);
      $polygonUNStable = array( "R"=>255,"G"=>30,"B"=>30,"Alpha"=>20,"Dash"=>TRUE,"DashR"=>170,"DashG"=>220,"DashB"=>190,"BorderR"=>255, "BorderG"=>255,"BorderB"=>255);
      //последнняя точка
      $PointsA = array();//точки на кривой состояния
      $PointsT = array();//точки на кривой температуры
      $PointsRes = array(); //итоговый массив
      $fH=null;
      $points=array_reverse($points);

      //точки на кривой состояния
      foreach ($points as $p) {
          if ( $fH==null ) $fH=$p[1];
          $PointsA[] = $p[0];//$this->_image->toX($p[0]+0.3);
          $PointsA[] = $p[1];//$this->_image->toY($p[1]);
      }
      $PointsA[] = $point1[0];
      $PointsA[] = $point1[1];
      $PointsA[] = $start['T'];
      $PointsA[] = $start['H'];
      // echo $start['T'].'/'.$start['H'];

      //точки на кривой температуры
      foreach ($allValues as $P => $val) {
          if ( !isset($val['H'])&&isset($val['P']) ) $val['H']=$kn->PtoH($val['P']);
          if ( isset($val['H']) && isset($val['T']) && $val['H']<$fH && $val['H']>=$start['H'] ){
             $PointsT[] = $val['T'];//$this->_image->toX($val['T']-0.1);
             $PointsT[] = $val['H'];//$this->_image->toY($val['H']); 

          }
      }
      //самая верхняя точка по срезу кривой состояния
      $PointsT[] = $kn->getT($fH);//$this->_image->toX($kn->getT($fH));
      $PointsT[] = $fH;//$this->_image->toY($fH); 

      //выделяем регионы с пересечением с кривой состояния и кривой стратификации
      $size = sizeof($PointsT);
      $prev=null;
      $stable = true;
      $j = 0; //указатель на адрес в итоговом массиве

      //проходимся сверху вниз по кривой температуры //кривой состояния
      for ($i=0; $i < $size ; $i+=2) { 
          $Tx= $kn->getPointOnSostSpline(null,$PointsT[$i+1]) ;//температура на высоте кривой стратификации
          if ( $PointsT[$i]>$Tx ){ //значит устойчиво true
              if ( !$stable && is_array($prev) ){ //если переходим из неустойчивости
                  //ищем точку пересечения температуры с кривой стратификации
                  //по подобным треугольникам (в приближении)
                  $y1 = $PointsT[$i+1];
                  $x1 = $PointsT[$i];

                  $y2 = $prev[1];
                  $x2 = $prev[0];

                  $a1 = ($y1-$y2);
                  $b1 = $x2-$x1;
                  $c1 = $x1*$y2 - $x2*$y1;
                  // echo 'x1:'.ceil($x1).', y1:'.ceil($y1).' x2:'.ceil($x2).', y2:'.ceil($y2).' a:'.$a1.', b:'.$b1.'  & ';
                  //для координат кривой температуры
                  $x1 = $Tx;
                  $x2 = $prev[2];
                  $a2 = ($y1-$y2);
                  $b2 = $x2-$x1;
                  $c2 = $x1*$y2 - $x2*$y1;
                  
                  //точка пересечения прямых
                  ////высота смены курса
                  $y12 = -($a1*$c2 - $a2*$c1)/($a1*$b2 - $a2*$b1);
                  $x12 = $kn->getT($y12);//-($x1*$b2 - $c2*$b1)/($a1*$b2 - $a2*$b1);
                  
                  $PointsRes[$j]['res'][]=($x12);
                  $PointsRes[$j]['res'][]=($y12);
                  // echo 'dy='.$PointsA[$i+1].'-'.$prev[1].'='.$dy.'; dx='.$prev[2].'-'.$Tx.'='.$dx.'/';
                  $j++;//завершаем секцию
                  $PointsRes[$j]['res'][]=($x12);
                  $PointsRes[$j]['res'][]=($y12);
                  $PointsRes[$j]['res'][]=($PointsT[$i]);
                  $PointsRes[$j]['res'][]=($PointsT[$i+1]);
                  $PointsRes[$j]['type']='stable';
              }
              if ( $stable ){ //если все усточиво - продолжаем массив с точками заполнять
                  $PointsRes[$j]['res'][]=($PointsT[$i]);
                  $PointsRes[$j]['res'][]=($PointsT[$i+1]);
                  $PointsRes[$j]['type']='stable';
              }
              $stable=true;
              $PointsRes[$j]['type']='stable';
          }else{ //неусточиво false
              if ( $stable && is_array($prev) ){ //если переходим из устойчивости
                  //ищем точку пересечения температуры с кривой стратификации
                  //по подобным треугольникам (в приближении)
                  $y1 = $PointsT[$i+1];
                  $x1 = $PointsT[$i];

                  $y2 = $prev[1];
                  $x2 = $prev[0];

                  $a1 = ($y1-$y2);
                  $b1 = $x2-$x1;
                  $c1 = $x1*$y2 - $x2*$y1;
                  // echo 'x1:'.ceil($x1).', y1:'.ceil($y1).' x2:'.ceil($x2).', y2:'.ceil($y2).' a:'.$a1.', b:'.$b1.'  & ';
                  //для координат кривой температуры
                  $x1 = $Tx;
                  $x2 = $prev[2];
                  $a2 = ($y1-$y2);
                  $b2 = $x2-$x1;
                  $c2 = $x1*$y2 - $x2*$y1;
                  
                  //точка пересечения прямых
                  ////высота смены курса
                  $y12 = -($a1*$c2 - $a2*$c1)/($a1*$b2 - $a2*$b1);
                  $x12 = $kn->getT($y12);//-($x1*$b2 - $c2*$b1)/($a1*$b2 - $a2*$b1);
                  $PointsRes[$j]['res'][]=($x12);
                  $PointsRes[$j]['res'][]=($y12);

                  $j++;//завершаем секцию
                  $PointsRes[$j]['res'][]=($x12);
                  $PointsRes[$j]['res'][]=($y12);
                  $PointsRes[$j]['res'][]=($PointsT[$i]+0.1);
                  $PointsRes[$j]['res'][]=($PointsT[$i+1]);
                  $PointsRes[$j]['type']='unstable';
              }
              if ( !$stable ){ //если все неусточиво - продолжаем массив с точками заполнять
                  $PointsRes[$j]['res'][]=($PointsT[$i]+0.1);
                  $PointsRes[$j]['res'][]=($PointsT[$i+1]);
                  $PointsRes[$j]['type']='unstable';
              }
              $stable=false;
              $PointsRes[$j]['type']='unstable';
          }
          $prev=array( $PointsT[$i],$PointsT[$i+1],$Tx );
      }

      //включаем тени
      $this->_image->setShadow(false,$shadow );   
      //заполняем снизу вверх точки температуры
      
      foreach ($PointsRes as $val) {
          if (!isset($val['res'])){
              continue;
          }
          $stop = $val['res'][1]; //верхняя граница для температуры
          $size = sizeof($val['res'])-1;
          $start = $val['res'][ $size ];//нижняя граница для температуры

          for ($i=0; $i < $size; $i+=2) { 
              $val['res'][$i] = $this->_image->toX($val['res'][$i]);
              $val['res'][$i+1] = $this->_image->toY($val['res'][$i+1]);
          }

          //дополняем массив точками на кривой состояния
          $size = sizeof($PointsA);
          for ($i=0; $i < $size ; $i+=2) { 
              if ( $PointsA[$i+1]<=$start && $PointsA[$i+1]>=$stop ){
                  $val['res'][] = $this->_image->toX($PointsA[$i]);
                  $val['res'][] = $this->_image->toY($PointsA[$i+1]);
              }
          }

          $val['res'][] = $this->_image->toX($kn->getT($stop));
          $val['res'][] = $this->_image->toY(($stop));
           // print_r($val);
          if ( $val['type']=='stable' ){
              $this->_image->drawPolygon($val['res'],$polygonStable);
          }else{
              $this->_image->drawPolygon($val['res'],$polygonUNStable);
          }
      }
      

      //включаем тени
      $this->_image->setShadow(TRUE,$shadow );   


      /***                                              ***
       * *                                              * *
       * *     У Р О В Е Н Ь  К О Н Д Е Н С А Ц И И     * *
       * *                                              * *
       ***                                              ***/

      //
      //рисуем уровень конденсации
      //
      $UKcolors = array( "R"=>20,"G"=>20,"B"=>150,"Ticks"=>0,'Alpha'=>80,'ShowControl'=>false,'Force'=>4, 'Segments'=>20 );//,'Segments'=>20
      $TextSettingsUK = array("DrawBox"=>FALSE,"R"=>20,"G"=>20,"B"=>150,"Angle"=>0,"FontSize"=>12,'Alpha'=>100,'BoxR'=>255,'BoxG'=>255,'BoxB'=>255,'BoxAlpha'=>50,'RoundedRadius'=>5);
      $points=array();
      $dX=18;
      if ( $point1[0]+$dX > 50){
          $dX= $point1[0]+$dX -50;
      }
          
      //первая точка - УК
      $points[] = array($point1[0], $point1[1]);
      //рисуем синусоиду
      $k=1;
      for ( $w = $point1[0]+0.3; $w<($point1[0]+$dX); $w+=1 ){            
          $points[] = array( $w, $point1[1]+50*$k );            
          $k=-$k;
      }
      $points[] = array( $point1[0]+$dX, $point1[1] );
      $this->_image->drawChartSpline($points,$UKcolors); 


      $this->_image->drawText( $this->_image->toX($point1[0]+$dX +1), $this->_image->toY($point1[1]-100), 'УК' ,$TextSettingsUK);
      $TextSettingsUK['FontSize']=11;
      $TextSettingsUK['DrawBox']=TRUE;

      //включаем тени
      $this->_image->setShadow(FALSE);    

      $this->_image->drawText( $this->_image->toX($point1[0]+$dX+4), $this->_image->toY($point1[1]-100), (int)($uk).'м' ,$TextSettingsUK);
      


      /***                                              ***
       * *                                              * *
       * *               О Б Л А Ч Н О С Т Ь            * *
       * *                                              * *
       ***                                              ***/
      $polygonCLOUD = array( "R"=>0,"G"=>100,"B"=>255,"Alpha"=>20,"Dash"=>TRUE,"DashR"=>170,"DashG"=>220,"DashB"=>190,"BorderR"=>255, "BorderG"=>255,"BorderB"=>255 );
      $TextSettingsCLOUD = array("DrawBox"=>TRUE,"BoxRounded"=>TRUE,"R"=>20,"G"=>20,"B"=>200, 'Alpha'=>90,"FontSize"=>9,'BoxR'=>255,'BoxG'=>255,'BoxB'=>255,'BoxAlpha'=>90,'RoundedRadius'=>2);

      $clouds = $kn->getCloudLayers();

      if ( sizeof($clouds)>0 ){
          $step = 10;
          $start =40;
          foreach ($clouds as $val) {
              $Points = array();                
              $Points[] = $this->_image->toX($start);  
              $Points[] = $this->_image->toY($val['stop']);
              $Points[] = $this->_image->toX($start+$step);  
              $Points[] = $this->_image->toY($val['start']);
              $this->_image->drawFilledRectangle($Points[0],$Points[1],$Points[2],$Points[3],$polygonCLOUD );
              $cldName = ( $val['start']>0&&$val['start']<=2000? 'Sc' : ( $val['start']>2000&&$val['start']<=6000? 'Ac' : ($val['start']>6000?'Ci':'') ) );
              $this->_image->drawText( $this->_image->toX( $start+$step/2-1 ), $this->_image->toY( $val['start']+($val['stop']-$val['start'])/2-40 ), $cldName ,$TextSettingsCLOUD);
              $dy = ( $val['stop']-$val['start'] <500 ? -40 : 60 );
              $this->_image->drawText( $this->_image->toX( $start ), $this->_image->toY( $val['start']-$dy*( $dy<0?-4:1 ) ), ceil($val['start']).'м' ,$TextSettingsCLOUD);
              $this->_image->drawText( $this->_image->toX( $start ), $this->_image->toY( $val['stop']-$dy ), ceil($val['stop']).'м' ,$TextSettingsCLOUD);
          }
      }




      /***                                                   ***
       * *                                                   * *
       * *     И Н Д Е К С Ы  Н Е У С Т О Й Ч И В О С Т И    * *
       * *                                                   * *
       ***                                                   ***/
      $polygonINDEX = array("R"=>220,"G"=>220,"B"=>255,"Alpha"=>50,"Dash"=>TRUE,"DashR"=>170,"DashG"=>220,"DashB"=>190,"BorderR"=>120, "BorderG"=>120,"BorderB"=>120,"Ticks"=>5);
      $TextSettingsINDEX = array("DrawBox"=>FALSE,"R"=>20,"G"=>20,"B"=>200, 'Alpha'=>90,"FontSize"=>12);
      $TextSettingsINDEXR = array("DrawBox"=>FALSE,"R"=>200,"G"=>20,"B"=>20, 'Alpha'=>90,"FontSize"=>12);
      $TextSettingsMinINDEX = array("DrawBox"=>FALSE,"R"=>20,"G"=>20,"B"=>200, 'Alpha'=>90,"FontSize"=>9);
      $TextSettingsMinINDEXR = array("DrawBox"=>FALSE,"R"=>200,"G"=>20,"B"=>20, 'Alpha'=>90,"FontSize"=>9);

      $string_H = 22; //высота строки
      $box_START= 66; //начало по X
      $box_W    = 20; //ширина блока
      $offset   = 0;  //текущее смещение (сверху вниз)
      $start_Y  = 20;  //стартовая позиция для высоты

      //рисуем блок для параметров станции
      $strings = 7; //количество строк в блоке
      $this->_image->drawFilledRectangle( $this->_image->toX( $box_START ) ,            $start_Y+$offset ,
                                       $this->_image->toX( $box_START + $box_W ) ,   $start_Y+$offset+$strings*$string_H+10 ,
                                       $polygonINDEX );    
      $offset +=22;
      //@stantionInfo Array ( [id] => 27612 [name] => Москва [name_en] => Moscow [country] => 3472 [lat] => 55.83 [lon] => 37.61 [high] => 147 ) 
      $this->_image->drawText( $this->_image->toX( $box_START+1 ), $start_Y+$offset+3 ,             'Пункт:   ' ,$TextSettingsINDEX);
      $this->_image->drawText( $this->_image->toX( $box_START+10 ), $start_Y+$offset ,             $stantionInfo['name'] ,$TextSettingsINDEX);
      $offset +=$string_H;
      
      $this->_image->drawText( $this->_image->toX( $box_START+1 ), $start_Y+$offset+3 ,             'Станция:   ' ,$TextSettingsINDEX);
      $this->_image->drawText( $this->_image->toX( $box_START+10 ), $start_Y+$offset ,             $stantionInfo['id'] ,$TextSettingsINDEX);
      $offset +=$string_H;

      $this->_image->drawText( $this->_image->toX( $box_START+1 ), $start_Y+$offset+3,   'Дата:   ' ,$TextSettingsINDEX);
      $this->_image->drawText( $this->_image->toX( $box_START+10 ), $start_Y+$offset, $kn->_date ,$TextSettingsINDEX);
      $offset +=$string_H;

      $this->_image->drawText( $this->_image->toX( $box_START+1 ), $start_Y+$offset+3,    'Время:   ' ,$TextSettingsINDEX);
      $this->_image->drawText( $this->_image->toX( $box_START+10 ), $start_Y+$offset,   $kn->_utc.'.00' ,$TextSettingsINDEX);
      $offset +=$string_H;

      $this->_image->drawText( $this->_image->toX( $box_START+1 ), $start_Y+$offset+3,  'Широта:   ' ,$TextSettingsINDEX);
      $this->_image->drawText( $this->_image->toX( $box_START+10 ), $start_Y+$offset, $stantionInfo['lat'].'°' ,$TextSettingsINDEX);
      $offset +=$string_H;

      $this->_image->drawText( $this->_image->toX( $box_START+1 ), $start_Y+$offset+3,  'Долгота:   ' ,$TextSettingsINDEX);
      $this->_image->drawText( $this->_image->toX( $box_START+10 ), $start_Y+$offset, $stantionInfo['lon'].'°' ,$TextSettingsINDEX);
      $offset +=$string_H;

      $this->_image->drawText( $this->_image->toX( $box_START+1 ), $start_Y+$offset+3 , 'Превышение:   ' ,$TextSettingsINDEX);
      $this->_image->drawText( $this->_image->toX( $box_START+13 ), $start_Y+$offset , $stantionInfo['high'].'м' ,$TextSettingsINDEX);
      $offset += $string_H + 10;

      

      $drawText = array();

      $T850 = $kn->getT(null,850);
      $T500 = $kn->getT(null,500);
      $T700 = $kn->getT(null,700);
      $d850 = $kn->getParam('D',null,850);
      $d500 = $kn->getParam('D',null,500);
      $d700 = $kn->getParam('D',null,700);

      //KINX
      //Ki=T850-T500+Td850-∆Td700.
      // < 20                Без гроз
      // 20 ≤ Ki ≤ 25        Отдельные изолированные грозы
      // 25 < Ki ≤ 30        Несколько гроз
      // 30 < Ki ≤ 35        Рассеяные грозы
      // 35 < Ki ≤ 40        Многочисленные грозы
      // Ki >40              Грозы повсеместно
      $KINXRange = [
                  'range'=>[ 20, 25, 30, 35, 40 ],
                  'text' =>[
                          'Без гроз',
                          'Отдельные грозы',
                          'Несколько гроз',
                          'Рассеяные грозы',
                          'Многочисленные грозы',
                          'Грозы повсеместно',
                          ]
              ];

      $KINX = $T850 - $T500 + ( $T850 - $d850 ) - $d700;

      $drawText[] = array( 
          'index' => 'Ki (Вайтинга)', 
          'val'=>$KINX, 
          'font'=> ( $KINX<30?$TextSettingsINDEX:$TextSettingsINDEXR ),
          'str' => [
                          'txt' => $this->compareRanges( $KINX, $KINXRange['range'], $KINXRange['text'] ),
                          'font'=> ( $KINX<30?$TextSettingsMinINDEX:$TextSettingsMinINDEXR )   
                      ]  );
      

      // Фауста
      // 
      $FaustRange = [
                  'range'=>[ 20, 25, 30, 35, 40 ],
                  'text' =>[
                          'Без гроз',
                          'Отдельные грозы',
                          'Несколько гроз',
                          'Рассеяные грозы',
                          'Многочисленные грозы',
                          'Грозы повсеместно',
                          ]
              ];

      // $Tv = function( $T850, ($d850+$d700+$d500)/3 ){
      //     return $kn->getPointOnSostSpline(null,$kn->PtoH(850)) - $kn->getPointOnSostSpline(null,$kn->PtoH(500));
      // };
      // $Faust = $Tv() - $T500;

      // $Faust = $kn->getPointOnSostSpline(null,$kn->PtoH(500)) + $kn->getPointOnSostSpline(null,$kn->PtoH(850)) ;

      // $drawText[] = array( 
      //     'index' => 'F (Фауста) '.((int)($d850+$d700+$d500)/3), 
      //     'val'=>$Faust, 
      //     'font'=> ( $Faust<30?$TextSettingsINDEX:$TextSettingsINDEXR ),
      //     'str' => [
      //                     'txt' => $this->compareRanges( $Faust, $FaustRange['range'], $FaustRange['text'] ),
      //                     'font'=> ( $Faust<30?$TextSettingsMinINDEX:$TextSettingsMinINDEXR )   
      //                 ]  );

      //VT = T850 - T500
      //VT > 28, следовательно тропосфера обладает высоким потенциалом конвективной неустойчивости, достаточным для образования гроз.

      $VTRange = [
                  'range'=>[ 28 ],
                  'text' =>[
                          'Нет условий для гроз',
                          'Условия для грозообразования',
                          ]
              ];

      $VT = $T850 - $T500 ;   

      $drawText[] = array( 
          'index' => 'VT', 
          'val'=>$VT, 
          'font'=> ( $VT<28?$TextSettingsINDEX:$TextSettingsINDEXR ),
          'str' => [
                          'txt' => $this->compareRanges( $VT, $VTRange['range'], $VTRange['text'] ),
                          'font'=> ( $VT<28?$TextSettingsMinINDEX:$TextSettingsMinINDEXR )   
                      ]  );

      //CT = Td850 - T500
      //СT < 18 — Тропосфера обладает низким потенциалом конвективной неустойчивости, который недостаточен для грозовой деятельности.
      //CT 18 - 19 — Умеренная неустойчивость. Слабая грозовая деятельность.
      //CT 20 - 21 — Высокая неустойчивость. Грозы.
      //CT 22 - 23 — Энергия неустойчивости при которой возможны сильные грозы.
      //CT 24 - 25 — Высокая энергия неустойчивости. Сильные грозы.
      //СT> 25 — Очень высокая энергия неустойчивости. Очень сильные грозы.

      $CTRange = [
                  'range'=>[ 18, 20, 22, 25 ],
                  'text' =>[
                          'Без гроз',
                          'Слабые грозы',
                          'Грозы',
                          'Сильные грозы',
                          'Очень сильные грозы',
                          ]
              ];

      $CT = $T850 - $d850  - $T500;   

      $drawText[] = array( 
          'index' => 'CT', 
          'val'=>$CT, 
          'font'=> ( $CT<20?$TextSettingsINDEX:$TextSettingsINDEXR ),
          'str' => [
                          'txt' => $this->compareRanges( $CT, $CTRange['range'], $CTRange['text'] ),
                          'font'=> ( $CT<20?$TextSettingsMinINDEX:$TextSettingsMinINDEXR )   
                      ]    );
      
      //TOTL = VT + CT
      //TT < 44 — Грозовая деятельность не возможна.
      //TT 44 - 45 — Отдельная гроза или несколько гроз.
      //TT 46 - 47 — Рассеянные грозовые очаги.
      //TT 48 - 49 — Значительные количество гроз, отдельные из которых сильные.
      //TT 50 - 51 — Рассеянные сильные грозовые очаги, отдельные очаги со смерчем.
      //TT 52 - 55 — Значительное количество очагов сильных гроз, отдельные очаги со смерчем.
      //TT > 55 — Многочисленные сильные грозы с сильными смерчами. 
      // 
      $TOTL = $VT + $CT;   
      $TOTLRange = [
                          'range'=>[ 44, 46, 48, 50, 55 ],
                          'text' =>[
                                  'Без гроз',
                                  'Отдельные грозы',
                                  'Рассеянные грозы',
                                  'Значительные грозы',
                                  'Сильные грозовые очаги',
                                  'Сильные грозы со смерчем',
                                  ]
                      ];

      $drawText[] = array( 'index' => 'TOTL', 
                           'val'=>$TOTL, 
                           'font'=> ( $TOTL<48?$TextSettingsINDEX:$TextSettingsINDEXR ),
                           'str' => [
                                          'txt' => $this->compareRanges( $TOTL, $TOTLRange['range'], $TOTLRange['text'] ),
                                          'font'=> ( $TOTL<48?$TextSettingsMinINDEX:$TextSettingsMinINDEXR )   
                                      ]      );
      
      //SWEAT = 12⋅Td850 + 20⋅(TT- 49) + 2⋅F850 + 2⋅F500 + (125⋅[sin(D500 - D850)+0.2])
      //SWEAT < 250 — нет условий для возникновения сильных гроз;
      //SWEAT 250-350 — есть условия для сильных гроз, града и шквалов;
      //SWEAT 350-500 — есть условия для очень сильных гроз, крупного града, сильных шквалов, смерчей;
      //SWEAT ≥ 500 — условия для очень сильных гроз, крупного града, сильных шквалов, сильных смерчей.
      //
      $F850 =$kn->getParam('FFF',null,850);
      $F500 =$kn->getParam('FFF',null,500);
      $DD850 =$kn->getParam('DD',null,850);
      $DD500 =$kn->getParam('DD',null,500);
      $SWEAT = 12*($T850 - $d850) + 20*($TOTL-49) + 3.888*$F850 + 1.944*$F500 + (125* ( sin($DD500 - $DD850)+0.2 ) );

      $SWEATRange = [
                          'range'=>[ 250,350,500 ],
                          'text' =>[
                                  'Без гроз',
                                  'Сильные грозы',
                                  'Сильные грозы, град, шквал',
                                  'Очень сильные грозы',
                                  ]
                      ];

      $drawText[] = array( 
                      'index' => 'SWEAT', 
                      'val'=>$SWEAT, 
                      'font'=> ( $SWEAT<250?$TextSettingsINDEX:$TextSettingsINDEXR ),
                      'str' => [
                                  'txt' => $this->compareRanges( $SWEAT, $SWEATRange['range'], $SWEATRange['text'] ),
                                  'font'=> ( $SWEAT<250?$TextSettingsMinINDEX:$TextSettingsMinINDEXR )   
                               ]  );

      // Li — Lifted index
      // Li — Разница температур окружающего воздуха и некоторого единичного объёма, поднявшегося [адиабатически] от поверхности земли (или с заданного уровня) до уровня 500 гПа. Li рассчитывается с учётом вовлечения окружающего воздуха.
      // Li — характеризует термическую стратификацию атмосферы по отношению к вертикальным перемещениям воздуха. Если значения Li положительные, то атмосфера (в соответствующем слое) устойчива. Если значения Li отрицательные — атмосфера неустойчива.
      // Li ≥ 6 — Глубокая инверсия. Атмосфера очень устойчива. Развиты нисходящие движения воздуха.
      // 1 ≤ Li ≤ 5 — Устойчивое состояние атмосферы. Кучевая облачность хорошей погоды.
      // 0 ≥ Li ≥ -2 — Небольшая неустойчивость. Конвективная оьлачность с ливнями, при интенсивном дневном прогреве или в зоне атмосферного фронта — с грозами и градом.
      // -3 ≥ Li ≥ -5 — Умеренная неустойчивость. Сильные грозы.
      // Li ≤ -6 — Высокая неустойчивость. Очень сильные грозы. 
      // 
      // LI= Tc(500mb) - Tp(500mb) 
      // $Tw = $kn->std['surface']['T'];
      // $Td = $Tw - $kn->std['surface']['D'];
      // $Ew = 6.1078 * exp([(9.5939 * $Tw) - 307.004]/[(0.556 * $Tw) + 219.522]);
      // $E = $Ew - 0.35 * ($Td - $Tw);
      // $Tp = -1 * {[ln($E/6.1078) * 219.522] + 307.004} / {[ln($E/6.1078) * 0.556] - 9.59539};

      $LI= $T500  - $kn->getPointOnSostSpline(null,$kn->PtoH(500));

      $LIRange = [
                          'range'=>[ -6,-3,0, 5 ],
                          'text' =>[
                                  'Высокая неустойчивость',
                                  'Умеренная неустойчивость',
                                  'Небольшая неустойчивость',
                                  'Устойчивая атмосфера',
                                  'Очень устойчивая атмосфера',
                                  ]
                      ];

      $drawText[] = array( 
                          'index' => 'Li', 
                          'val'=>$LI, 
                          'font'=> ( $LI>-3?$TextSettingsINDEX:$TextSettingsINDEXR ),
                          'str' => [
                                      'txt' => $this->compareRanges( $LI, $LIRange['range'], $LIRange['text'] ),
                                      'font'=> ( $LI>-3?$TextSettingsMinINDEX:$TextSettingsMinINDEXR )   
                                   ]    );

      // Ti — Thompson index
      // Ti = Ki- Li. Ki — К-индекс (число Вайтинга), Li — Lifted index.
      // Ti < 25 — Без гроз.
      // TI 25 - 34 — Возможны грозы.
      // TI 35 - 39 — Грозы, местами сильные.
      // TI ≥ 40 — Сильные грозы.
      $TI = $KINX - $LI;

      $TIRange = [
                      'range'=>[ 25,34,40 ],
                      'text' =>[
                              'Без гроз',
                              'Возможны грозы',
                              'Грозы',
                              'Сильные грозы',
                              ]
                  ];

      $drawText[] = array( 
                      'index' => 'Ti', 
                      'val'=>$TI, 
                      'font'=> ( $TI<34?$TextSettingsINDEX:$TextSettingsINDEXR ),
                      'str' => [
                                  'txt' => $this->compareRanges( $TI, $TIRange['range'], $TIRange['text'] ),
                                  'font'=> ( $TI<34?$TextSettingsMinINDEX:$TextSettingsMinINDEXR )   
                               ]   );


      // SCS — * Экспериментальный индекс мощных конвективных штормов  (Severe Convective Storm)
      // SCS = 0.083*scpsfc+0.667*ui+0.5*mcsi+0.0025*sweat+0.025*ti
      // scpsfc – индекс SCP, с использованием sfcCAPE,
      // ui – индекс Пескова,
      // mcsi – индекс MCS,
      // sweat – индекс SWEAT,
      // ti – индекс Томпсона.
      // Интерпретация значений индекса SCS:
      // <1: развития мощных конвективных штормов (МКШ) не ожидается, местами возможны слабые грозы;
      // 1…2:МКШ маловероятны (вероятность приблизительно 10-20%). Возможны умеренные грозы с отдельными неблагоприятными явлениями (НЯ);
      // 2...3: небольшая вероятность МКШ (20-40%), условия для неблагоприятных конвективных явлений и гроз умеренной интенсивности;
      // 3...4: средняя вероятность МКШ (40-60%), возможны комплексы неблагоприятных явлений (КНЯ), местами опасные явления (ОЯ);
      // 4...5: большая вероятность развития МКШ (60 – 90%) и ОЯ;
      // >5: очень высокая вероятность (>90%) развития доминирующих устойчивых МКШ (в радиусе примерно до 100-150 км от максимальных значений индекса), комплекса особо разрушительных опасных явлений.

      $SCS = 0;//0.083*scpsfc+0.667*ui+0.5*mcsi+0.0025*sweat+0.025*$TI;

      $SCSRange = [
                      'range'=>[ 2,3,5 ],
                      'text' =>[
                              'Без конвективных штормов',
                              'Условия для гроз и конвекции',
                              'Развитие конвективных штормов',
                              'Опасные конвективные шторма',
                              ]
                  ];

      $drawText[] = array( 
              'index' => 'SCS', 
              'val'=>$SCS, 
              'font'=> ( $SCS<4?$TextSettingsINDEX:$TextSettingsINDEXR ),
              'str' => [
                          'txt' => $this->compareRanges( $SCS, $SCSRange['range'], $SCSRange['text'] ),
                          'font'=> ( $SCS<4?$TextSettingsMinINDEX:$TextSettingsMinINDEXR )   
                       ]  );

      $offset+=15;

      $TextSettingsINDEX['FontSize']=10;
      $this->_image->drawText( $this->_image->toX( $box_START ), $start_Y+$offset+5 , 'Индексы неустойчивости:' ,$TextSettingsINDEX);
      $offset+=10;

      //рисуем блок для индексов неустойчивости
      $strings = count($drawText); //количество строк в блоке
      $this->_image->drawFilledRectangle( $this->_image->toX( $box_START ) ,            $start_Y+$offset ,
                                       $this->_image->toX( $box_START + $box_W ) ,   $start_Y+$offset+$strings*($string_H+12)+10 ,
                                       $polygonINDEX );

      $offset +=15;
      foreach ($drawText as $text) {            
          $this->_image->drawText( $this->_image->toX( $box_START+1 ), $start_Y+$offset+5 , $text['index'].':' ,$text['font']);
          $this->_image->drawText( $this->_image->toX( $box_START+15 ), $start_Y+$offset+5 , ceil($text['val']) ,$text['font']);
          if ( isset( $text['str'] ) ){
              $this->_image->drawText( $this->_image->toX( $box_START+1 ), $start_Y+$offset+15+5 , $text['str']['txt'] ,$text['str']['font']);
          }
          $offset += $string_H+13 ;            
      }


      /* Build the PNG file and send it to the web browser */ 
      $path = '/ftp/pub/aero/'.$stantionInfo['id'].'-'.$kn->_date.'-'.$kn->_utc.'.png'; 
      $fpath = $_SERVER['DOCUMENT_ROOT'].$path; 
      $this->_image->render( $fpath );
      return $path;
      
  }


  /**
   * Функция выборки из периодов
   * @val - значение которое сравниваем
   * @range - массив значений
   *          <x
   *          <x1
   *          <x2
   *          ...
   *          >xn
   * сравниваем в интервале ДО значения
   *  массив @text должен быть на 1 больше массива @range (для последнего значения, превышающего интервал)
   */
  function compareRanges( $val, $range, $text ){
      if ( count($range)>0 && count($text)>0 ){
          $ret=null;
          for ($i=0; $i<count($range);$i++){
              if (isset($text[$i])){
                  if ( $val<$range[$i] ){
                      return $text[$i];
                  }
              }
          }
          if ( $val >= $range[count($range)-1] && isset($text[count($range)]) ){
              return $text[count($range)];
          }
      }
      return false;
  }

}