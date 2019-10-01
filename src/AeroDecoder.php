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

  /************** KN-04 END ***************/

}