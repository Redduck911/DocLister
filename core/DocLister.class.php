<?php
if(!defined('MODX_BASE_PATH')){die('What are you doing? Get out of here!');}
/**
 * DocLister class
 *
 * @license GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @author Agel_Nash <Agel_Nash@xaker.ru>
 * @date 11.03.2013
 * @version 1.0.6
 *
 *	@TODO add controller for work with plugin http://modx.com/extras/package/quid and get TV value via LEFT JOIN
 *	@TODO add controller for filter by TV values
 *  @TODO add method load default template
 *  @TODO add example custom controller for build google sitemap.xml
 *  @TODO add method build tree for replace Wayfinder if need TV value in menu OR sitemap
 *  @TODO add controller for show list web-user with filter by group and other user information
 *  @TODO depending on the parameters
 *  @TODO prepare value before return final data (maybe callback function OR extender)
*/

abstract class DocLister {
    protected  $_docs=array();
    protected $_tree=array();
    protected $IDs=0;
    protected $modx=null;
    protected $extender='';
    protected $_plh=array();
    protected $_lang=array();
    private  $_cfg=array();

    function __construct($modx,$cfg){
        try{
            if(extension_loaded('mbstring')){
		        mb_internal_encoding("UTF-8");
            }else{
                throw new Exception('Not found php extension mbstring');
            }

            if($modx instanceof DocumentParser){
                $this->modx=$modx;
            }else{
                throw new Exception('MODX var is not instaceof DocumentParser');
            }

            if(!$this->setConfig($cfg)){
                throw new Exception('no parameters to run DocLister');
            }

            $this->loadLang('core');
            $this->setLocate();
        }catch(Exception $e){
            $this->ErrorLogger($e->getMessage(),$e->getCode(),$e->getFile(),$e->getLine(),$e->getTrace());
        }

        $this->loadExtender($this->getCFGDef("extender",""));
        if($this->checkExtender('request')){
            $this->extender['request']->init($this,$this->getCFGDef("requestActive",""));
        }
	}

    abstract public function getUrl($id=0);
    abstract public function getDocs($tvlist='');
    abstract public function render($tpl='');

    /*
     * CORE Block
     */

    /*
     * Display and save error information
     *
     * @param string $message error message
     * @param integer $code error number
     * @param string $file error on file
     * @param integer $line error on line
     * @param array $trace stack trace
     *
     * @todo $this->modx->debug
     * @todo $this->modx->logEvent(4001,3,$msg,'DocLister');
     */
    final public function ErrorLogger($message,$code,$file,$line,$trace){
        if($this->getCFGDef('debug','0')=='1'){
            echo "CODE #".$code."<br />";
            echo "on file: ".$file.":".$line."<br />";
            echo "<pre>";
            var_dump($trace);
            echo "</pre>";
        }
        die($message);
    }
    final public function getMODX(){
        return $this->modx;
    }
    final public function loadExtender($ext){
         $ext=explode(",",$ext);
         foreach($ext as $item){
             $this->_loadExtender($item);
         }
    }
    final public function setConfig($cfg){
		if(is_array($cfg)){
			$this->_cfg=array_merge($this->_cfg,$cfg);
            $ret=count($this->_cfg);
        }else{
            $ret=false;
        }
        return $ret;
	}
    final public function getCFGDef($name,$def){
		return isset($this->_cfg[$name])?$this->_cfg[$name]:$def;
	}
    final public function toPlaceholders($data,$set=0,$key='contentPlaceholder'){
        $this->_plh[$key]=$data;
		if($set==0){
			$set=$this->getCFGDef('contentPlaceholder',0);
		}
		if($set!=0){
			$id=$this->getCFGDef('id','');
			if($id!='') $id.=".";
			$this->modx->toPlaceholder($key,$data,$id);
		}else{
			return $data;
		}
	}
	final protected function sanitarIn($data,$sep=','){
		if(!is_array($data)){
			$data=explode($sep,$data);
		}
		$out=array();
		foreach($data as $item){
			$out[]=$this->modx->db->escape($item);
		}
		$out="'".implode("','",$out)."'";
		return $out;
	}
    final protected function loadLang($name='core',$lang=''){
		if($lang==''){
			$lang=$this->getCFGDef('lang',$this->modx->config['manager_language']);
		}
        if(file_exists(dirname(__FILE__)."/lang/".$lang."/".$name.".inc.php")){
            $tmp=include_once(dirname(__FILE__)."/lang/".$lang."/".$name.".inc.php");
            if(is_array($tmp)) {
                $this->_lang=array_merge($this->_lang,$tmp);
            }
        }
        return $this->_lang;
	}
    final public function getMsg($name,$def=''){
        return (isset($this->_lang[$name])) ? $this->_lang[$name] : $def;
    }
    final public function renameKeyArr($data,$prefix='',$suffix='',$sep='.'){
        $out=array();
        if($prefix=='' && $suffix==''){
            $out=$data;
        }else{
            if($prefix!=''){
                $prefix=$prefix.$sep;
            }
            if($suffix!=''){
                $suffix=$sep.$suffix;
            }
            foreach($data as $key=>$item){
                $out[$prefix.$key.$suffix]=$item;
            }
        }
        return $out;
    }
	final public function setLocate($locale=''){
		switch(true){
			case (''==$locale):{
				$locale = $this->getCFGDef('locale','');
				//without break
			}
			case (''!=$locale):{
				setlocale(LC_ALL, $locale);
				break;
			}
		}
		return $locale;
	}
    public function parseChunk($name,$data){
        $out='';
        if($name!='' && !isset($this->modx->chunkCache[$name])){
            $mode=substr($name,0,6);
            switch($mode){
                case '@FILE:':{ //chunk in file
                    $tpl=trim(substr($name, 6));
                    if(file_exists($data)){
                        $tpl=file_get_contents($tpl); //@todo: validate filename
                    }else{
                        $tpl=null;
                    }
                    break;
                }
                case '@CODE:':{ //name is tpl
                    $tpl=trim(substr($name, 6));
                }
                default:{  //not exist chunk
                    $tpl=null;
                }
            }
            if(isset($tpl)){
                $this->modx->chunkCache[$name]=$tpl;
            }
        }
        if(is_array($data) && $name!=''){
            $out = isset($this->modx->chunkCache[$name]) ? $this->modx->chunkCache[$name] : ''; //get tpl
            if($out!=''){
                foreach ($data as $key => $value) {
                    $out = str_replace('[+' . $key . '+]', $value, $out);
                }
            }
        }
        return $out;
    }

    public function getJSON($data,$fields,$array=array()){
        $out=array();
        $fields = is_array($fields) ? $fields : explode(",",$fields);
		if(is_array($array) && count($array) > 0){
			foreach($data as $i=>$v){ //array_merge not valid work with integer index key
				$tmp[$i]= (isset($array[$i]) ? array_merge($v,$array[$i]) : $v);
			}
			$data = $tmp;
		}

        foreach($data as $num=>$doc){
			foreach($doc as $name=>$value){
				if(in_array($name,$fields) || array('1')==$fields){
					$tmp[str_replace(".","_",$name)]=$value; //JSON element name without dot 
				}
			}
			$out[$num]=$tmp; 
        }
		
		// $out = prepareJsonData($out); 
        return json_encode($out);
    }
    /*
     * @param string $name extender name
     * @return boolean status extender load
     */
    final protected function checkExtender($name){
        return (isset($this->extender[$name]) && $this->extender[$name] instanceof $name."_DL_Extender");
    }

    final private function _loadExtender($name){
        $flag=false;

        $classname=($name!='') ? $name."_DL_Extender" : "";
        if($classname!='' && isset($this->extender[$name]) && $this->extender[$name] instanceof $classname){
            $flag=true;
        }else{
            if(!class_exists($classname,false) && $classname!=''){
                if(file_exists(dirname(__FILE__)."/controller/extender/".$name.".extender.inc")){
                    include_once(dirname(__FILE__)."/controller/extender/".$name.".extender.inc");
                }
            }
            if(class_exists($classname,false) && $classname!=''){
                $this->extender[$name]=new $classname;
                $this->loadLang($name);
                $flag=true;
            }
        }
        return $flag;
    }

    /*
     * IDs BLOCK
     */
    final public function setIDs($IDs){
        $IDs=$this->cleanIDs($IDs);
        $type = $this->getCFGDef('idType','parents');
        $depth = $this->getCFGDef('depth','1');
        if($type=='parents' && $depth>1){
            $tmp=$IDs;
            do{
                if(count($tmp)>0){
                    $tmp=$this->getChildernFolder($tmp);
                    $IDs=array_merge($IDs,$tmp);
                }
            }while((--$depth)>1);
        }
        return ($this->IDs=$IDs);
    }

    final public function cleanIDs($IDs,$sep=',') {
        $out=array();
        if(!is_array($IDs)){
            $IDs=explode($sep,$IDs);
        }
        foreach($IDs as $item){
            if((int)$item==$item){
                $out[]=$item;
            }
        }
        $out = array_unique($out);
		return $out;
	}
    final protected function checkIDs(){
           return (is_array($this->IDs) && count($this->IDs)>0) ? true : false;
    }

    /*
     * Get all field values from array documents
     *
     * @param string $userField field name
     * @param boolean $uniq Only unique values
     * @global array $_docs all documents
     * @return array all field values
     */
    final public function getOneField($userField,$uniq=false){
        $out=array();
        foreach($this->_docs as $doc=>$val){
            if(isset($val[$userField]) && (($uniq && !in_array($val[$userField],$out)) || !$uniq)){
                $out[$doc]=$val[$userField];
            }
        }
        return $out;
    }

    /*
     * SQL BLOCK
     */
    abstract public function getChildrenCount();
    abstract public function getChildernFolder($id);

    /*
     *    Sorting method in SQL queries
     *
     *    @global string $order
     *    @global string $orderBy
     *    @global string sortBy
     *
     *    @param string $sortNme default sort field
     *    @param string $orderDef default order (ASC|DESC)
     *
     *    @return string Order by for SQL
     */
    final protected function SortOrderSQL($sortName,$orderDef='DESC'){
        $out=array('orderBy'=>'','order'=>'','sortBy'=>'');
        if(($tmp=$this->getCFGDef('orderBy',''))!=''){
            $out['orderBy']=$tmp;
        }else{
            switch(true){
                case (''!=($tmp=$this->getCFGDef('sortDir',''))):{ //higher priority than order
                    $out['order']=$tmp;
                }
                case (''!=($tmp=$this->getCFGDef('order',''))):{
                    $out['order']=$tmp;
                }
            }
            if(''==$out['order'] || !in_array(strtoupper($out['order']),array('ASC','DESC'))){
                $out['order']=$orderDef; //Default
            }

            $out['sortBy']= (($tmp=$this->getCFGDef('sortBy',''))!='') ? $tmp : $sortName;
            $out['orderBy'] = $out['sortBy']. " ".$out['order'];
        }
        $this->setConfig($out); //reload config;
        return "ORDER BY ".$out['orderBy'];
    }

    final protected  function LimitSQL($limit=0,$offset=0){
		$ret='';
		if($limit==0){
			$limit=$this->getCFGDef('display',0);
		}
		if($offset==0){
			$offset=$this->getCFGDef('offset',0);
		}
		$offset+=$this->getCFGDef('start',0);
		$total=$this->getCFGDef('total',0);
		if($limit<($total-$limit)){
			$limit=$total-$offset;
		}

		if($limit!=0){
			$ret="LIMIT ".(int)$offset.",".(int)$limit;
		}else{
			if($offset!=0){
				 /*
				 * To retrieve all rows from a certain offset up to the end of the result set, you can use some large number for the second parameter
				 * @see http://dev.mysql.com/doc/refman/5.0/en/select.html
				 */
				$ret="LIMIT ".(int)$offset.",18446744073709551615";
			}
		}
		return $ret;
	}

    /*
    * @TODO: replace { and }
    */
	final public function sanitarData($data){
		$data=str_replace(array('[', '%5B', ']', '%5D'), array('&#91;', '&#91;', '&#93;', '&#93;'),htmlspecialchars($data));
		return $data;
	}
    /*
     * run tree build
     *
     * @param string $idField default name id field
     * @param string $parentField default name parent field
     */
    final public function treeBuild($idField='id',$parentField='parent'){
        return $this->_treeBuild($this->_docs,$this->getCFGDef('idField',$idField),$this->getCFGDef('parentField',$parentField));
    }
    /*
	* @see: https://github.com/DmitryKoterov/DbSimple/blob/master/lib/DbSimple/Generic.php#L986
     *
     * @param array $data Associative data array
     * @param string $idName name ID field in associative data array
     * @param string $pidName name parent field in associative data array
	*/
    final private function _treeBuild($data, $idName, $pidName){
        $children = array(); // children of each ID
        $ids = array();
        foreach ($data as $i=>$r) {
            $row =& $data[$i];
            $id = $row[$idName];
            $pid = $row[$pidName];
            $children[$pid][$id] =& $row;
            if (!isset($children[$id])) $children[$id] = array();
            $row['#childNodes'] =& $children[$id];
            $ids[$row[$idName]] = true;
        }
        // Root elements are elements with non-found PIDs.
        $this->_tree = array();
        foreach ($data as $i=>$r) {
            $row =&$data[$i];
            if (!isset($ids[$row[$pidName]])) {
                $this->_tree[$row[$idName]] =$row;
            }
        }

        return $this->_tree;
    }
}

/**
 * DocLister abstract extender class
 *
 * @license GNU General Public License (GPL), http://www.gnu.org/copyleft/gpl.html
 * @author Agel_Nash <Agel_Nash@xaker.ru>
 * @date 09.03.2012
 * @version 1.0.1
 *
 */
abstract class extDocLister{
    protected $DocLister;
    protected $modx;
    protected $_cfg=array();

    abstract protected function run();

    final public function init($DocLister){
        $flag=false;
        if($DocLister instanceof DocLister){
            $this->DocLister=$DocLister;
            $this->modx=$this->DocLister->getMODX();
            $this->checkParam(func_get_args());
            $flag=$this->run();
        }
        return $flag;
    }
    
    final protected function checkParam($args){
        if(isset($args[1])){
            $this->_cfg=$args[1];
        }
    }

    final protected function getCFGDef($name,$def){
		return isset($this->_cfg[$name])?$this->_cfg[$name]:$def;
	}
}
?>