<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_InfoCard
 * @subpackage Zend_InfoCard_Xml_Security
 * @copyright  Copyright (c) 2005-2008 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Security.php 9094 2008-03-30 18:36:55Z thomas $
 */

/**
 * Zend_InfoCard_Xml_Security_Transform
 */
require_once 'InfoCard/Zend_InfoCard_Xml_Security_Transform.php';

/**
 *
 * @category   Zend
 * @package    Zend_InfoCard
 * @subpackage Zend_InfoCard_Xml_Security
 * @copyright  Copyright (c) 2005-2008 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_InfoCard_Xml_Security
{
    /**
     * ASN.1 type INTEGER class
     */
    const ASN_TYPE_INTEGER = 0x02;

    /**
     * ASN.1 type BIT STRING class
     */
    const ASN_TYPE_BITSTRING = 0x03;

    /**
     * ASN.1 type SEQUENCE class
     */
    const ASN_TYPE_SEQUENCE = 0x30;

    /**
     * The URI for Canonical Method C14N Exclusive
     */
    const CANONICAL_METHOD_C14N_EXC = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    /**
     * The URI for Signature Method SHA1
     */
    const SIGNATURE_METHOD_SHA1 = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';

    /**
     * The URI for Digest Method SHA1
     */
    const DIGEST_METHOD_SHA1 = 'http://www.w3.org/2000/09/xmldsig#sha1';

    /**
     * The Identifier for RSA Keys
     */
    const RSA_KEY_IDENTIFIER = '300D06092A864886F70D0101010500';

    /**
     * Constructor  (disabled)
     *
     * @return void
     */
    private function __construct()
    {
    }

    /**
     * Validates the signature of a provided XML block
     *
     * @param  string $strXMLInput An XML block containing a Signature
     * @return bool True if the signature validated, false otherwise
     * @throws Exception
     */
    static public function validateXMLSignature($strXMLInput)
    {
        if(!extension_loaded('openssl')) {
            throw new Exception("You must have the openssl extension installed to use this class");
        }

        $sxe = simplexml_load_string($strXMLInput);

	$sxe->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        list($canonMethod) = $sxe->xpath("//ds:Signature/ds:SignedInfo/ds:CanonicalizationMethod");
        switch((string)$canonMethod['Algorithm']) {
            case self::CANONICAL_METHOD_C14N_EXC:
                $cMethod = (string)$canonMethod['Algorithm'];
                break;
            default:
                throw new Exception("Unknown or unsupported CanonicalizationMethod Requested");
        }

        list($signatureMethod) = $sxe->xpath("//ds:Signature/ds:SignedInfo/ds:SignatureMethod");
        switch((string)$signatureMethod['Algorithm']) {
            case self::SIGNATURE_METHOD_SHA1:
                $sMethod = (string)$signatureMethod['Algorithm'];
                break;
            default:
                throw new Exception("Unknown or unsupported SignatureMethod Requested");
        }

        list($digestMethod) = $sxe->xpath("//ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestMethod");
        switch((string)$digestMethod['Algorithm']) {
            case self::DIGEST_METHOD_SHA1:
                $dMethod = (string)$digestMethod['Algorithm'];
                break;
            default:
                throw new Exception("Unknown or unsupported DigestMethod Requested");
        }

        $base64DecodeSupportsStrictParam = version_compare(PHP_VERSION, '5.2.0', '>=');

        list($digestValue) = $sxe->xpath("//ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestValue");
        if ($base64DecodeSupportsStrictParam) {
            $dValue = base64_decode((string)$digestValue, true);
        } else {
            $dValue = base64_decode((string)$digestValue);
        }

        list($signatureValueElem) = $sxe->xpath("//ds:Signature/ds:SignatureValue");
        if ($base64DecodeSupportsStrictParam) {
            $signatureValue = base64_decode((string)$signatureValueElem, true);
        } else {
            $signatureValue = base64_decode((string)$signatureValueElem);
        }

        $transformer = new Zend_InfoCard_Xml_Security_Transform();

	//need to fix this later
        $transforms = $sxe->xpath("//ds:Signature/ds:SignedInfo/ds:Reference/ds:Transforms/ds:Transform");
        while(list( , $transform) = each($transforms)) {
          $transformer->addTransform((string)$transform['Algorithm']);
        }

        $transformed_xml = $transformer->applyTransforms($strXMLInput);

        $transformed_xml_binhash = pack("H*", sha1($transformed_xml));

        if($transformed_xml_binhash != $dValue) {
            throw new Exception("Locally Transformed XML does not match XML Document. Cannot Verify Signature");
        }

        $public_key = null;

	$sxe->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
	list($x509Data) = $sxe->xpath("ds:Signature/ds:KeyInfo/ds:X509Data");
        list($keyValue) = $sxe->xpath("ds:Signature/ds:KeyInfo/ds:KeyValue");
          
        if(isset($x509Data))
        { 
          $x509Data->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
          list($x509cert) = $x509Data->xpath("ds:X509Certificate");
        }
        
        if(isset($keyValue))
        { 
          $keyValue->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
          list($rsaKeyValue) = $keyValue->xpath("ds:RSAKeyValue");
        }

        switch(true) {
            case isset($x509cert):

                $certificate = (string)$x509cert;


                $pem = "-----BEGIN CERTIFICATE-----\n" .
                       wordwrap($certificate, 64, "\n", true) .
                       "\n-----END CERTIFICATE-----";

                $public_key = openssl_pkey_get_public($pem);

                if(!$public_key) {
                    throw new Exception("Unable to extract and prcoess X509 Certificate from KeyValue");
                }

                break;
            case isset($rsaKeyValue):

	        $rsaKeyValue->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
                list($modulus) = $rsaKeyValue->xpath("ds:Modulus");
                list($exponent) = $rsaKeyValue->xpath("ds:Exponent");
                if(!isset($modulus) ||
                   !isset($exponent)) {
                    throw new Exception("RSA Key Value not in Modulus/Exponent form");
                }

                $modulus = base64_decode((string)$modulus);
                $exponent = base64_decode((string)$exponent);

                $pem_public_key = self::_getPublicKeyFromModExp($modulus, $exponent);

                $public_key = openssl_pkey_get_public ($pem_public_key);

                break;
            default:
                throw new Exception("Unable to determine or unsupported representation of the KeyValue block");
        }

        $transformer = new Zend_InfoCard_Xml_Security_Transform();
        $transformer->addTransform((string)$canonMethod['Algorithm']);

        list($signedInfo) = $sxe->xpath("//ds:Signature/ds:SignedInfo");
	$signedInfoXML = self::addNamespace($signedInfo, "http://www.w3.org/2000/09/xmldsig#"); 

        $canonical_signedinfo = $transformer->applyTransforms($signedInfoXML);

        if(openssl_verify($canonical_signedinfo, $signatureValue, $public_key)) {
	    list($reference) = $sxe->xpath("//ds:Signature/ds:SignedInfo/ds:Reference");
            return (string)$reference['URI'];
        }

        return false;
    }

    private function addNamespace($xmlElem, $ns) {
	//warning expected
	$xe = simplexml_load_string(($xmlElem->asXML()), "SimpleXMLElement", LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach($xe->getNamespaces() as $xns) {
          if($xns == $ns) {
            return $xmlElem->asXML();
          }
        }

        $xmlElem->addAttribute('DS_NS', $ns);
	$xml = $xmlElem->asXML();
        if(preg_match("/<(\w+)\:\w+/", $xml, $matches)) {
          $prefix = $matches[1];
          $xml = str_replace("DS_NS", "xmlns:" . $prefix, $xml);
        }
        else {
          $xml = str_replace("DS_NS", "xmlns", $xml);
        }

        return $xml;
    }

    /**
     * Transform an RSA Key in Modulus/Exponent format into a PEM encoding and
     * return an openssl resource for it
     *
     * @param string $modulus The RSA Modulus in binary format
     * @param string $exponent The RSA exponent in binary format
     * @return string The PEM encoded version of the key
     */
    static protected function _getPublicKeyFromModExp($modulus, $exponent)
    {
        $modulusInteger  = self::_encodeValue($modulus, self::ASN_TYPE_INTEGER);
        $exponentInteger = self::_encodeValue($exponent, self::ASN_TYPE_INTEGER);
        $modExpSequence  = self::_encodeValue($modulusInteger . $exponentInteger, self::ASN_TYPE_SEQUENCE);
        $modExpBitString = self::_encodeValue($modExpSequence, self::ASN_TYPE_BITSTRING);

        $binRsaKeyIdentifier = pack( "H*", self::RSA_KEY_IDENTIFIER );

        $publicKeySequence = self::_encodeValue($binRsaKeyIdentifier . $modExpBitString, self::ASN_TYPE_SEQUENCE);

        $publicKeyInfoBase64 = base64_encode( $publicKeySequence );

        $publicKeyString = "-----BEGIN PUBLIC KEY-----\n";
        $publicKeyString .= wordwrap($publicKeyInfoBase64, 64, "\n", true);
        $publicKeyString .= "\n-----END PUBLIC KEY-----\n";

        return $publicKeyString;
    }

    /**
     * Encode a limited set of data types into ASN.1 encoding format
     * which is used in X.509 certificates
     *
     * @param string $data The data to encode
     * @param const $type The encoding format constant
     * @return string The encoded value
     * @throws Exception
     */
    static protected function _encodeValue($data, $type)
    {
        // Null pad some data when we get it (integer values > 128 and bitstrings)
        if( (($type == self::ASN_TYPE_INTEGER) && (ord($data) > 0x7f)) ||
            ($type == self::ASN_TYPE_BITSTRING)) {
                $data = "\0$data";
        }

        $len = strlen($data);

        // encode the value based on length of the string
        // I'm fairly confident that this is by no means a complete implementation
        // but it is enough for our purposes
        switch(true) {
            case ($len < 128):
                return sprintf("%c%c%s", $type, $len, $data);
            case ($len < 0x0100):
                return sprintf("%c%c%c%s", $type, 0x81, $len, $data);
            case ($len < 0x010000):
                return sprintf("%c%c%c%c%s", $type, 0x82, $len / 0x0100, $len % 0x0100, $data);
            default:
                throw new Exception("Could not encode value");
        }

        throw new Exception("Invalid code path");
    }
}
