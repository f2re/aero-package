<?php

namespace F2re\Aero;

/**
 * класс, который будет раскодировать телеграмму (текст)
 */
class AeroDecoder 
{
  
  public $_id            = 0;
  public $_stantion      = ''; //номер тсанции
  public $_code          = ''; //сырая телеграмма
  public $_std           = ''; //массив стандартных поверхностей (раскодированных)
  public $_nstd          = ''; //массив нестандартных поверхностей (раскодированных)
  public $_utc           = 0; //время зондирования
  public $_date          = 0; //дата зонда
  public $_day           = 0; //день выпуска зонда
  public $_iscorrectdate = 0; //корректна ли дата? (за тот ли месяц и год зонд?)
  public $_lastshape     = 0; //последняя поверхность зондирования
  public $_knots         = false; //узлы вместо метров в секунду
  public $_uk            = 0; //уровень конденсации
  public $_cacheimg      = '';
  public $_imgexist      = 0;

  public $mergedarray = array();
  private $inversions = array(); // инверсии
  private $std        = array(); // стандартные поверхности
  private $nstd       = array(); // нестандартные поверхности
  private $p10        = array(); //массив с данным интерполяции через каждые 10 гпа
  private $sostSpline = array(); //массив точек кривой состояния

  private $izogramm = array( //градиент в градусах цельсия (система координат) на 200гпа (надо перевести в высоты) 1000гпа-800гпа
      '-50' => 1.8,
      '-40' => 2,
      '-30' => 2.1,
      '-20' => 2.6,
      '-10' => 3,
      '0'   => 3.2,
      '10'  => 3.4,
      '20'  => 3.8,
      '30'  => 4.2,
      '40'  => 4.4,
  ); //изограммы
  private $dTCloud = array( //дефицит точки росы для обозначения облачности
      '850' => 1.5,
      '700' => 2.0,
      '500' => 2.5,
      '300' => 3.0,
  ); //изограммы
  private $syhoGrad = 0.0098; //сухая адиабата град на 1 м
  private $vlagoGrad = array( //влажноадиабатический градиент температуры
      '1000' => array(
        '-60'  => 0.973,
        '-50'  => 0.966,
        '-40'  => 0.95,
        '-30'  => 0.917,
        '-20'  => 0.856,
        '-10'  => 0.763,
        '0'    => 0.658,
        '10'   => 0.532,
        '20'   => 0.435,
        '30'   => 0.363,
        '40'   => 0.315,
      ),
      '800' => array(
        '-60' => 0.972,
        '-50' => 0.964,
        '-40' => 0.944,
        '-30' => 0.903,
        '-20' => 0.831,
        '-10' => 0.726,
        '0'   => 0.614,
        '10'  => 0.489,
        '20'  => 0.398,
        '30'  => 0.335,
        '40'  => 0.294,
      ),
      '600' => array(
          '-60' => 0.97,
          '-50' => 0.96,
          '-40' => 0.934,
          '-30' => 0.882,
          '-20' => 0.793,
          '-10' => 0.674,
          '0' => 0.557,
          '10' => 0.436,
          '20' => 0.356,
          '30' => 0.303,
          '40' => 0.27,
      ),
      '400' => array(
          '-60' => 0.9268,
          '-50' => 0.952,
          '-40' => 0.914,
          '-30' => 0.842,
          '-20' => 0.73,
          '-10' => 0.594,
          '0'   => 0.478,
          '10'  => 0.371,
          '20'  => 0.307,
          '30'  => 0.267,
          '40'  => 0.243,
      ),
      '200' => array(
          '-60' => 0.959,
          '-50' => 0.928,
          '-40' => 0.861,
          '-30' => 0.745,
          '-20' => 0.597,
          '-10' => 0.456,
          '0'   => 0.361,
          '10'  => 0.286,
          '20'  => 0.247,
          '30'  => 0.223,
          '40'  => 0.204,
      ),
      '100' => array(
          '-60' => 0.943,
          '-50' => 0.886,
          '-40' => 0.774,
          '-30' => 0.615,
          '-20' => 0.458,
          '-10' => 0.342,
          '0'   => 0.269,
          '10'  => 0.226,
          '20'  => 0.207,
          '30'  => 0.187,
          '40'  => 0.163,
      ),
  );

  public $_param_list  = array( 'id','stantion','code','std','nstd','utc','date','day','iscorrectdate','lastshape','cacheimg','imgexist' ); 

  /**
   * Конструктор класса
   * передаем сразу сырой текст
   */
  public function __construct($rawtext){
    $this->_code = $rawtext;
  }


  /**
   * функция декодирования 
   * @return [type] [description]
   */
  public function decode()
  {
    if ($this->_code != '') {

        $TTAA = ''; //первая группа кода
        $TTBB = ''; //вторая группа кода
        $TTDD = ''; //третья группа кода

        // TTAA
        // YYGGI
        // IIiii
        // 99PPP NNNDD ddfff
        //
        // получаем строку между TTAA ... и =
        $TTAA = $this->get_string_between($this->_code, 'TTAA', '=');
        $TTBB = $this->get_string_between($this->_code, 'TTBB', '=');

        //разбиваем ее
        $TTAA = explode(' ', trim($TTAA));

        //TTBB
        $TTBB = explode(' ', trim($TTBB));

        if (is_array($TTAA) && sizeof($TTAA) > 0) {
            $YYGGI = $this->parseDate(trim(array_shift($TTAA))); //число, срок, последняя поверхность
            $IIiii = trim(array_shift($TTAA)); //станция
        } else if (is_array($TTBB) && sizeof($TTBB) > 0) {
            $YYGGI = $this->parseDate(trim(array_shift($TTBB))); //число, срок, последняя поверхность
            $IIiii = trim(array_shift($TTBB)); //станция
        } else {
            return false;
        }

        $this->_stantion = $IIiii;
        if ((int) $this->_stantion == 0 || (int) $this->_stantion > 99999) {
            return false;
        }

        if ($YYGGI['day'] > 31) {
            $YYGGI['day'] = $YYGGI['day'] - 50;
            $this->_knots = true;
        }
        $this->_day           = (int) $YYGGI['day'];
        $this->_utc           = (int) $YYGGI['utc'];
        $this->_lastshape     = (int) $YYGGI['shp'];
        $this->_iscorrectdate = ($YYGGI['day'] == gmdate('j') ? 1 : 0); //если сегодняшний день, то все ок
        if (!$this->_iscorrectdate) {
            if ($YYGGI['day'] > gmdate('j')) {
                //значит предыдущий месяц
                $this->_date = date('Y-m-', strtotime(date('Y-m') . ' -1 month')) . ((int) $YYGGI['day']);
            } else {
                $this->_date = date('Y-m-') . ((int) $YYGGI['day']);
            }
        } else {
            $this->_date = date('Y-m-d');
        }

        if ($this->_date == '' || $this->_date == 0) {
            return false;
        }

        $PtoH = array(); //создадим массив Давление - Геопотенциал для расчета значений геопотенциала между ними для нестандартных поверхностей

        if (is_array($TTAA) && sizeof($TTAA) > 0) {
            //пробежимся по остаткам и разобьем на группы по 3 секции
            $array_size = sizeof($TTAA);
            $KN04 = array('date' => $YYGGI, 'std' => array(), 'nstd' => array());
            for ($i = 0; $i < $array_size; $i++) {
                if (sizeof($TTAA) < 3) {
                    break;
                }

                $press = $this->parseP(trim(array_shift($TTAA)));
                $temp = $this->parseT(trim(array_shift($TTAA)));
                $wind = $this->parseW(trim(array_shift($TTAA)), $this->_knots);
                $type = $this->retTypeP($press['type']);
                $KN04['std'][$type] = array(
                    'P'   => isset($press['P']) ? $press['P'] : $type,
                    'H'   => isset($press['H']) ? $press['H'] : null,
                    'T'   => isset($temp['T']) ? $temp['T'] : null,
                    'D'   => isset($temp['D']) ? $temp['D'] : null,
                    'DD'  => isset($wind['DD']) ? $wind['DD'] : null,
                    'FFF' => isset($wind['FFF']) ? $wind['FFF'] : null,
                );
                if ((int) $KN04['std'][$type]['P'] > 0 && (int) $KN04['std'][$type]['H'] > 0) {
                    $PtoH[(int) $KN04['std'][$type]['P']] = (int) $KN04['std'][$type]['H'];
                }
            }

        }
        //сортируем геопотенциалы
        krsort($PtoH);

        //TTBB
        if (is_array($TTBB) && sizeof($TTBB) > 0 || is_array($TTAA) && sizeof($TTAA) > 0) {
            //$YYGGI = trim(array_shift($TTBB)); //число, срок, последняя поверхность
            //$IIiii = trim(array_shift($TTBB)); //станция

            $array_size = sizeof($TTBB);
            $tempo = false;
            $windo = false;
            $cont = false; //далее не работаем
            for ($i = 0; $i < $array_size; $i++) {
                if (sizeof($TTBB) < 2) {
                    break;
                }

                $first = trim(array_shift($TTBB)); //сохраняем первый считанный код для проверки
                $press = $this->parseP($first, true);
                $pp = isset($press['P']) ? $press['P'] : null;

                if ($first == '21212') {
                    //ветровая секция дальше
                    $windo = true;
                    $tempo = false;
                    continue;
                }
                if ($first == '31313' || $first == '41414' || $cont) {
                    $cont = true;
                    continue;
                }

                $KN04['nstd'][$pp]['P'] = isset($press['P']) ? $press['P'] : null;

                //рассчитываем геопотенциал на поверхности
                if ((int) $KN04['nstd'][$pp]['P'] > 0) {
                    $Pprev = 0; //предыдущее значение давления
                    foreach ($PtoH as $Pa => $Ha) {
                        // echo $Pa.'<'.(int)$KN04['nstd'][ $pp ]['P'].' '.$PtoH[$Pprev];
                        if ($Pa < (int) $KN04['nstd'][$pp]['P'] && isset($PtoH[$Pprev])) {
                            // 0.1 - признак экстраполированных данных
                            $KN04['nstd'][$pp]['H'] = (int) ($PtoH[$Pprev] + (($Pprev - (int) $KN04['nstd'][$pp]['P']) * (($Ha - $PtoH[$Pprev]) / ($Pprev - $Pa)))) + 0.01; //делим разность геопотенциалов на разность давления
                            break;
                        }
                        $Pprev = $Pa; //сохраняем для предыдущего значения
                    }
                }

                if (($press['type'] == '00' && !$windo) || $tempo) {
                    //проверяем что это температурная секция
                    $tempo = true;
                    $temp = $this->parseT(trim(array_shift($TTBB)));
                    $wind = array();
                    $KN04['nstd'][$pp]['T'] = isset($temp['T']) ? $temp['T'] : null;
                    $KN04['nstd'][$pp]['D'] = isset($temp['D']) ? $temp['D'] : null;
                } else if ($windo) {
                    //проверяем что это секция ветра
                    $windo = true;
                    $wind = $this->parseW(trim(array_shift($TTBB)), $this->_knots);
                    $temp = array();
                    $KN04['nstd'][$pp]['DD'] = isset($wind['DD']) ? $wind['DD'] : null;
                    $KN04['nstd'][$pp]['FFF'] = isset($wind['FFF']) ? $wind['FFF'] : null;
                }
            } //endfor
        } //TTBB
        // print_r($KN04);
        if (isset($KN04['std']) && isset($KN04['nstd'])) {
            $this->std   = $KN04['std'];
            $this->nstd  = $KN04['nstd'];
            $this->_std  = json_encode($KN04['std']);
            $this->_nstd = json_encode($KN04['nstd']);

            $this->getmergedarray();
        }
        return;
    } //endif code
  }

  /**
   * return stantion ID
   * @return [type] [description]
   */
  public function get_stantion_id(){
    return $this->_stantion;
  }

  public function to_array(){
     // if ( $this->_id!=0 ){
       $to_arr = array();
       foreach ( $this->_param_list as $key ){
         // if ( $key=='admin_rating' && !$this->ion_auth->is_admin() ) continue;
         $local = '_'.$key;
         if ( in_array($key, array('std','nstd') ) ) {
            $to_arr[ $key ] = json_decode($this->$local,true);
         }else{
            $to_arr[ $key ] = $this->$local;
         }
     }
     return $to_arr;
     // }
     return;
  }

  //получаем общий массив стандартных и нестандартных поверхностей
  public function getmergedarray(){
    $allValues = array();
    
    if ( is_array($this->std) && sizeof($this->std)>0 ){
          foreach ($this->std as $val) {
            if ( !isset($val['P']) || $val['P']=='' || $val['P']<100 ) continue;
            if ( !isset($val['H']) || $val['H']=='' ) {
              $val['H'] = $this->PtoH($val['P']);
            }
              $allValues[ $val['P'] ] = $val;
          }
      }

      
      if ( is_array($this->nstd) && sizeof($this->nstd)>0 ){
          foreach ($this->nstd as $val) {
            if ( !isset($val['P']) || $val['P']=='' || $val['P']<100 ) continue;
            if ( !isset($val['H'])||$val['H']=='' ) $val['H'] = $this->PtoH($val['P']);
            
              $allValues[ $val['P'] ] = $val;
          }
      }
        krsort($allValues);  

        // высота геопотенциала и геопотенциал
      $prevP=null;
    $prevH=null;
    //температура чтобы не отличалась
    $prevT=null;
        foreach ($allValues as $key=>$val) {
          if ( ($prevT!=null && isset($val['T'])) && ( abs($val['T']-$prevT)>20 )  ) {
            unset($allValues[$key]);
          }
          $prevT = isset($val['T'])?$val['T']:null;
          if ( ($prevP!=null && $prevH!=null) && ($val['P']<$prevP && $val['H']<$prevH)  ) {
            // $prevP = $val['P'];
              // $prevH = $val['H'];
            unset($allValues[$key]);
          }
          $prevP = $val['P'];
            $prevH = $val['H'];
        }
        // echo '========================
        // ';
        // print_r($allValues);
        
        $this->mergedarray = $allValues;
        return $allValues;
  }



/**
 *
 *  Help function section
 *
 * 
 */
  

  /**
   * получаем высоту по давлению
   * @param [type] $P [description]
   */
  function PtoH($P){
    return $this->getH($P);
  }


  /**
   * получаем или интерполируем высоту на уровне давления
   * @param  [type] $P [description]
   * @return [type]    [description]
   */
  function getH($P){
    $P=(int)$P;
    $prev=null;
    $Pd = null; // два параметра для экстраполяции
    $dPdH=null; // для экстраполяции, если не получилось интерполировать
    if ( is_array($this->mergedarray) && sizeof($this->mergedarray)>0 && $P>=0 ){
      foreach ( $this->mergedarray as $Pa => $val) {
        $sval = $val;

        if ( ($Pa == $P ) && isset($val['H'])&&trim($val['H'])!='' ) return $val['H'];

        if ( ($Pa < $P ) && isset($val['H']) && isset($prev['H']) && isset($prev['P']) && $this->isreal($val['H']) ){
          // 0.01 - признак экстраполированных данных
           // echo $this->isreal($val['H'])." [$P] - $Pa ({$val['H']}):  [ {$prev['P']} - ($P) - $Pa ]  [[ {$prev['H']} - (".((int)(   $prev['H'] + ( ($P-$Pa) *  ( ($val['H'] - $prev['H']) / ($prev['P']-$Pa) ) ) )).") - {$val['H']} ]] <br>";
          return  (int)(   $val['H'] - ( ($P-$Pa) *  ( ($val['H'] - $prev['H']) / ($prev['P']-$Pa) ) ) ) + 0.01; //делим разность геопотенциалов на разность давления
        }
        
        //если предыдущий уровень содержит высоту, то сохраняем его как предыдущий
        if ( isset($val['H'])&&$val['H']>0&&$this->isreal($val['H']) ){
          if ( $prev!=null )
            $dPdH = ( ($val['H']-$prev['H'])/($prev['P']-$val['P']) ); //сохраняем последий действиетльный градиент высоты
          $Pd['P']   = $val['P'];
          $Pd['H']   = $val['H'];
          $prev=$val;
        }
        
      }
      return ( $Pd['H'] + $dPdH*($Pd['P']-$P) )+0.02;
    }
    
    return false;
  }

  /**
   * Получаем строку между двумя символами
   * @param  [type]  $string  [исходная строка]
   * @param  [type]  $start   [сивол старта]
   * @param  [type]  $end     [символ конца]
   * @param  integer &$offset [смещение]
   * @return [string]           [возвращаемая строка]
   */
  function get_string_between($string, $start, $end, &$offset=0){
    $string  = ' ' . $string;
    $ini     = strpos($string, $start, $offset );

    if ($ini == 0) {
      return '';
    }

    $ini    += strlen($start);
    $len     = strpos($string, $end, $ini) - $ini;
    $offset  = strpos($string, $end, $ini)+strlen($end);
    return substr($string, $ini, $len);
  }

  /**
   * Распарсиваем участок с датой
   * @param  [type] $code [description]
   * @return [array]       [description]
   */
  function parseDate($code){
    if ( $code=='' ) {
      return false;
    }
    $retval['day'] = (int)substr($code, 0,2); //день
    $retval['utc'] = (int)substr($code, 2,2); //время по гринвичу
    $retval['shp'] = (int)substr($code, 4,1)*100; // последняя поверхность
    
    return $retval;
  }


   /**
    * ********* KN-04 ************
    */


  /**
   * Распарсиваем участок с давлением
   * @param  [type] $code [description]
   * @return [array]       [P] [H] [type]
   */
  function parseP($code,$B=false){
    if ( $code=='' ) return false;
    //берем первые две цифры
    $type   = substr($code, 0,2);
    $val    = (int)substr($code, 2,3);
    $first  = (int)substr($code, 2,1);
    $res    = [];
    if ( $B ){
        switch ($type) {
            case '00': //данные у земли
                $res['P']=$this->returnP($val);
                $res['type']='00';
                break;
            default:
                $res['P']=(int)($val);
                $res['type']=$type;
                break;
        }

    }else{
        switch ($type) {
            case '99': //данные у земли
                $res['P']=$this->returnP($val);
                $res['type']='99';
                break;
            case '00': //1000 гпа
            case '92': //925 гпа
                $res['H']=$val;
                $res['type']=$type;
                break;  
            case '85': //850 гпа ***
                $res['H']=1000+$val;
                $res['type']=$type;
                break; 
            case '70': //700 гпа *****
                $res['H']= ( $val>500?2000+$val:3000+$val );
                $res['type']=$type;
                break; 
            case '50': //500 гпа
            case '40': //400 гпа
            case '30': //300 гпа
                $res['H']=$val*10;
                $res['type']=$type;
                break; 
            case '25': //250 гпа
                // echo $val.'-'.$first;
                // if ( $val<100 ){
                $res['H']=($first<=8?10000:0)+$val*10;
                // }else{
                //     $res['H']=$val*10;
                // }
                $res['type']=$type;
                break;
            case '20': //200 гпа
            case '15': //150 гпа
            case '10': //100 гпа
                $res['H']=($first<=8?10000:0)+$val*10;
                $res['type']=$type;
                break;    
            case '88': //тропопауза
                $res['P']=(int)($val); 
                $res['type']='88';
                break;    
            
            default:
                return null;
                break;
        }
    }

    return $res;
  }

  /**
   * возвращаем давление или тип поверхности
   * @param  [type] $p [description]
   * @return [type]    [description]
   */
  function retTypeP($p){
    switch ($p) {
        case '99':
            return 'surface';
            break;
        case '88':
            return 'tropo';
            break;
        case '00':
            return '1000';
            break;
        case '92':
            return '925';
            break;
        case '85':
        case '70':
        case '50':
        case '40':
        case '30':
        case '25':
        case '20':
        case '15':
        case '10':
            return $p.'0';
            break;
        
        default:
            return null;
            break;
    }
  }

  /**
   * формула для раскодировки давления (трех символов)
   * @param  [type] $code [description]
   * @return [type]       [P]
   */
  function returnP($code){
    if ( $code=='' ) return false;
    $code=(int)$code;
    //берем первые две цифры
    if ( $code<800 ) return 1000+$code;
    else return $code;

  }

  //рассчитываем координаты для кривой годске (кривая насыщения надо льдом)
  function godske($val,$max=-80){
    $ret = -8*$val;
    if ( $ret<=-80 ){
        $ret=-80;
    }
    return $ret;
  }

  /**
   * Распарсиваем участок с ветрмо и направлением
   * @knots = true - метры в секунду, иначе - узлы
   * @return [array]       [DD] [FF]
   */
  function parseW($code,$knots=false){
    if ( $code=='' ) return false;
    $dd = substr($code, 0,2)=='//'?null:(int)substr($code, 0,2) * 10; //dd направление
    if ( substr($code, 2,1)==5 ){
      $dd+=5;
      $fff = substr($code, 2,3)=='///'?null:(int)substr($code, 3,2); //fff скорость  
    }else
      $fff = substr($code, 2,3)=='///'?null:(int)substr($code, 2,3); //fff скорость

    if ( $fff > 0 && $knots ) $fff=(int)$fff/2; //узлы вместо метров
    $retval['DD']=$dd; //возвращаем направление
    $retval['FFF']=$fff; //возвращаем направление

    return $retval;
  }


  /**
   * Распарсиваем участок с температурой
   * @param  [type] $code [description]
   * @return [array]       [description]
   */
  function parseT($code){
    if ( $code=='' ) return false;
    if ( substr($code, 0,2)=="//" ) return null;
    $res = (int)substr($code, 0,2);
    $sign = substr($code, 2,1)=="//"?null:(int)substr($code, 2,1);

    if ( ($res==null||$res=='' )&&$res!=0 ) {
        return null;
    }

    $res = $res+($sign/10);
    if ( $sign%2!=0 ) $res=(-$res);
    $retval['T']=$res; //возвращаем температуру

    //точка росы
    $res = (int)substr($code, 3,2);
    //расчитываем по таблице кодирования
    if ( $res<=50 ) $retval['D']=$res/10; //до 50 - делим на 10
    if ( $res>55 ) $retval['D']=$res-50; //с 55 - вычитаем 50

    return $retval;
  }


  //получаем координаты влажной адиабаты для температуры
  //с шагом 100гпа
  //ограничение - -90градусов
  function getVlagAdiabat( $T=0, $shag=100, $Tlast=-90 ){
    $coord=array();
    if ( isset($this->vlagoGrad[1000][$T]) ){
      $Tcur = $T;
      $Gcur = $this->vlagoGrad[1000][$T]*10; // grad/1km
      $H=null;$Hprev=0;
      $arr = $this->getmergedarray();
      $last = array_pop( $arr );
      // print_r($last);
      for ( $i=1000;$i>($last['P']>100?$last['P']:100);$i=$i-$shag ){
        if ( isset( $this->vlagoGrad[$i] ) ){ //меняем градиент
          $Gcur=$this->vlagoGrad[$i][$T]*10;
        }
        $H =($this->getH($i)); //получаем высоту поверхности
        if ( $H ){
          $Tcur = $Tcur - (( $H-$Hprev )/1000)*$Gcur; //рассчитываем изменения температуры

          if ( $Tcur<=$Tlast || $H<=0  ) continue;

          $coord[]=array(  round($Tcur,1),($H) ); //сохраняем координаты

          $Hprev = $H;
        }
      }
      return $coord;
    }
    return false;
  }


  /**
   * Получаем все слои с облачностью
   * @return [type] [description]
   */
  function getCloudLayers(){
    $clouds=array();
    reset($this->dTCloud);
    $startKey = key($this->dTCloud);
    $start = false;
    $iter=array();
    $prev=null;
    foreach ($this->mergedarray as $P => $val) {
      if ( isset($val['D']) && isset($val['P']) && $val['P']<=850 && $val['P']>=300 ){
        $dt = $this->interpolate( $this->dTCloud,$val['P']);
        // echo $dt.'>'.$val['D'].'['.(isset($val['H'])?$val['H']:$this->PtoH($val['P'])).']  ';
        if ( $val['D']<=$dt && !$start ){
          $iter['start'] = isset($val['H'])?$val['H']:$this->PtoH($val['P']);
          $iter['stop'] = null;
          $start=true;
        }
        if ( $val['D']>=$dt && $start ){
          $iter['stop'] = isset($val['H'])?$val['H']:$this->PtoH($val['P']);
          $clouds[] = $iter;
          $iter=array();
          $start=false;
        }
        // $prev=array('D'=>$val['D'],'H'=>isset($val['H'])?$val['H']:$this->PtoH($val['P']) );

      }
    }
    return $clouds;
    // echo $this->interpolate( $this->dTCloud,600);
  }

  /**
   * интерполяция
   * @arr - 
   * @return [type] [description]
   */
  function interpolate(&$arr, $key){
    if ( is_array($arr) && sizeof($arr)>0  ){
      $prev=array( 'val'=> reset($arr), 'k'=>key($arr) );
      if ( isset( $arr[$key] ) ){
        return $arr[$key];
      }
      foreach ($arr as $k => $val) {
        if ( $key==$k ){
          return $val;
        }
        if ( $prev['k']!=$k ){
          if ( ($key >= $prev['k'] && $key <= $k) || ($key <= $prev['k'] && $key >= $k) ){
            return $prev['val'] + ( $prev['k'] - $key ) * ( ( $val-$prev['val'] )/( $prev['k']-$k ) );
          }
        }
        $prev = array( 'k'=>$k,'val'=> $val );
      }
    }
  }

  /**
   * Получаем градиент для изограмм (в градусах на 1м)
   * @param  integer $T [description]
   * @return [type]     [description]
   */
  function getIzogrammGrad($T=0){
    if ( $T<-50 ) $T=-50;
    if ( $T>40 )  $T=40;
    $dH=$this->getH(800) - $this->getH(1000); //получаем разность высот для расчета градиента
    //интерполируем градиент
    $prev=$this->izogramm['-50'];
    $G = null;
    foreach ($this->izogramm as $tt => $grad) {
      if ( $T > $prev && $T < $tt ){
        $G = $this->izogramm[$prev] + ( ($T-$prev) *  ( ($this->izogramm[$tt] - $this->izogramm[$prev]) / ($tt-$prev) ) ) ;
        break;
      }
      $prev = $T;
    }
    return $G/$dH;
  }

  //получаем уровень конденсации
  function getUK(){
    $dH=$this->PtoH($this->std['surface']['P']); //уровень земли
    //если есть приземная инверсия
    if ( is_array($this->inversions)&&isset($this->inversions[0]['start']['H'])&&$this->inversions[0]['start']['H']==$this->PtoH($this->std['surface']['P']) ){
      $dH=$this->inversions[0]['stop']['H'];
    }
    if ( isset( $this->std['surface'] ) ){
      // $this->_uk = ( $this->std['surface']['D'] )/( $this->syhoGrad - $this->getIzogrammGrad($this->std['surface']['T']-$this->std['surface']['D']) ) + $dH; 
      $this->_uk = 122*( $this->std['surface']['D'] ) + $dH;  
      return $this->_uk;
    }   

  }


  // получаем или интерполируем темпетаруру на высоте или уровне
  function getT($H=null,$P=null){
    if ( $H==null ){
      $P=(int)$P;
      if ( is_array($this->mergedarray) && sizeof($this->mergedarray)>0 && $P>=0 ){
        $prev=null;
        // print_r($this->mergedarray);
        foreach ( $this->mergedarray as $Pa => $val) {
          if ( ($Pa == $P ) && isset($val['T']) ) return $val['T'];
          if ( ($Pa < $P ) && isset($prev['T'])&& isset($val['T']) && isset($prev['P']) && $this->isreal($val['T']) ){
            // 0.1 - признак экстраполированных данных
            $ans = round(   $prev['T'] + ( ($prev['P'] - $P) *  ( ($val['T'] - $prev['T']) / ($prev['P']-$Pa) ) ),1 );
            return $ans<0?($ans-0.01):($ans + 0.01); //делим разность геопотенциалов на разность давления
          }
          
          //если предыдущий уровень содержит высоту, то сохраняем его как предыдущий
          if ( isset($val['T'])&&$val['T']!=null && $this->isreal($val['T']) )
            $prev=$val;
        }
      }
    }else{
      $H=(int)$H;
      if ( is_array($this->mergedarray) && sizeof($this->mergedarray)>0 && $H>=0 ){
        $prev=null;
        // print_r($this->mergedarray);
        foreach ( $this->mergedarray as $Pa => $val) {
          if ( ($val['H'] == $H ) && isset($val['T']) ) return $val['T'];
          if ( ($val['H'] > $H ) && isset($prev['H']) &&isset($prev['T'])&&isset($val['T']) && isset($prev['P']) ){
            // 0.1 - признак экстраполированных данных
            $ans = round(   $prev['T'] + ( ($prev['H'] - $H) *  ( ($val['T'] - $prev['T']) / ($prev['H']-$val['H']) ) ),1 );
            return $ans<0?($ans-0.01):($ans + 0.01); //делим разность геопотенциалов на разность давления
          }
          
          //если предыдущий уровень содержит высоту, то сохраняем его как предыдущий
          if ( isset($val['T'])&&$val['T']!=null  )
            $prev=$val;
        }
      }
    }
    return false;
  }


  // получаем или интерполируем темпетаруру на высоте или уровне
  function getParam($param='T',$H=null,$P=null){
    if ( !in_array($param, array('T','D','DD','FFF')) )
      return false;

    if ( $H==null ){
      $P=(int)$P;
      if ( is_array($this->mergedarray) && sizeof($this->mergedarray)>0 && $P>=0 ){
        $prev=null;
        // print_r($this->mergedarray);
        foreach ( $this->mergedarray as $Pa => $val) {
          if ( ($Pa == $P ) && isset($val[$param]) ) return $val[$param];
          if ( ($Pa < $P ) && isset($prev[$param])&& isset($val[$param]) && isset($prev['P']) && $this->isreal($val[$param]) ){
            // 0.1 - признак экстраполированных данных
            $ans = round(   $prev[$param] + ( ($prev['P'] - $P) *  ( ($val[$param] - $prev[$param]) / ($prev['P']-$Pa) ) ),1 );
            return $ans<0?($ans-0.01):($ans + 0.01); //делим разность геопотенциалов на разность давления
          }
          
          //если предыдущий уровень содержит высоту, то сохраняем его как предыдущий
          if ( isset($val[$param])&&$val[$param]!=null && $this->isreal($val[$param]) )
            $prev=$val;
        }
      }
    }else{
      $H=(int)$H;
      if ( is_array($this->mergedarray) && sizeof($this->mergedarray)>0 && $H>=0 ){
        $prev=null;
        // print_r($this->mergedarray);
        foreach ( $this->mergedarray as $Pa => $val) {
          if ( ($val['H'] == $H ) && isset($val[$param]) ) return $val[$param];
          if ( ($val['H'] > $H ) && isset($prev['H']) &&isset($prev[$param])&&isset($val[$param]) && isset($prev['P']) ){
            // 0.1 - признак экстраполированных данных
            $ans = round(   $prev[$param] + ( ($prev['H'] - $H) *  ( ($val[$param] - $prev[$param]) / ($prev['H']-$val['H']) ) ),1 );
            return $ans<0?($ans-0.01):($ans + 0.01); //делим разность геопотенциалов на разность давления
          }
          
          //если предыдущий уровень содержит высоту, то сохраняем его как предыдущий
          if ( isset($val[$param])&&$val[$param]!=null  )
            $prev=$val;
        }
      }
    }
    return false;
  }



  //интерполируем давление и температуру на 10 гпа
  function interpolate10($begin = 1000, $stop =100){
    for ( $i=$begin; $i>$stop; $i-=10 ){
      $this->p10[$i] = array( 
        'P' => $i,
        'H' => $this->getH($i),
        'T' => $this->getT(null,$i)
       );
    }
    // print_r($this->p10);
  }


  //проверяем интерполированные или ральные данные
  function isreal($val){
    $val = $val*10;
    $val1 = explode('.', $val);
    // if ( ($val - ceil($val))==0.1 ) return false;
    if ( isset($val1[1]) && $val1[1]==1 ) return false;
    else return true;
  }


  /**
   * усреднение и уплавнение координат прямой / кривой
   * @sections - количество секций базового сплайна для построения кривой безье
   */
  public function averageSpline2( &$arr, $sections=3 ){

    $new  = array();
    $i    = 0;
    $j    = 0;
    $help = array();
    $size = sizeof($arr);

    $max = 0; //находим самую высокую точку
    $min = 30000;

    foreach ($arr as $key => $val) {
      if ( $val[1]>$max ) $max = $val[1];
      if ( $val[1]<$min ) $min = $val[1];
    }
    //делим массив на секции по высотам
    $sectionONE = ($max - $min)/$sections;

    $new[] = array_shift($arr);

    for ($j=1; $j <= $sections; $j++) { 
      $sectionData=array(0,0);
      $i = 0;
      foreach ($arr as $key => $val) {
        if ( $val[1]<($min+$j*$sectionONE) && $val[1]>($min+($j-1)*$sectionONE) ){
          $sectionData[0] += $val[0];
          $sectionData[1] += $val[1];
          $i++;
        }
      }
      if ($i>0 && ($sectionData[0]!=0 && $sectionData[1] !=0) ){
        $new[]=array( $sectionData[0]/$i,$sectionData[1]/$i );
      }
    }
    $new[] = array_pop($arr);

    return $new;
  }


  //получаем все инверсии и изотермии
  function getAllInversion(){
    $inversions = array();
    $startinversion=false;
    $inv=array();
    $prev =array();
    // print_r($this->std);
    //проходимся по всем точкам
    foreach ($this->mergedarray as $P => $val) {
      // если есть данные о температуре
      if ( isset( $prev['T'] )&&isset( $val['T'] ) ){ //если находим инверсию
        // если она с высотой повышается/не изменяется
        if ( $val['T']>=$prev['T'] ){
          // если не начата секция инверсии
          if ( !$startinversion ){ //начинаем новую инверсию
            $startinversion=true; // начали инверсию
            $inv['tropo'] = false;  // обнулили тропопаузу            
            $inv['start'] = array( 
                    'H'=> ( isset($prev['H'])?$prev['H']:$this->PtoH($prev['P']) ) , 
                    'T'=>$prev['T'] 
                  );
          }else {//сохраняем промежуточные точки
            $inv['vals'][] = array( 
                    'H'=> ( isset($prev['H'])?$prev['H']:$this->PtoH($prev['P']) ) , 
                    'T'=>$prev['T'] 
                  );
          }
        }else{ //если инверсии нет или закончилась
          if ( $startinversion ){ // если инверсия закончилась
            $startinversion=false; //закрываем ее
            $inv['stop'] = array( 
                  'H'=> ( isset($prev['H'])?$prev['H']:$this->PtoH($prev['P']) ) , 
                  'T'=>$prev['T'] 
                );
            $inversions[] = $inv; // добавляем в массив инверсий
            $inv = array();
          }
        }
      }
      if ( isset( $val['T'] ) ) {
        $prev = $val;
      }
    }

    // если инверсия не закрыта по окончанию массива данных - закрываем
    if ( !isset($inv['stop']) && isset($inv['vals']) ){
      $prev = array_pop($inv['vals']);
      $inv['stop'] = array( 'H'=> ( isset($prev['H'])&&$prev['H']!=''?$prev['H']:$this->PtoH($prev['P']) ) , 'T'=>$prev['T'] );
      $inversions[] = $inv;
    }

    // 
    //  запускаем тропопаузу
    //
    $inv = array();

    // print_r($this->std);

    if (   isset( $this->std['tropo']['T'] )
         &&
         $this->std['tropo']['T']<0  
         &&
         $this->std['tropo']['H'] > 0
       ){
      $inv['tropo'] = true;
      $startinversion=true;
      $inv['start'] = array( 'H'=> ( isset($this->std['tropo']['H'])&&$this->std['tropo']['H']!='' ? $this->std['tropo']['H'] : $this->PtoH($this->std['tropo']['P']) ) , 'T'=>$this->std['tropo']['T'] );
      
      $begP = $this->std['tropo']['P'];
      $prev = $this->std['tropo'];
      // echo $this->std['tropo']['T'];
      $tropo = array(); //итоговый массив
      $last=array();
      
      foreach ($this->mergedarray as $P => $val) {
        if ( isset( $prev['T'] )&&isset( $val['T'] ) && ($begP>$P) ){ //если находим инверсию
          if ( ($val['T']>=$prev['T']) ){
            if ( $startinversion && $prev['H']>$this->std['tropo']['H'] ){  //сохраняем промежуточные точки
              $inv['vals'][] = array( 'H'=> ( isset($prev['H'])&&$prev['H']!=''?$prev['H']:$this->PtoH($prev['P']) ) , 'T'=>$prev['T'] );
            }

          }else{ //если инверсии нет или закончилась
            if ( $startinversion && isset($inv['vals'])&&sizeof($inv['vals'])>0 ){
              $startinversion=false;
              $inv['stop'] = array( 'H'=> ( isset($prev['H'])&&$prev['H']!=''?$prev['H']:$this->PtoH($prev['P']) ) , 'T'=>$prev['T'] );
              $tropo = $inv;
              // print_r($tropo);
            }
          }
        }
        if ( isset( $val['T'] ) ) $prev = $val;
      }//endforeach

      //если зонд оборвался, а инверсия не закончилась - то присваиваем последнее значение
      if ( !isset($inv['stop']) ){

        if ( isset($inv['vals']) ){
          $prev = array_pop($inv['vals']);
        }else{
          foreach ($this->mergedarray as $P => $val) {
            if ( !isset($val['H'])||$val['H']=='' ) $val['H'] = $this->PtoH($val['P']);
            if ( isset( $val['T'] )&&isset( $val['T'] ) && ( $val['H'] < $prev['H'] ) && ( $val['H'] > $inv['start']['H'] ) ){
              $inv['vals'][] = array( 'H'=> ( isset($val['H'])&&$val['H']!=''?$val['H']:$this->PtoH($val['P']) ) , 'T'=>$val['T'] );
            }
          }
        }
        $inv['stop'] = $prev;
        $tropo=$inv;
      }
      
      // print_r($tropo);
      //если есть инверсии выше тропопаузы - то это тропопауза
      $firsttropo = false;
      $tropoIsSets= false; //переменная которая учитывает тропоппаузу ЕДИНОЖДЫ (если инверсия в тропопаузе не наблюдается)
      foreach ($inversions as $i => $val) {
        if ( isset($val['start']) && isset($tropo['start']) && $val['start']['H']>=$tropo['start']['H'] ){
          if ( $firsttropo ){
            unset($inversions[$i]);
            continue;
          }
          $inversions[$i]=$tropo;
          $inversions[$i]['tropo'] = true;
          $tropoIsSets=true;
          // print_r($tropo);
          $firsttropo = true;
        }
      }
      if ( !$tropoIsSets && is_array($tropo) ){
        $tropo['tropo']=true;
        $inversions[]=$tropo;
        $tropoIsSets=true;
      }
      // print_r($inversions);
    } 

    // print_r($inversions);
    $this->inversions=$inversions;
    return $inversions;
  }

  //получаем координаты кривой состояния
  
  function getSostSpline($uk=null,$start=null) {
    if ( $uk!=null ){
      $this->generateSostSpline($uk,$start);
      return $this->sostSpline;
    }else{
      if ( is_array($this->sostSpline) && sizeof( $this->sostSpline )>0 ){
        return $this->sostSpline;
      }else{
        return null;
      }
    }
  }


  //// start - массив с данными о поверхности или верхней границе приземной инверсии
  function generateSostSpline( $uk=null,$start=null ){
    if ( $uk==null ){
     return null;
    }
    else{
      // Построение влажноадиабатического участка кривой от 1000-900 до 500 гПа
          //
          // Расчет температуры смоченного термометра средней в слое от земли до 900 гПа
          $UKP    = $this->getP($uk);
          // print_r($UKP);
          if ( $UKP==0 ){
            return 1;
          }
          $H      = $start==null? ((isset($this->std['surface']['H']) && $this->std['surface']['H']!='' ) ? $this->std['surface']['H'] : $this->PtoH($this->std['surface']['P'])) : $start['H'];
          $TUK    = ($start==null? $this->std['surface']['T'] : $start['T']) - ( $uk-$H )*0.98/100;
          

          $Po       = $this->std['surface']['P'];
          $TtrosySrPo_900 = $this->getAverageParam('TD',$Po,900);
          $TsrPo_900    = $this->getAverageParam('T',$Po,900);
          
          // $PsrZ_900    = ($Po + 900)/2;
          $PsrZ_900   = $UKP;
          $T850       = $this->getRealT(850);
          $T500       = $this->getRealT(500);
          $E = 6.11 * pow(10, 7.5 * $TtrosySrPo_900 / (237.7 + $TtrosySrPo_900));
          // TsmT - температура смоченного термометра
          $TsmT = (((0.00066 * $PsrZ_900) * $TsrPo_900) + ((4098 * $E) / (($TtrosySrPo_900 + 237.7) * ($TtrosySrPo_900 + 237.7)) 
              * $TtrosySrPo_900)) /
              ((0.00066 * $PsrZ_900) + (4098 * $E) / (($TtrosySrPo_900 + 237.7) * ($TtrosySrPo_900 + 237.7)));
          // Вычисление массовой доли насыщенного водяного пара Sm
          // Округляю давление в середине слоя до целого. 0,1 - для округления по правилам арифметики
          $PsrZ_900 = round($PsrZ_900 + 0.1);        
          // Выделяю крайнюю цифру справа
          $Pcount = 1000;
          while ($Pcount > $PsrZ_900)
          {
              $Pcount = $Pcount - 10;
          }
          $dP = $Pcount - $PsrZ_900; // Разница давления до целых десятков
          // Вычисление Sm на исходном уровне
          if ( $PsrZ_900!=0 && $TsmT!=-235 ){
            $Sm[0] = (622 * 6.1078 / $PsrZ_900) * exp((17.13 * $TsmT) / (235 + $TsmT));
          }else{
            $Sm[0] = 0;
          }
          // Вычисление приращения температуры на уровне, не кратном 10 гПа
          // Формула приращения температуры на кривой состояния
          $SmSr = $Sm[0];

          $dT[0] = (((2.49 * $SmSr + 0.286 * (273.15 + $TsrPo_900))) /
              (1 + (13513.9 * $SmSr / pow(273.15 + $TsrPo_900, 2)))) *
                   log(($PsrZ_900 + $dP) / $PsrZ_900);
          // Вычисление приращения температуры на уровнях, кратных 10 гПа
          $T[0]['T'] = $TUK;
          $T[1]['T'] = $TUK + $dT[0];
          $T[0]['P'] = ($UKP);
          $T[1]['P'] = ($UKP + $dP);

          $P = $Pcount;

          $nlevel=1;
          // Построение кривой влажноадиабатического участка на уровнях кратных 10
          while ($P > 100)   {
            if ( $T[$nlevel]['T']>-80 ){
                $Sm[$nlevel] = (622 * 6.1078 / ($P - 10)) * exp((17.13 * $T[$nlevel]['T']) / (235 + $T[$nlevel]['T']));
                $SmSr = ($Sm[$nlevel - 1] + $Sm[$nlevel]) / 2;
                $dT[$nlevel] = (((2.49 * $SmSr + 0.286 * (273.15 + $T[$nlevel]['T']))) /
                (1 + (13513.9 * $SmSr / pow(273.15 + $T[$nlevel]['T'], 2)))) *
                     log(($P - 10) / $P);
              
                $T[$nlevel + 1]['T'] = $T[$nlevel]['T'] + $dT[$nlevel];
                $T[$nlevel + 1]['P'] = ($P);
                $nlevel++;
            }
             
              $P = $P - 10;
          }
          
          $res = array();
          foreach ($T as $val) {
            $h = $this->getH($val['P']);
            if ( $h )
              $res[] = array( $val['T'],  $h );
          }
          $this->sostSpline = $res;
    }
  }


  function getRealT($P){
    foreach ($this->mergedarray as $Pa => $val) {
      if ( ($Pa == $P) ) {
        if ( isset( $val['T'] ) )
          return $val['T'];
        else
          return $this->getT(null,$P);
      }
    }
  }


  /**
   * получение среднего заданного параметра @param в слое @P0 - @P1
   */
  function getAverageParam($param='T',$P0=1000,$P1=850){
    $i=0;
    $sum=0;
    if ( $param=='TD' ){ //ищем точку росы а не известный параметр
      foreach ($this->mergedarray as $key => $value) {
        if ( isset($value['P'])&& ($P0>=$value['P'] && $P1<=$value['P']) ){
          if ( isset( $value[ 'T' ] )&&$value[ 'T' ]!=null&&isset( $value[ 'D' ] )&&$value[ 'D' ]!=null ){
            // echo 'P['.$value['P'].']-'.($value[ 'T' ]-$value[ 'D' ]).' ';
            $sum+=$value[ 'T' ]-$value[ 'D' ];
            $i++;
          }
        }
      }
    }else{
      foreach ($this->mergedarray as $key => $value) {
        if ( isset($value['P'])&& ($P0>=$value['P'] && $P1<=$value['P']) ){
          if ( isset( $value[ $param ] )&&$value[ $param ]!=null ){
            $sum+=$value[ $param ];
            $i++;
          }
        }
      }
    }
    return $i>0?$sum/$i:null;
  }


    // получаем или интерполируем давление на высоте
  function getP($H){
    $H=(int)$H;
    if ( is_array($this->mergedarray) && sizeof($this->mergedarray)>0 && $H>=0 ){
      $prev=null;
      // print_r($this->mergedarray);
      foreach ( $this->mergedarray as $Pa => $val) {
        if ( ($val['H'] > $H ) && isset($prev['H']) && isset($prev['P']) ){
          // 0.1 - признак экстраполированных данных
          return  (int)(   $prev['P'] + ( ($prev['H'] - $H) *  ( ($val['P'] - $prev['P']) / ($prev['H']-$val['H']) ) ) ) + 0.01; //делим разность геопотенциалов на разность давления
        }
        
        //если предыдущий уровень содержит высоту, то сохраняем его как предыдущий
        if ( isset($val['P'])&&$val['P']>0 )
          $prev=$val;
      }
    }
    return false;
  }

  //получаем точку на кривой состояния по одной из координат
  function getPointOnSostSpline( $x=null, $y=null ){
    $farr=array();
    foreach ($this->sostSpline as $arr) { //переведем все в массив, пригодный для интерполяции по схеме КЛЮЧ - ЗНАЧЕНИЕ (поиск по ключу)
      if ( $x!=null ){
        $farr[ $arr[0] ] = $arr[1];
      }else{
        $farr[ $arr[1] ] = $arr[0];
      }
    }
    return $this->interpolate( $farr, ( $x!=null?$x:$y ) );
  }

  //получаем высоту для искомой температуры
  function getHforT($T=0){
    $arr = $this->mergedarray;
    ksort($arr);
    // print_r($arr);
    foreach ($arr as $P => $val) {
      if ( isset($val['T']) && isset($val['P']) && $val['T']==$T ) return $val['H'];
      if ( isset($val['T']) && isset($val['P']) && isset($prev['T'])  && $val['T']>$T ){
        // 0.1 - признак экстраполированных данных
        $ans = round(   ( isset($val['H'])?$val['H']:$this->PtoH($val['P']) ) + (  ($T - $val['T'] ) *  ( (( isset($prev['H'])?$prev['H']:$this->PtoH($prev['P']) )  - ( isset($val['H'])?$val['H']:$this->PtoH($val['P']) ) ) / ($prev['T']-$val['T']) )  ),1 );
        return $ans + 0.01; //делим разность геопотенциалов на разность давления
      }
      if ( isset( $val['T'] )&& isset($val['P']) ) $prev = $val;
    }
  }

  /************** KN-04 END ***************/

}