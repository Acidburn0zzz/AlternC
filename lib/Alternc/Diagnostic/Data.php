<?php 


/**
 * Uniform data component containing other components or real data
 */
class Alternc_Diagnostic_Data {
    
    public $index                       = array();
    public $data                        = array();
    public $type                        = "";
    public $metadata                    = null;
    
    
    const TYPE_ROOT                     = "root";
    const TYPE_DOMAIN                   = "service";
    const TYPE_SECTION                  = "section";
   
    
    public function __construct( $type, $sectionData = null) {
        $this->type                     = $type;
        if( $sectionData){
            $this->data                 = $sectionData;
        }
        
    }

    // recursive rebuild
    function buildFromArray( $content ){

	if( ! $this->isValidArrayData( $content ) ) { 
	    return $content;
	}
	$type				= $content["type"];
	$newInstance			= new Alternc_Diagnostic_Data( $type );
	$newInstance->index		= $content["index"];
	$newInstance->metadata		= $content["metadata"];
	$data				= $content["data"];
	// The content is raw
	if( $type === self::TYPE_SECTION){
	    $newInstance->data		= $data;
	}
	// The content is made of services or sections
	else foreach( $content["data"] as $section_name => $sectionData ){
		$sectionContent		= $this->buildFromArray( $sectionData );
		$newInstance->addData( $section_name, $sectionContent );
	}
	return $newInstance;

    }

	// Make sure we have a valid format result
    function isValidArrayData( $content ){
	if(
	    !is_array($content)
	    || !array_key_exists( "type", $content ) 
	    || !array_key_exists("metadata",$content) 
	    || !array_key_exists("index",$content) 
	    || !array_key_exists("data",$content)  
	    || ! in_array( $content["type"], array(self::TYPE_ROOT,self::TYPE_DOMAIN,self::TYPE_SECTION) ) ){
	
	    return false;
	
	}
	return true;
    }
    
    /**
     * Sets 
     * 
     * @param string $sectionname
     * @param Alternc_Diagnostic_Data $data
     * @return boolean
     */
    function addData( $sectionname, Alternc_Diagnostic_Data $data){
        $this->index[]                  = $sectionname;
        $this->data[$sectionname]       = $data;
        return true;
    }
    
    /**
     * @param array index
     */
    public function setIndex($index) {
        $this->index                    = $index;
        return $this;
    }

    /**
     * @return array
     */
    public function getIndex() {
        return $this->index;
    }
    
    /**
     * @param array data
     */
    public function setData($data) {
        $this->data                     = $data;
        return $this;
    }

    /**
     * @return array
     */
    public function getData() {
        return $this->data;
    }

        /**
     * @param string type
     */
    public function setType($type) {
        $this->type                     = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param array metadata
     */
    public function setMetadata($metadata) {
        $this->metadata                 = $metadata;
        return $this;
    }

    /**
     * @return array
     */
    public function getMetadata() {
        return $this->metadata;
    }

    /**
     * Retrieves a given section of the data
     * 
     * 
     * @param string $section_name
     * @return boolean
     */
    public function getSection( $section_name ){
        
        if( !in_array($section_name, $this->index)){
            return FALSE;
        }
        return $this->data[$section_name];
        
    }
    
}
