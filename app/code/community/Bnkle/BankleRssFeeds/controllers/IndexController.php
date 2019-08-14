<?php
/**
 * @author Bnkle
 *
 */
class Bnkle_BankleRssFeeds_IndexController extends Mage_Core_Controller_Front_Action {
	private $oProducts;
	private $oProdudctIds;
	private $sBaseXml = '<?xml version="1.0" encoding="UTF-8"?><products></products>';
	private $sProductElement = 'product';
	private $oFeed;
	private $oXml;
	private $oConfig;
	private $oProductModel;
	private $aBadChars = array('"',"\r\n","\n","\r","\t");
	private $aReplaceChars = array(""," "," "," ",""); 
	private $aConfigPaths = array (	'bnklerssfeeds/general/enabled',
									'bnklerssfeeds/general/urlparam',
									'bnklerssfeeds/general/imgsmall_width',
									'bnklerssfeeds/general/imgsmall_height',
									'bnklerssfeeds/general/imgmedium_width',
									'bnklerssfeeds/general/imgmedium_height'
	);

	private $aDataMap = array(	'internal_id' 		=> 'id',
								'title' 			=> 'title',
								'description' 		=> 'description',
								'img_large' 		=> 'image_link_large',
								'img_medium'		=> 'image_link_medium',
								'img_small'			=> 'image_link_small',
								'link' 				=> 'link',
								'minimum_price' 	=> 'special_price',
								'maximum_price' 	=> 'price',
								'category' 			=> 'category',
								'sub_category' 		=> 'subcategory',
								'images' 		=> 'media_gallery');

	public function indexAction() {
		set_time_limit(3600);
		$this->setConfig();
		if($this->oConfig->general->enabled == 1) {		
			$this->initFeed();
			$this->getProducts();
			foreach($this->oProdudctIds as $iProduct) {
				$this->addToFeed($iProduct);
			}
			$this->sendHeader();
			$this->getXml();
		}
	}

	private function setConfig() {
		$this->oConfig = new StdClass();
		foreach($this->aConfigPaths as $sPath) {
			$aParts = explode('/',$sPath);
			@$this->oConfig->$aParts[1]->$aParts[2] = Mage::getStoreConfig($sPath);
		}

		if(!is_numeric($this->oConfig->general->imgsmall_width)) {
			$this->oConfig->general->imgsmall_width = 150;
		}
		if(!is_numeric($this->oConfig->general->imgsmall_height)) {
			$this->oConfig->general->imgsmall_height = 150;
		}
		if(!is_numeric($this->oConfig->general->imgmedium_width)) {
			$this->oConfig->general->imgmedium_width = 320;
		}

		if(!is_numeric($this->oConfig->general->imgmedium_height)) {
			$this->oConfig->general->imgmedium_height = 320;
		}		
	}

	private function initFeed() {
		$this->oFeed = new Bnkle_BankleRssFeeds_Helper_SimpleXml($this->sBaseXml);
	}

	private function getProducts() {
		$this->oProducts = Mage::getModel('catalog/product')->getCollection();
		$this->oProducts->addAttributeToFilter('status', 1);//enabled
		$this->oProducts->addAttributeToFilter('visibility', 4);//catalog, search
		$this->oProducts->addAttributeToSelect('*');
		$this->oProdudctIds = $this->oProducts->getAllIds();		
	}

	private function addToFeed($iProduct) {
		$aData = $this->getSanitized($this->getProductData($iProduct));
		
		$oFeedProduct = $this->oFeed->addChild($this->sProductElement);
		$this->addDataToFeedProduct($aData,$oFeedProduct);	
	}

	private function addDataToFeedProduct($aData,$oFeedProduct) {
		foreach($this->aDataMap as $sTarget => $sSource) {
			if($sSource == 'link' && $this->oConfig->general->urlparam != '') {
				$aData[$sSource] = $this->addUrlParam($aData[$sSource]);
			}
			$oFeedProduct->addCData($sTarget,$aData[$sSource]);	
		}		
	}

	private function addUrlParam($sUrl) {
		if(!strstr($sUrl,'?')) {
			$sUrl .= '?'.str_replace('?','',$this->oConfig->general->urlparam);	
		} else {
			$sUrl .= '&'.str_replace('?','',$this->oConfig->general->urlparam);
		}		

		return $sUrl;		
	}

	private function getSanitized($aData) {
		$aSanitized = array();
	 	foreach($aData as $k=>$val){
			$aSanitized[$k] = str_replace($this->aBadChars,$this->aReplaceChars,$val);
		}	

		return $aSanitized;
	}

	private function getProductData($iProduct) {
		$oProduct = Mage::getModel('catalog/product');
		$oProduct ->load($iProduct);
		//echo '<pre>';
		//print_r($oProduct); die;
		
		$aCats = $this->getCategories($oProduct);
	    $aData = array();	
	    $aData['id']=$iProduct;
	    $aData['sku']=$oProduct->getSku();	
	    $aData['brand']=$oProduct->getResource()->getAttribute('manufacturer')->getFrontend()->getValue($oProduct);	
	    $aData['title']=$oProduct->getName();
	    $aData['description']=strip_tags($oProduct->getDescription());
	    $aData['price']=$oProduct->getPrice();
	    $aData['special_price']=$oProduct->getSpecialPrice();
		$aData['availability']='yes';
		$aData['condition']='';
	    $aData['link']=$oProduct->getProductUrl();
	    
		
		
		
		$aData['image_link_large']= Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product'.$oProduct->getImage();
		
	    $aData['stock_descrip']='';		
	    $aData['shipping_rate']='';
	    $aData['category'] = $aCats['main'];
	    $aData['subcategory'] = $aCats['sub'];

	    if($aData['special_price'] == '') {
	    	$aData['special_price'] = $aData['price'];
	    }
		
		$imagesArray = $oProduct->getMediaGallery();
		$imagesArray1	=	$imagesArray['images'];
	
		
		for($i=0;isset($imagesArray1[$i]);$i++)
			{
				$imagesNEwArr[]	=	Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product'.$imagesArray1[$i]['file'];
			}
		//$aData['media_gallery'] = $imagesNEwArr;
		
		$aData['media_gallery'] = implode('@@',$imagesNEwArr);
		
			
	    return $aData;
	}

	private function getCategories($oProduct) {
		$aIds = $oProduct->getCategoryIds();
		$aCategories = array();

		foreach($aIds as $iCategory){
			$oCategory = Mage::getModel('catalog/category')->load($iCategory);
			// echo $oCategory->getId();die;
			//$newCats[]	=	 $oCategory->getName(); 
			switch($oCategory->getLevel()) {
			    case '1':
			    //$aCategories['main'] = $oCategory->getName();
				$ID	=	 $oCategory->getId(); 
				$Name	=	 $oCategory->getName();
				$newCats[] = $ID.'@@'.$Name;
				case '2':
					//$aCategories['main'][] = $oCategory->getName();
					break;
				case '3':
					//$aCategories['sub'] = $oCategory->getName();
					break;					
			}
	    }

		$aCategories['main'] = implode('#',$newCats);
	    return $aCategories;
	}

	private function sendHeader() {
		header('Content-type: text/xml');
	}

	private function getXml() {
		$oXml = new DOMDocument('1.0');
		$oXml->formatOutput = true;
		$oNode = dom_import_simplexml($this->oFeed);
		$oNode = $oXml->importNode($oNode, true);
		$oNode = $oXml->appendChild($oNode);		
		echo $oXml->saveXML();		
	}

	private function debug($m) {
		echo '<pre>';
		print_r($m);
		echo '</pre>';
	}

}

