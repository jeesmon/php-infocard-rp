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
 * @subpackage Zend_InfoCard_Xml
 * @copyright  Copyright (c) 2005-2008 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Saml.php 9094 2008-03-30 18:36:55Z thomas $
 */

/**
 * A Xml Assertion Document in SAML Token format
 *
 * @category   Zend
 * @package    Zend_InfoCard
 * @subpackage Zend_InfoCard_Xml
 * @copyright  Copyright (c) 2005-2008 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_InfoCard_Xml_Assertion_Saml2 extends SimpleXMLElement
{

    const SAML_ASSERTION_2_0_NS = "urn:oasis:names:tc:SAML:2.0:assertion";

    /**
     * Audience Restriction Condition
     */
    const CONDITION_AUDIENCE = 'AudienceRestriction';

    /**
     * The URI for a 'bearer' confirmation
     */
    const CONFIRMATION_BEARER = 'urn:oasis:names:tc:SAML:2.0:cm:bearer';

    /**
     * The amount of time in seconds to buffer when checking conditions to ensure
     * that differences between client/server clocks don't interfer too much
     */
    const CONDITION_TIME_ADJ = 3600; // +- 5 minutes

    protected function _getServerName() {
        return $_SERVER['SERVER_NAME'];
    }

    protected function _getServerPort() {
        return $_SERVER['SERVER_PORT'];
    }

    /**
     * Validate the conditions array returned from the getConditions() call
     *
     * @param array $conditions An array of condtions for the assertion taken from getConditions()
     * @return mixed Boolean true on success, an array of condition, error message on failure
     */
    public function validateConditions(Array $conditions)
    {

        $currentTime = time();

        if(!empty($conditions)) {

            foreach($conditions as $condition => $conditionValue) {
                switch(strtolower($condition)) {
                    case 'audiencerestriction':

                        $serverName = $this->_getServerName();
                        $serverPort = $this->_getServerPort();

                        $self_aliases[] = $serverName;
                        $self_aliases[] = "{$serverName}:{$serverPort}";

                        $found = false;
                        if(is_array($conditionValue)) {
                            foreach($conditionValue as $audience) {

                                list(,,$audience) = explode('/', $audience);
                                if(in_array($audience, $self_aliases)) {
                                    $found = true;
                                    break;
                                }
                            }
                        }

                        if(!$found) {
                            return array($condition, 'Could not find self in allowed audience list');
                        }

                        break;
                    case 'notbefore':
                        $notbeforetime = strtotime($conditionValue);

                        if($currentTime < $notbeforetime) {
                            if($currentTime + self::CONDITION_TIME_ADJ < $notbeforetime) {
                                return array($condition, 'Current time is before specified window');
                            }
                        }

                        break;
                    case 'notonorafter':
                        $notonoraftertime = strtotime($conditionValue);

                        if($currentTime >= $notonoraftertime) {
                            if($currentTime - self::CONDITION_TIME_ADJ >= $notonoraftertime) {
                                return array($condition, 'Current time is after specified window');
                            }
                        }

                        break;

                }
            }
        }
        return true;
    }

    /**
     * Get the Assertion URI for this type of Assertion
     *
     * @return string the Assertion URI
     */
    public function getAssertionURI()
    {
        return Zend_InfoCard_Xml_Assertion::TYPE_SAML;
    }

    /**
     * Get the Major Version of the SAML Assertion
     *
     * @return integer The major version number
     */
    public function getMajorVersion()
    {
        return (int)(string)$this['MajorVersion'];
    }

    /**
     * The Minor Version of the SAML Assertion
     *
     * @return integer The minor version number
     */
    public function getMinorVersion()
    {
        return (int)(string)$this['MinorVersion'];
    }

    /**
     * Get the Assertion ID of the assertion
     *
     * @return string The Assertion ID
     */
    public function getAssertionID()
    {
        return (string)$this['ID'];
    }

    /**
     * Get the Issuer URI of the assertion
     *
     * @return string the URI of the assertion Issuer
     */
    public function getIssuer()
    {
        return (string)$this['Issuer'];
    }

    /**
     * Get the Timestamp of when the assertion was issued
     *
     * @return integer a UNIX timestamp representing when the assertion was issued
     */
    public function getIssuedTimestamp()
    {
        return strtotime((string)$this['IssueInstant']);
    }

    /**
     * Return an array of conditions which the assertions are predicated on
     *
     * @throws Exception
     * @return array an array of conditions
     */
    public function getConditions()
    {

	$this->registerXPathNamespace('saml', self::SAML_ASSERTION_2_0_NS);
        list($conditions) = $this->xpath("//saml:Conditions");

        if(!($conditions instanceof SimpleXMLElement)) {
            throw new Exception("Unable to find the saml:Conditions block");
        }

        $retval = array();

        foreach($conditions->children(self::SAML_ASSERTION_2_0_NS) as $key => $value) {
            switch($key) {
                case self::CONDITION_AUDIENCE:
                    foreach($value->children(self::SAML_ASSERTION_2_0_NS) as $audience_key => $audience_value) {
                        if($audience_key == 'Audience') {
                            $retval[$key][] = (string)$audience_value;
                        }
                    }
                    break;
            }
        }

        $retval['NotBefore'] = (string)$conditions['NotBefore'];
        $retval['NotOnOrAfter'] = (string)$conditions['NotOnOrAfter'];

        return $retval;
    }

    /**
     * Get they KeyInfo element for the Subject KeyInfo block
     *
     * @todo Not Yet Implemented
     * @ignore
     */
    public function getSubjectKeyInfo()
    {
        /**
         * @todo Not sure if this is part of the scope for now..
         */

        if($this->getConfirmationMethod() == self::CONFIRMATION_BEARER) {
            throw new Exception("Cannot get Subject Key Info when Confirmation Method was Bearer");
        }
    }

    /**
     * Return the Confirmation Method URI used in the Assertion
     *
     * @return string The confirmation method URI
     */
    public function getConfirmationMethod()
    {
	$this->registerXPathNamespace('saml', self::SAML_ASSERTION_2_0_NS);
        list($confirmation) = $this->xPath("//saml:SubjectConfirmation");
        return (string)$confirmation['Method'];
    }

    /**
     * Return an array of attributes (claims) contained within the assertion
     *
     * @return array An array of attributes / claims within the assertion
     */
    public function getAttributes()
    {
	$this->registerXPathNamespace('saml', self::SAML_ASSERTION_2_0_NS);
        $attributes = $this->xPath('//saml:Attribute');

        $retval = array();
        foreach($attributes as $key => $value) {

            $retkey = (string)$value['Name'];

            $retval[$retkey]['name'] = (string)$value['Name'];
	    $exploded = explode("/", (string)$value['Name']);
            $attrNS = "";
            for($ei=0; $ei < count($exploded) - 1; $ei++) {
              if($ei > 0) $attrNS .= "/"; 
	      $attrNS .= $exploded[$ei];
            }
            $retval[$retkey]['namespace'] = $attrNS;

            list($aValue) = $value->children(self::SAML_ASSERTION_2_0_NS);
            $retval[$retkey]['value'] = (string)$aValue;
        }

        return $retval;
    }
}

