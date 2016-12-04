<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
* Xport controller.
*
* @package    pnp4nagios
* @author     Joerg Linge
* @license    GPL
*/
class Api_Controller extends System_Controller  {

  public function __construct(){
    parent::__construct();
    // Disable auto-rendering
    $this->auto_render = FALSE;
    $this->data->getTimeRange($this->start,$this->end,$this->view);
    // Graphana sends JSON via POST
    $this->post_data = json_decode(file_get_contents('php://input'), true);

  }

  public function index() {
    $data['pnp_version']  = PNP_VERSION;
    $data['pnp_rel_date'] = PNP_REL_DATE;
    $data['error']        = "";
    $json = json_encode($data, JSON_PRETTY_PRINT);
    header('Status: 200');
    header('Content-type: application/json');
    print $json;
  }

  public function hosts($query = false) {
    if ( $query ){
      $hosts = $this->data->getHosts();
      $data  = array();
      foreach ( $hosts as $host => $value ){
        if ( $value['state'] != 'active' ){
          continue;
        }
        if ( preg_match("/$query/i", $value['name']) ){
          $data[] = $value['name'];
        }
      }
    }else{
      $hosts = $this->data->getHosts();
      $data  = array();
      foreach ( $hosts as $host => $value ){
        if ( $value['state'] != 'active' ){
          continue;
        }
        $data[] = $value['name'];
      }
    }
    $json = json_encode($data, JSON_PRETTY_PRINT);
    header('Status: 200');
    header('Content-type: application/json');
    print $json;
  }

  public function services($host = false, $query = false) {
    if ( $host === false ){
      $data['error'] = "No hostname specified";
      $json = json_encode($data, JSON_PRETTY_PRINT);
      header('Status: 901');
      header('Content-type: application/json');
      print $json;
      return;
    }
    $services = array();
    try {
      $services = $this->data->getServices($host);
    } catch ( Kohana_Exception $e) {
      $data['error'] = "$e";
      $json = json_encode($data);
      header('Status: 901');
      header('Content-type: application/json');
      print $json;
      return;
    }

    foreach($services as $service => $value){
      if ( $query === false){
        // All Services
        $data['services'][] = $value['name'];
      }else{
        // Services matching Regex
        if ( preg_match( "/$query/i", $value['name']) ){
          $data['services'][] = $value['name'];
        }
      }
    }
    $json = json_encode($data, JSON_PRETTY_PRINT);
    header('Status: 200');
    header('Content-type: application/json');
    print $json;
  }

  public function labels ( $host=false, $service=false ) {
    if ( $host === false ){
      $data['error'] = "No hostname specified";
      $json = json_encode($data, JSON_PRETTY_PRINT);
      header('Status: 901');
      header('Content-type: application/json');
      print $json;
      return;
    }
    if ( $service === false ){
      $data['error'] = "No service specified";
      $json = json_encode($data, JSON_PRETTY_PRINT);
      header('Status: 901');
      header('Content-type: application/json');
      print $json;
      return;
    }
    try {
      // read XML file
      $this->data->readXML($host, $service);
    } catch (Kohana_Exception $e) {
      $data['error'] = "$e";
      $json = json_encode($data);
      header('Status: 901');
      header('Content-type: application/json');
      print $json;
      return;
    }
    $data = array();
    foreach( $this->data->DS as $KEY => $DS){
      $data['labels'][] = $DS['NAME'];
    }
    $json = json_encode($data, JSON_PRETTY_PRINT);
    header('Status: 200');
    header('Content-type: application/json');
    print $json;
  }


  public function metrics($host=false, $service=false, $label=false){
    if ( $host === false ){
      $data['error'] = "No hostname specified";
      $json = json_encode($data, JSON_PRETTY_PRINT);
      header('Status: 901');
      header('Content-type: application/json');
      print $json;
      return;
    }
    if ( $service === false ){
      $data['error'] = "No service specified";
      $json = json_encode($data, JSON_PRETTY_PRINT);
      header('Status: 901');
      header('Content-type: application/json');
      print $json;
      return;
    }
    if ( $label === false ){
      $data['error'] = "No perfdata label specified";
      $json = json_encode($data, JSON_PRETTY_PRINT);
      header('Status: 901');
      header('Content-type: application/json');
      print $json;
      return;
    }
    $data = array();

    try {
      $this->data->buildXport($host, $service);
      $xml = $this->rrdtool->doXport($this->data->XPORT);
    } catch (Kohana_Exception $e) {
      $data['error'] = "$e";
      $json = json_encode($data);
      header('Status: 901');
      header('Content-type: application/json');
      print $json;
      return;
    }

    $xpd = simplexml_load_string($xml);
    $i = 0;
    $index = 0;
    foreach ( $xpd->meta->legend->entry as $k=>$v){
      if( $v == $label."_AVERAGE"){
        $index = $i;
        break;
      }
      $i++;
    }

    $data['index'] = $index;
    $i = 0;
    $start = (string) $xpd->meta->start;
    $step  = (string) $xpd->meta->step;
    foreach ( $xpd->data->row as $row=>$value){
        $timestamp = $start + ($i * $step);    
        #print_r($value);i
        $d = (string) $value->v->$index;
        if ($d == "NaN"){ $d = 0; }
        $d = floatval($d);
        $data['datapoints'][] = sprintf("%s,%s", $timestamp, $d);
        $i++;
    }
    $json = json_encode($data, JSON_PRETTY_PRINT);
    header('Status: 200');
    header('Content-type: application/json');
    print $json;
  }
}