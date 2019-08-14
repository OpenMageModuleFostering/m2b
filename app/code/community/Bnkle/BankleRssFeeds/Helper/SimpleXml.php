<?php 



class Bnkle_BankleRssFeeds_Helper_SimpleXml extends SimpleXMLElement {

	

  public function addCData($nodename,$cdata_text) {

    $node = $this->addChild($nodename); 

    $node = dom_import_simplexml($node);

    $no = $node->ownerDocument;

    $node->appendChild($no->createCDATASection($cdata_text));

  }	

	

} 



?>