<?php

namespace FlashCardGenerator\DataSource;

include_once('AbstractDataSource.php');

class PHPManualDataSource extends AbstractDataSource
{

    const MANUAL_URL_PREPEND_BOOK = 'book.';
    const DEFAULT_MANUAL_LOCATION = 'resources/php-chunked-xhtml';

    protected $phpManualBaseUrl;

    public function __construct($phpManualBaseUrl=self::DEFAULT_MANUAL_LOCATION){
        $this->phpManualBaseUrl = $phpManualBaseUrl;
    }

    protected function _buildUrl($fileName){
        return $this->phpManualBaseUrl.'/'.$fileName;
    }


    protected function _parseBookPage($bookPageDom){

        //query functions from book
        $xpathQueryFunction = new \DOMXPath($bookPageDom);
        $domFunctions = $xpathQueryFunction->query("//ul[@class='chunklist chunklist_book chunklist_children']/li/a['href']");

        //build return array with functions
        $functionsData = array();
        for($i=0;$i<$domFunctions->length;$i++) {

            //build manual file location
            $functionUrl = $this->_buildUrl($domFunctions->item($i)->getAttribute('href'));

            //make sure manual file exists
            if (!file_exists($functionUrl)){
                //fix-me: add error reporting?
                continue;
            }

            //load the html file for the specific function
            $functionPageDom = self::constructDOMWithHTML5(file_get_contents($functionUrl));

            array_push($functionsData,$this->_parseFunctionPage($functionPageDom));

        }

        return $functionsData;

    }

    protected function _parseFunctionPage(\DOMDocument $functionPageDom){

        return(array('name'=>$this->_parseFunctionPageFunctionName($functionPageDom),
                        'parameters'=>$this->_parseFunctionPageFunctionParameters($functionPageDom),
                        'description'=>$this->_parseFunctionPageFunctionDescription($functionPageDom),
                        'return'=>$this->_parseFunctionPageFunctionReturn($functionPageDom),
        ));

    }

    protected function _parseFunctionPageFunctionName(\DOMDocument $functionPageDom){

        //query for the parameter definition
        $queryParameters = new \DOMXPath($functionPageDom);
        $domParameters = $queryParameters->query("//h1[@class='refname']");

        //no parameters found
        if($domParameters->length==0){
            return;
        }else{

            //get first item
            $domParametersString = $domParameters->item(0)->textContent;
            //strip newlines
            $domParametersString = str_replace("\n", '', $domParametersString);
            //trip
            $domParametersString = trim($domParametersString);
            //return
            return $domParametersString;
        }

    }

    protected function _parseFunctionPageFunctionParameters(\DOMDocument $functionPageDom){

        //query for the parameter definition
        $queryParameters = new \DOMXPath($functionPageDom);
        $domParameters = $queryParameters->query("//div[@class='methodsynopsis dc-description']");

        //no parameters found
        if($domParameters->length==0){
            return;
        }else{

            //get first item
            $domParametersString = $domParameters->item(0)->textContent;
            //strip newlines
            $domParametersString = str_replace("\n", '', $domParametersString);
            //find content inside parenthesis
            $domParametersStringMatch = preg_split('/(\(|\))/', $domParametersString);
            //add parenthesis back
            $domParametersString = "({$domParametersStringMatch[1]})";
            //return
            return $domParametersString;
        }


    }

    protected function _parseFunctionPageFunctionDescription(\DOMDocument $functionPageDom){

        $queryDescription = new \DOMXPath($functionPageDom);
        $domDescription = $queryDescription->query("//p[@class='para rdfs-comment']");

        if(!$domDescription->length==0){
            return;
        }else{

            //get first item
            $domDescriptionString = $domDescription->item(0)->textContent;
            //remove new lines
            $domDescriptionString = str_replace("\n",'',$domDescriptionString);
            //trip
            $domDescriptionString = trim($domDescriptionString);
            //return
            return $domDescriptionString;

        }

    }

    protected function _parseFunctionPageFunctionReturn(\DOMDocument $functionPageDom){

        return null;

    }

    public function getFunctionInformationFromBook($bookName)
    {

        //build full url based on book name
        $phpManualFunctionReferenceUrl = $this->_buildUrl(self::MANUAL_URL_PREPEND_BOOK . $bookName . ".html");

        //load into dom
        $dom = self::constructDOMWithHTML5(file_get_contents($phpManualFunctionReferenceUrl));

        $functionsData = $this->_parseBookPage($dom);

        return $functionsData;

    }

    /**
     * @param $contents
     * @return \DOMDocument
     */
    public static function constructDOMWithHTML5(&$contents)
    {
        //load into dom
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($contents);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $dom;
    }

    public function getData($category=null){

        if(is_null($category)){
            throw new \Exception("You must specify which PHP Manual book to use");
        }

        return $this->getFunctionInformationFromBook($category);
    }

    public function formatData(&$data){

        foreach($data as $key=>$functionInformation){

            if(!empty($functionInformation['parameters'])||!empty($functionInformation['description'])){

                array_push($return,array(
                                    'front'=>$functionInformation['name'],
                                    'back'=>$functionInformation['parameters']."\n".$functionInformation['description'],
                ));

            }

        }

        return $return;
    }

}